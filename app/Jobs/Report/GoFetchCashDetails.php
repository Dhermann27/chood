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

class GoFetchCashDetails implements ShouldQueue, ShouldBeUnique
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
        return '1001' . $this->reportId; // 1001 is the Deposit Details Report
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $report = Report::findOrFail($this->reportId);
        $payload = $fetchDataService->createConstrainedPayload($report, 'paymentTypeId', 'eq', '1');
        $data = $report->data;
        $data['cash'] = [];

        $output = $fetchDataService->fetchData(config('services.dd.uris.reports.depositDetails'), $payload)
            ->getData(true);;
        foreach ($output['data'][0]['columns'] as $index => $column) {
            if ($column['filterKey'] === 'paymentDate') $dateColIndex = $index;
            if ($column['filterKey'] === 'orderId') $orderIdColIndex = $index;
            if ($column['filterKey'] === 'firstName') $firstNameColIndex = $index;
            if ($column['filterKey'] === 'lastName') $lastNameColIndex = $index;
            if ($column['filterKey'] === 'items') $itemsColIndex = $index;
            if ($column['filterKey'] === 'amount') $amountColIndex = $index;
        }

        foreach ($output['data'][0]['rows'] as $row) {
            if (!isset($data['cash'][$row[$orderIdColIndex]])) {
                $data['cash'][$row[$orderIdColIndex]] = [];
            }
            $data['cash'][$row[$orderIdColIndex]]['date'] = $row[$dateColIndex] . substr(0, 0, 10);
            $data['cash'][$row[$orderIdColIndex]]['firstName'] = $row[$firstNameColIndex];
            $data['cash'][$row[$orderIdColIndex]]['lastName'] = $row[$lastNameColIndex];
            $data['cash'][$row[$orderIdColIndex]]['items'] = $row[$itemsColIndex];
            $data['cash'][$row[$orderIdColIndex]]['amount'] = $row[$amountColIndex];
        }

        $report->update(['data' => $data]);

        $delay = config('services.dd.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }
}
