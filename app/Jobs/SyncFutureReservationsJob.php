<?php

namespace App\Jobs;

use App\Enums\ServiceColor;
use App\Models\FutureService;
use App\Models\Service;
use App\Services\FetchDataService;
use App\Services\GoogleCalendarService;
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
    protected GoogleCalendarService $googleCalendarService;


    /**
     * Create a new job instance.
     */
    public function __construct(FetchDataService $fetchDataService, GoogleCalendarService $googleCalendarService)
    {
        $this->fetchDataService = $fetchDataService;
        $this->googleCalendarService = $googleCalendarService;
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
        if (count($output['data']) > 0) {
            $data = $output['data'][0];
            $columns = collect($data['columns'])->pluck('index', 'filterKey');

            $services = Service::all();
            $serviceMap = $services->pluck('id', 'name');
            $specialServices = $services->whereIn('category', config('services.dd.special_service_cats'));

            foreach ($data['rows'] as $row) {
                if (!$serviceMap->has($row[$columns['category']])) {
                    Log::warning("Unknown service category '{$row[$columns['category']]}' for {$row[$columns['pet']]}");
                    continue;
                }

                if ($specialServices->pluck('name')->contains($row[$columns['category']])) {
                    $fs = FutureService::updateOrCreate(
                        [
                            'pet_id' => $row[$columns['pet_id']],
                            'service_id' => $serviceMap->get($row[$columns['category']]),
                            'scheduled_start' => $row[$columns['scheduled_start']],
                            'scheduled_end' => $row[$columns['scheduled_end']],
                        ],
                        [
                            'dog_name' => $row[$columns['pet']],
                        ]
                    );

                    try {
                        $summary = "⏳ {$row[$columns['pet']]} – {$row[$columns['category']]}";
                        $description = "{$row[$columns['customerFirstName']]} {$row[$columns['customerLastName']]}\n";
                        $description .= "{$this->formatPhoneNumber($row[$columns['phone']])} {$row[$columns['email']]}\n";
                        $description .= "{$row[$columns['weight']]}} lbs";

                        $service = $services->firstWhere('id', $serviceMap->get($row[$columns['category']]));
                        $eventPayload = [
                            'summary' => $summary,
                            'description' => $description,
                            'start' => [
                                'dateTime' => $fs->scheduled_start->toRfc3339String(),
                                'timeZone' => config('app.timezone'),
                            ],
                            'end' => [
                                'dateTime' => $fs->scheduled_end->toRfc3339String(),
                                'timeZone' => config('app.timezone'),
                            ],
                            'colorId' => ServiceColor::forCategory($service)->googleColorId(),
                        ];

                        if ($fs->google_event_id) {
                            $this->googleCalendarService->createOrUpdateEvent($fs->google_event_id, $eventPayload);
                        } else {
                            $created = $this->googleCalendarService->createOrUpdateEvent(null, $eventPayload);
                            $fs->google_event_id = $created->getId();
                        }

                        $fs->is_synced = true;
                        $fs->save();
                    } catch (Throwable $e) {
                        Log::error("Failed syncing future reservation for pet_id {$fs->pet_id}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        return preg_replace("/^(\d{3})(\d{3})(\d{4})$/", '$1-$2-$3', $phone);
    }

}
