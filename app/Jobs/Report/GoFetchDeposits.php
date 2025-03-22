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

class GoFetchDeposits implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const CASH = 'Cash';
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
        return '1000' . $this->reportId; // 1000 is the Deposits Report
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
        $data['deposits'] = [];

        $output = $fetchDataService->fetchData(config('services.dd.uris.reports.deposits'), $payload)
            ->getData(true);;
        foreach ($output['data'][0]['columns'] as $index => $column) {
            if ($column['filterKey'] === 'paymentType') $typeColIndex = $index;
            if ($column['filterKey'] === 'qty') $qtyColIndex = $index;
            if ($column['filterKey'] === 'total') $totColIndex = $index;
        }

        foreach ($output['data'][0]['rows'] as $row) {
            if (!isset($data['deposits'][$row[$typeColIndex]])) {
                $data['deposits'][$row[$typeColIndex]] = ['qty' => 0, 'total' => 0];
                if ($row[$typeColIndex] == self::CASH) GoFetchCashDetails::dispatch($this->reportId);
            }
            $data['deposits'][$row[$typeColIndex]]['qty'] += $row[$qtyColIndex];
            $data['deposits'][$row[$typeColIndex]]['total'] += $row[$totColIndex];
        }

        $report->update(['data' => $data]);

        $delay = config('services.dd.queuedelay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }
}
