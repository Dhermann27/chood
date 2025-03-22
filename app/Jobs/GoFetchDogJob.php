<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoFetchDogJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $petId;


    /**
     * Create a new job instance.
     */
    public function __construct(string $petId)
    {
        $this->petId = $petId;
    }

    public function uniqueId()
    {
        return $this->petId;
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
        $url = preg_replace('/PET_ID/', $this->petId, config('services.dd.uris.card'));
        $response = $fetchDataService->fetchData($url, $payload)->getData(true);

        if (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {
            $data = $response['data'][0];
            Dog::updateOrCreate(['pet_id' => $this->petId], ['photoUri' => $data['photoUrl']]);
        } else {
            Log::warning('Dog data missing: ' . $this->petId);
        }

        $delay = config('services.dd.queuedelay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }

}
