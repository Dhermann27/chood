<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Models\DogService;
use App\Models\Service;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoFetchBookingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $bookingId;


    /**
     * Create a new job instance.
     */
    public function __construct(string $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function uniqueId(): string
    {
        return 'booking' . $this->bookingId;
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $payload = [
            "username" => config('services.dd.username'),
            "password" => config('services.dd.password'),
        ];

        $response = $fetchDataService->fetchData(config('services.dd.uris.booking') . $this->bookingId, $payload)
            ->getData(true);
        if (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {
            $existingPetIds = Dog::pluck('pet_id')->flip(); // fast lookup
            $specialServiceMap = Service::whereIn('category', config('services.dd.special_service_cats'))
                ->pluck('id', 'dd_id');
            $dogs = collect($response['data'])->groupBy('contactId');
            foreach ($dogs as $petId => $services) {
                if (isset($existingPetIds[$petId])) {
                    foreach ($services as $service) {
                        if ($specialServiceMap->has($service['serviceItem']['id'])) {
                            DogService::updateOrCreate(['pet_id' => $petId,
                                'service_id' => $specialServiceMap[$service['serviceItem']['id']]], [
                                'scheduled_start' => substr($service['startDatetime'], 0, 19),
                                // Not actually UTC because who knows
                            ]);
                        }
                    }
                } else {
                    Log::info('Pet ID not present: ' . $petId);
                }
            }
        }

//        $delay = config('services.dd.queue_delay'); Unnecessary due to enormous data payload size
//        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }
}
