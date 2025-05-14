<?php

namespace App\Jobs;

use App\Models\Feeding;
use App\Services\FetchDataService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GoFetchFeedingJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $petId;
    protected string $accountId;


    /**
     * Create a new job instance.
     */
    public function __construct(string $petId, string $accountId)
    {
        $this->petId = $petId;
        $this->accountId = $accountId;
    }

    public function uniqueId()
    {
        return 'feed' . $this->petId . $this->accountId;
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
        $url = preg_replace('/PET_ID/', $this->petId, config('services.dd.uris.feeding'));
        $url = preg_replace('/ACCOUNT_ID/', $this->accountId, $url);

        $response = $fetchDataService->fetchData($url, $payload)->getData(true);

        if (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {
            $newIds = collect($response['data'])->pluck('id')->toArray();
            Feeding::where('pet_id', $this->petId)->whereNotIn('feeding_id', $newIds)->delete();
            foreach ($response['data'] as $feeding) {
                Feeding::updateOrCreate(['feeding_id' => $feeding['id']], [
                    'pet_id' => $this->petId,
                    'type' => $feeding['attributeValue'],
                    'description' => $feeding['description'],
                    'modified_at' => Carbon::parse($feeding['dateModified']),
                ]);
            }
        }

        $delay = config('services.dd.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }
}
