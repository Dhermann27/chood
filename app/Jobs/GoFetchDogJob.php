<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Services\NodeService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

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
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function handle(NodeService $nodeService): void
    {
        $payload = [
            "username" => config('services.puppeteer.username'),
            "password" => config('services.puppeteer.password'),
        ];
        $url = config('services.puppeteer.uris.card') . $this->petId .
            config('services.puppeteer.uris.cardSuffix');
        $data = $nodeService->fetchData($url, $payload)->getData(true)['data'][0];
        Dog::updateOrCreate(
            ['pet_id' => $this->petId],
            [
                'photoUri' => $data['photoUrl'] // Comes from DD, do not change
            ]
        );
        sleep(mt_rand(0, 2000) / 1000);

    }

}
