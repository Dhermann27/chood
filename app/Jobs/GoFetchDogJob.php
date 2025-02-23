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
        $url = config('services.dd.uris.card') . $this->petId .
            config('services.dd.uris.cardSuffix');
        $data = $fetchDataService->fetchData($url, $payload)->getData(true)['data'][0];
        Dog::updateOrCreate(['pet_id' => $this->petId], ['photoUri' => $data['photoUrl']]);
        sleep(mt_rand(0, 1000) / 1000);

    }

}
