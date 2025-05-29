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

class GoFetchReports implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $reportId,
        public readonly string $username
    )
    {
    }


    public function uniqueId(): string
    {
        return $this->username;
    }

    public function handle(FetchDataService $fetchDataService): void
    {
        $report = Report::findOrFail($this->reportId);
        $payload = $fetchDataService->createPayload($report);
        $data = $report->data;

        $this->processGroup($fetchDataService, $payload, $report, 'deposits', ['paymentType', 'count', 'total'], 'deposits');

        $cash = $this->processCashDeposits($fetchDataService, $report);
        $report->data = array_merge($report->data, ['cash' => $cash]);
        $report->updated_at = now(); // force polling-visible timestamp change
        $report->save();
        usleep(mt_rand(config('services.dd.queue_delay'), config('services.dd.queue_delay') + 1000) * 1000);

        $this->processGroup($fetchDataService, $payload, $report, 'accrual_packages', ['packageName', 'count', 'total'], 'acc_packages');
        $this->processGroup($fetchDataService, $payload, $report, 'accrual_services', ['category', 'count', 'total'], 'acc_services');
        $this->processGroup($fetchDataService, $payload, $report, 'packages', ['packageName', 'count', 'total'], 'packages');
        $this->processGroup($fetchDataService, $payload, $report, 'services', ['category', 'count', 'total'], 'services');


        $report->update(['data' => array_merge($report->data, $data)]);
    }

    private function processGroup(FetchDataService $service, array $payload, Report $report, string $uriKey, array $columns, string $dataKey): void
    {
        $output = $service->fetchData(config("services.dd.uris.reports.$uriKey"), $payload)->getData(true);
        $result = $this->sumByKeys($output, $columns);
        $report->data = array_merge($report->data, [$dataKey => $result]);
        $report->updated_at = now(); // force polling-visible timestamp change
        $report->save();

        usleep(mt_rand(config('services.dd.queue_delay'), config('services.dd.queue_delay') + 1000) * 1000);
    }


    private function sumByKeys(array $output, array $keys): array
    {
        [$typeKey, $qtyKey, $totalKey] = $keys;
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
     * @throws Exception
     */
    private function processCashDeposits(FetchDataService $service, Report $report): array
    {
        $data = [];

        if (!empty($report->data['deposits']['Cash'])) {
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
        }

        return $data;
    }


}
