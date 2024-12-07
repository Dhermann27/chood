<?php

namespace App\Jobs;

use App\Http\Controllers\NodeController;
use App\Models\Dog;
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

    public $petId;

    /**
     * Create a new job instance.
     */
    public function __construct($petId)
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
     */
    public function handle(NodeController $nodeController): void
    {
        $data = $nodeController->fetchData(config('services.puppeteer.uris.card') . $this->petId .
            config('services.puppeteer.uris.cardSuffix'))->getData(true)['data'][0];
        Dog::updateOrCreate(
            ['id' => $this->petId],
            [
                'photoUri' => $data['photoUrl'], // Comes from DD, do not change
                'size' => $data['size']
            ]
        );

    }

}
