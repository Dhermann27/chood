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
use Illuminate\Support\Facades\Log;

class GoFetchReports implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $reportId, public readonly string $username)
    {
        $this->onQueue('high');
    }


    public function uniqueId(): string
    {
        return $this->username;
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $report = Report::findOrFail($this->reportId);
        $payload = $fetchDataService->createPayload($report);
        $reportData = $report->data ?? [];

        $this->processGroup($fetchDataService, $payload, $reportData, 'deposits', ['paymentType', 'qty', 'total']);
        if (!empty($reportData['deposits']['Cash'])) {
            $reportData['cash'] = $this->processCashDeposits($fetchDataService, $report);
        }

        $this->saveAndSleep($report, $reportData);

        // Step 2: Service/package groups
        $this->processGroup($fetchDataService, $payload, $reportData, 'packages', ['packageName', 'count', 'total']);
        $this->processGroup($fetchDataService, $payload, $reportData, 'accrual_packages', ['packageName', 'count', 'rtTotal']);
        $this->processGroup($fetchDataService, $payload, $reportData, 'services', ['category', 'count', 'total']);

        $payload = $fetchDataService->createConstrainedPayload($report, 'packages', 'eq', '1');
        $this->processGroup($fetchDataService, $payload, $reportData, 'accrual_services', ['categoryName', 'count', 'rtTotal']);

        $reportData['accrual_total'] = $this->computeAccrualTotal($reportData);
        $this->saveAndSleep($report, $reportData);
    }


    /**
     * @throws Exception
     */
    private function processGroup(FetchDataService $service, array $payload, array &$reportData, string $uriKey, array $columns): void
    {
        $output = $service->fetchData(config("services.dd.uris.reports.$uriKey"), $payload)->getData(true);
        $result = $this->sumByKeys($output, $columns);
        $reportData[$uriKey] = $result;
    }


    private function sumByKeys(array $output, array $keys): array
    {
        [$typeKey, $qtyKey, $totalKey] = $keys;
        if (!isset($output['data'][0]['columns']) || !is_array($output['data'][0]['columns'])) {
            Log::warning("Missing or malformed 'data' in report output", ['output' => $output]);
            return [];
        }

        $columns = array_flip(array_column($output['data'][0]['columns'], 'filterKey'));

        $typeIndex = $columns[$typeKey] ?? null;
        $qtyIndex = $columns[$qtyKey] ?? null;
        $totalIndex = $columns[$totalKey] ?? null;

        $groomingLabels = ['Bath', 'Basic Grooming', 'Full Service Grooming'];
        $result = [];
        if ($typeIndex === null || $qtyIndex === null || $totalIndex === null) {
            return [];
        }
        foreach ($output['data'][0]['rows'] ?? [] as $row) {
            if (isset($row[$typeIndex], $row[$qtyIndex], $row[$totalIndex])) {

                $rawKey = $row[$typeIndex];
                $key = in_array($rawKey, $groomingLabels) ? 'Grooming' : $rawKey;

                if (!isset($result[$key])) $result[$key] = ['qty' => 0, 'total' => 0];
                $result[$key]['qty'] += $row[$qtyIndex];
                $result[$key]['total'] += $row[$totalIndex];
            }
        }

        return $result;
    }

    /**
     * @param FetchDataService $service
     * @param Report $report
     * @return array
     * @throws Exception
     */
    private function processCashDeposits(FetchDataService $service, Report $report): array
    {
        $data = [];

            $payload = $service->createConstrainedPayload($report, 'paymentTypeId', 'eq', '1');

            $output = $service->fetchData(config('services.dd.uris.reports.depositDetails'), $payload)->getData(true);

            $columns = array_flip(array_column($output['data'][0]['columns'], 'filterKey'));
            $rows = $output['data'][0]['rows'] ?? [];

            foreach ($rows as $row) {
                $orderId = $row[$columns['orderId']] ?? null;
                if (!$orderId) continue;

                $data[$orderId] = [
                    'date' => substr($row[$columns['paymentDate']] ?? '', 0, 10),
                    'firstName' => $row[$columns['firstName']] ?? null,
                    'lastName' => $row[$columns['lastName']] ?? null,
                    'items' => $row[$columns['items']] ?? null,
                    'amount' => $row[$columns['amount']] ?? null,
                ];
            }

        return $data;
    }

    private function computeAccrualTotal(array $reportData): array
    {
        $sum = [
            'qty' => 0,
            'total' => 0.0,
        ];

        foreach (['accrual_packages', 'accrual_services'] as $key) {
            if (!empty($reportData[$key])) {
                foreach ($reportData[$key] as $entry) {
                    $sum['qty'] += $entry['qty'] ?? 0;
                    $sum['total'] += $entry['total'] ?? 0;
                }
            }
        }

        $sum['total'] = round($sum['total'], 2);
        return $sum;
    }


    private function saveAndSleep(Report $report, array $data): void
    {
        $report->data = $data;
        $report->updated_at = now();
        $report->save();

        usleep(mt_rand(config('services.dd.queue_delay'), config('services.dd.queue_delay') + 1000) * 1000);
    }


}
