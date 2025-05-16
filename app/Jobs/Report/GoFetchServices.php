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
            ->getData(true);
        $columnMap = array_flip(array_column($output['data'][0]['columns'], 'filterKey'));
        $typeColIndex = $columnMap['category'] ?? null;
        $qtyColIndex = $columnMap['count'] ?? null;
        $totColIndex = $columnMap['total'] ?? null;

        $data['services'] = [];
        $groomingLabels = ['Bath', 'Basic Grooming', 'Full Service Grooming'];
        foreach ($output['data'][0]['rows'] as $row) {
            $key = in_array($row[$typeColIndex], $groomingLabels) ? 'Grooming' : $row[$typeColIndex];

            if (!isset($data['services'][$key])) {
                $data['services'][$key] = ['qty' => 0, 'total' => 0];
            }
            $data['services'][$key]['qty'] += $row[$qtyColIndex];
            $data['services'][$key]['total'] += $row[$totColIndex];
        }

        $customOrder = ['Daycare', 'Boarding', 'Enrichment', 'Grooming', 'Training'];

        $data['services'] = collect($data['services'])->sortBy(function ($value, $key) use ($customOrder) {
                $index = array_search($key, $customOrder);
                return $index !== false ? $index : (count($customOrder) + 0.001) . $key;})->all();

        $report->update(['data' => $data]);

        $delay = config('services.dd.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }
}
