<?php

namespace App\Jobs\Report;

use App\Models\Report;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchPaymentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $reportId, public readonly array $cookies)
    {
        $this->onQueue('high');
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $report = Report::findOrFail($this->reportId);
        $params = $this->buildParams($report->report_date);

        $paymentsRaw = $fetchDataService->postReport(
            config('services.gingr.uris.payments_refunds_raw'),
            $params,
            $this->cookies
        );

        $methodData = $paymentsRaw['payment_method_data'] ?? [];

        $data                     = $report->data ?? [];
        $data['deposits']         = $this->parseDeposits($methodData, $paymentsRaw['payment_method_refund_data'] ?? []);
        $data['cash_transactions'] = $this->parseCashTransactions($paymentsRaw['payment_events'] ?? [], $methodData);

        $report->data       = $data;
        $report->updated_at = now();
        $report->save();
    }

    private function buildParams(string $date): array
    {
        $formatted = \Carbon\Carbon::parse($date)->format('m/d/Y');

        return [
            'date_from'      => $formatted,
            'time_from'      => '00:00:00',
            'date_to'        => $formatted,
            'time_to'        => '23:59:59',
            'payment_method' => range(1, 12),
            'location'       => [config('services.gingr.location_id')],
        ];
    }

    // payment_method_data: { "Cash": { "Payments": {sum, pt_count}, ... }, "Credit Card": {...}, ... }
    private function parseDeposits(array $methodData, array $refundData = []): array
    {
        $exclude     = ['no payment', 'store credit', 'account credit'];
        $cardMethods = config('services.dd.card_types', []);
        $cardLower   = array_map('strtolower', $cardMethods);

        $card    = ['qty' => 0, 'total' => 0.0];
        $nonCard = [];
        $refunds = ['qty' => 0, 'total' => 0.0];

        foreach ($methodData as $method => $types) {
            $lower = strtolower($method);
            if (in_array($lower, $exclude, true)) continue;

            $isCard = in_array($lower, $cardLower, true) || str_contains($lower, 'credit card');
            foreach ($types as $entry) {
                if ($isCard) {
                    $card['qty']   += (int)($entry['pt_count'] ?? 0);
                    $card['total'] += (float)($entry['sum'] ?? 0);
                } else {
                    $nonCard[$method]['qty']   = ($nonCard[$method]['qty'] ?? 0) + (int)($entry['pt_count'] ?? 0);
                    $nonCard[$method]['total'] = ($nonCard[$method]['total'] ?? 0.0) + (float)($entry['sum'] ?? 0);
                }
            }
        }

        foreach ($refundData as $types) {
            foreach ($types as $entry) {
                $refunds['qty']   += (int)($entry['pt_count'] ?? 0);
                $refunds['total'] += (float)($entry['sum'] ?? 0);
            }
        }

        $result = [
            ['label' => 'Overall', 'qty' => $card['qty'], 'total' => round($card['total'], 2)],
        ];
        foreach ($nonCard as $method => $totals) {
            if ($totals['total'] != 0.0 || $totals['qty'] > 0) {
                $result[] = ['label' => $method, 'qty' => $totals['qty'], 'total' => round($totals['total'], 2)];
            }
        }
        if ($refunds['total'] != 0.0) {
            $result[] = ['label' => 'Refunds', 'qty' => $refunds['qty'], 'total' => round($refunds['total'], 2)];
        }

        return $result;
    }

    // payment_events.tbody rows: [invoice_link, owner_link, date, user, amount, tx_id]
    private function parseCashTransactions(array $paymentEvents, array $methodData): array
    {
        $cashIds = [];
        foreach ($methodData as $method => $types) {
            if (!in_array(strtolower($method), ['cash', 'check'], true)) continue;
            foreach ($types as $entry) {
                foreach (explode('.', $entry['pt_ids'] ?? '') as $id) {
                    if ($id = trim($id)) $cashIds[$id] = true;
                }
            }
        }

        $transactions = [];
        foreach ($paymentEvents['tbody'] ?? [] as $row) {
            preg_match('/\/id\/(\d+)/', $row[0] ?? '', $m);
            $invoiceId = $m[1] ?? null;
            if (!$invoiceId || !isset($cashIds[$invoiceId])) continue;

            preg_match('/>([^<]+)<\/a>/', $row[1] ?? '', $m);
            $owner = trim($m[1] ?? '');
            $date  = preg_replace('/^\w+,\s*/', '', $row[2] ?? '');

            $transactions[$invoiceId] = [
                'date'   => $date,
                'owner'  => $owner,
                'amount' => (float)($row[4] ?? 0),
            ];
        }

        return $transactions;
    }
}
