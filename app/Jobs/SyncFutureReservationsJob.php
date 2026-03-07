<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Service;
use App\Services\FetchDataService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncFutureReservationsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FetchDataService $fetchDataService;


    /**
     * Create a new job instance.
     */
    public function __construct(FetchDataService $fetchDataService)
    {
        $this->fetchDataService = $fetchDataService;
        $this->onQueue('high');
    }

    public function shouldDispatch(): bool
    {
        return !app()->isDownForMaintenance();
    }

    /**
     * Handle the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $payload = [
            "username" => config('services.dd.username'),
            "password" => config('services.dd.password'),
            "dataToPost" => [
                "timestamp" => time(),
                "data" => [
                    [
                        "criteria" => [
                            [
                                "key" => "dateFrom",
                                "operator" => "gte",
                                "value" => Carbon::today()->toDateString()
                            ],
                            [
                                "key" => "dateTo",
                                "operator" => "lte",
                                "value" => Carbon::today()->addMonth()->toDateString()
                            ]
                        ],
                        "limit" => 50,
                        "offset" => 0,
                    ]
                ]
            ]
        ];
        $output = $this->fetchDataService->fetchData(config('services.dd.uris.futureReservations'), $payload)
            ->getData(true);
        if (!isset($output['data']) || !is_array($output['data'])) {
            Log::warning('SyncFutureReservationsJob futureReservations returned no usable data.', [
                'output_type' => gettype($output),
                'output_preview' => is_array($output) ? array_slice($output, 0, 5) : $output,
            ]);
            throw new Exception('No usable data found in response.');
        }

        if (!empty($output['data'])) {
            $data = $output['data'][0];
            $columns = collect($data['columns'])->pluck('index', 'filterKey');

            $specialCats = config('services.dd.special_service_cats');
            $services = Service::query()->get(['id', 'name', 'category']);
            $serviceByName = $services->keyBy('name');

            $keyFor = static fn(int $petId, int $serviceId, $start, $end): string => "{$petId}|{$serviceId}|{$start}|{$end}";
            $futureNullKeys = [];

            foreach ($data['rows'] as $row) {
                $petId = (int)$row[$columns['petId']];
                $dogName = $row[$columns['pet']] . ' ' . $row[$columns['customerLastName']];
                $scheduledStart = $row[$columns['startDate']];
                $scheduledEnd = $row[$columns['endDate']];

                // The API returns a comma-separated serviceName list like:
                // "Boarding:Cabin, Grooming - Bath, Grooming - Brush"
                $serviceNames = collect(explode(',', $row[$columns['serviceName']]))
                    ->map(fn($s) => trim($s))->filter()->values();

                foreach ($serviceNames as $serviceName) {
                    $service = $serviceByName->get($serviceName);

                    if (!$service) {
                        Log::warning("Unknown service name '{$serviceName}' for pet_id {$petId} ({$dogName})");
                        continue;
                    }

                    // Only create placeholder rows for services whose category is in special_service_cats
                    if (!in_array($service->category, $specialCats, true)) {
                        continue;
                    }

                    $k = $keyFor($petId, (int)$service->id, $scheduledStart, $scheduledEnd);
                    $futureNullKeys[$k] = true;

                    try {
                        Appointment::updateOrCreate(
                            [
                                'pet_id' => $petId,
                                'service_id' => (int)$service->id,
                                'scheduled_start' => $scheduledStart,
                                'scheduled_end' => $scheduledEnd,
                                'appointment_id' => null, // placeholder only
                            ],
                            [
                                'pet_name' => $dogName,
                            ]
                        );
                    } catch (Throwable $e) {
                        Log::error("Failed syncing future reservation for pet_id {$petId} (service '{$serviceName}', service_id {$service->id}): {$e->getMessage()}");
                    }
                }
            }

            // Cleanup: delete placeholder rows not present in the latest future-reservations list
            $placeholders = Appointment::query()
                ->whereNull('appointment_id')
                ->whereNotNull('scheduled_start')
                ->where('scheduled_start', '>=', now())
                ->get(['id', 'pet_id', 'service_id', 'scheduled_start', 'scheduled_end']);

            $idsToDelete = [];

            foreach ($placeholders as $a) {
                $k = $keyFor((int)$a->pet_id, (int)$a->service_id, $a->scheduled_start, $a->scheduled_end);
                if (!isset($futureNullKeys[$k])) {
                    $idsToDelete[] = $a->id;
                }
            }

            if ($idsToDelete) {
                Appointment::query()->whereIn('id', $idsToDelete)->delete();
                Log::info('Deleted stale future-reservation placeholders', ['deleted' => count($idsToDelete)]);
            }
        }
    }
}
