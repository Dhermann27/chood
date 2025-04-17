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
        $data = $output['data'][0];
        $columns = collect($data['columns'])->pluck('index', 'filterKey');

        $services = collect($data['rows'])->pluck($columns['id'])->toArray();
        Service::whereNotIn('dd_id', $services)->update(['is_active' => '0']);

        foreach ($data['rows'] as $row) {
            if ($row[$columns['active']]) {
                Service::updateOrCreate(['dd_id' => trim($row[$columns['id']])], [
                    'name' => trim($row[$columns['label']]),
                    'category' => trim($row[$columns['category']]),
                    'code' => trim($row[$columns['code']]),
                    'duration' => trim($row[$columns['duration']])
                ]);
            }
        }
    }

}
