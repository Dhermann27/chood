<?php

namespace App\Jobs;

use App\Models\Service;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 *
 */
class GoFetchServiceListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FetchDataService $fetchDataService;

    /**
     * Create a new job instance.
     */
    public function __construct(FetchDataService $fetchDataService)
    {
        $this->fetchDataService = $fetchDataService;
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $payload = [
            "username" => config('services.dd.username'),
            "password" => config('services.dd.password'),
        ];
        $output = $this->fetchDataService->fetchData(config('services.dd.uris.servicelist'), $payload)
            ->getData(true);
        if (!isset($output['data']) || !is_array($output['data']) || count($output['data']) == 0) {
            throw new Exception("No data found: " . $output);
        }

        DB::transaction(function () use ($output) {
            $data = $output['data'][0];
            $columns = collect($data['columns'])->pluck('index', 'filterKey');

            // Build rows for upsert (active only)
            $rows = [];
            foreach ($data['rows'] as $row) {
                if (!empty($row[$columns['active']])) {
                    $rows[] = [
                        'dd_id' => trim($row[$columns['id']]),
                        'name' => trim($row[$columns['label']]),
                        'category' => trim($row[$columns['category']]),
                        'code' => trim($row[$columns['code']]),
                        'duration' => (int)trim($row[$columns['duration']]),
                    ];
                }
            }

            if (!empty($rows)) {
                Service::upsert($rows, ['dd_id'], ['name', 'category', 'code', 'duration']);
            }

            $activeDdIds = array_column($rows, 'dd_id');
            if (!empty($activeDdIds)) {
                Service::whereNotIn('dd_id', $activeDdIds)->update(['is_active' => 0]);
            }

            // Refresh cache used by your sync job
            Cache::put('serviceMapByDDID', Service::pluck('id', 'dd_id'), now()->addDay());
        });

    }

}
