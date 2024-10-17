<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Services\PantherService;
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

    public function uniqueId() {
        return $this->petId;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function handle(PantherService $pantherService): void
    {
        $data = $pantherService->fetchData(config('services.panther.uris.card') . $this->petId .
            config('services.panther.uris.cardSuffix'));
        Dog::updateOrCreate(
            ['id' => $this->petId],
            [
                'photoUri' => $data['photoUrl'], // Comes from DD, do not change
                'size' => $data['size']
            ]
        );

        sleep(4);
    }

}
