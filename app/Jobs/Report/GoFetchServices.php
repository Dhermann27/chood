<?php

namespace App\Jobs\Report;

use App\Models\Report;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GoFetchServices implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $reportId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $reportId)
    {
        $this->reportId = $reportId;
        $this->onQueue('high');
    }

    public function uniqueId()
    {
        return '3000' . $this->reportId; // 3000 is the Services Report
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $report = Report::findOrFail($this->reportId);
        $payload = $fetchDataService->createPayload($report);
        $data = $report->data;

        $output = $fetchDataService->fetchData(config('services.dd.uris.reports.services'), $payload)
            ->getData(true);;
        foreach ($output['data'][0]['columns'] as $index => $column) {
            if ($column['filterKey'] === 'category') $typeColIndex = $index;
            if ($column['filterKey'] === 'count') $qtyColIndex = $index;
            if ($column['filterKey'] === 'total') $totColIndex = $index;
        }

        $data['services'] = [];
        foreach ($output['data'][0]['rows'] as $row) {
            if (!isset($data['services'][$row[$typeColIndex]])) $data['services'][$row[$typeColIndex]] = ['qty' => 0, 'total' => 0];
            $data['services'][$row[$typeColIndex]]['qty'] += $row[$qtyColIndex];
            $data['services'][$row[$typeColIndex]]['total'] += $row[$totColIndex];
        }

        $report->update(['data' => $data]);

        $delay = config('services.dd.queuedelay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }
}
