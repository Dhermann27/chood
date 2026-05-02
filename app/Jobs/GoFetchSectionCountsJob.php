<?php

namespace App\Jobs;

use App\Services\FetchDataService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoFetchSectionCountsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('high');
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $response = $fetchDataService->fetchWithSession('sectionCounts');

        if (empty($response['success']) || empty($response['data'])) {
            Log::warning('GoFetchSectionCountsJob: unexpected response', ['response' => $response]);
            return;
        }

        Cache::put('section_counts', [
            'checkin_today' => (int)($response['data']['expected_today'] ?? 0),
            'checkout_today' => (int)($response['data']['going_home_today'] ?? 0),
        ], now()->endOfDay());
    }
}
