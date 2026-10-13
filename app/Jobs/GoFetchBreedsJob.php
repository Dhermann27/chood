<?php

namespace App\Jobs;

use App\Models\Breed;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoFetchBreedsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FetchDataService $fetchDataService): void
    {
        try {
            $output = $fetchDataService->fetchWithSession(config('services.gingr.uris.breeds'));
        } catch (Exception $e) {
            Log::warning('GoFetchBreedsJob failed: ' . $e->getMessage());
            return;
        }

        $count = 0;

        foreach ($output as $row) {
            $id = (string)($row['value'] ?? '');
            $name = $row['label'] ?? null;
            if (!$id || !$name) continue;

            Breed::updateOrCreate(
                ['gingr_breed_id' => $id],
                ['name' => $name]
            );
            $count++;
        }

        Log::info("GoFetchBreedsJob: synced $count breeds.");
    }
}
