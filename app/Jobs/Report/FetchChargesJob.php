<?php

namespace App\Jobs\Report;

use App\Models\Report;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchChargesJob implements ShouldQueue
{
    use Queueable;

    // account_code_charges.tbody row indices
    const ACC_NAME   = 0;
    const ACC_AMOUNT = 2;
    const ACC_QTY    = 4;

    // charges_package / redeemed_package_credits tbody row indices
    const PKG_NAME   = 0;
    const PKG_AMOUNT = 1;
    const PKG_QTY    = 3; // # items (not invoice count)

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

        $chargesRaw = $fetchDataService->postReport(
            config('services.gingr.uris.charges_raw'),
            $params,
            $this->cookies
        );

        $accountCodeCharges = $chargesRaw['account_code_charges'] ?? [];

        $data                     = $report->data ?? [];
        $data['services']         = $this->parseAccountCodeCharges($accountCodeCharges);
        $data['packages']         = $this->parsePackagesSold($chargesRaw['charges_package'] ?? []);
        $data['accrual_packages'] = $this->parseRedeemedCreditsByPackage(
            $chargesRaw['redeemed_package_credits'] ?? [],
            $chargesRaw['redeemed_package_credits_services'] ?? []
        );
        $data['tips']             = $this->parseTips($accountCodeCharges);

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

    // account_code_charges.tbody: [name, parent, amount, refund, invoice_count, ?, ?, code_id, ...]
    private function parseAccountCodeCharges(array $data): array
    {
        $result = [];
        foreach ($data['tbody'] ?? [] as $row) {
            $category = $this->categoryFromAccountCode($row[self::ACC_NAME] ?? '');
            if (!$category) continue;

            $result[$category] ??= ['qty' => 0, 'total' => 0.0];
            $result[$category]['qty']   += (int)($row[self::ACC_QTY] ?? 0);
            $result[$category]['total'] += (float)($row[self::ACC_AMOUNT] ?? 0);
        }

        return $result;
    }

    private function categoryFromAccountCode(string $name): ?string
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'day care') || str_contains($lower, 'daycare') || str_contains($lower, 'day camp')) return 'Daycare';
        if (str_contains($lower, 'board'))                                                                              return 'Boarding';
        if (str_contains($lower, 'train'))                                                                              return 'Training';
        if (str_contains($lower, 'groom') || str_contains($lower, 'bath') || str_contains($lower, 'nail'))             return 'Grooming';
        if (str_contains($lower, 'enrich'))                                                                             return 'Enrichment';
        return null;
    }

    // charges_package.tbody: [name, amount, invoice_count, item_count, ...]
    private function parsePackagesSold(array $data): array
    {
        $result = [];
        foreach ($data['tbody'] ?? [] as $row) {
            $name = $row[self::PKG_NAME] ?? 'Unknown Package';
            $result[$name] ??= ['qty' => 0, 'total' => 0.0];
            $result[$name]['qty']   += (int)($row[self::PKG_QTY] ?? 0);
            $result[$name]['total'] += (float)($row[self::PKG_AMOUNT] ?? 0);
        }

        return $result;
    }

    private function parseRedeemedCreditsByPackage(array $credits, array $serviceCredits): array
    {
        $result = [];
        foreach (array_merge($credits['tbody'] ?? [], $serviceCredits['tbody'] ?? []) as $row) {
            $name = $row[self::PKG_NAME] ?? 'Unknown Package';
            $result[$name] ??= ['qty' => 0, 'total' => 0.0];
            $result[$name]['qty']   += (int)($row[self::PKG_QTY] ?? 0);
            $result[$name]['total'] += abs((float)($row[self::PKG_AMOUNT] ?? 0));
        }

        return $result;
    }

    private function parseTips(array $accountCodeCharges): array
    {
        $qty   = 0;
        $total = 0.0;

        foreach ($accountCodeCharges['tbody'] ?? [] as $row) {
            if (str_contains(strtolower($row[self::ACC_NAME] ?? ''), 'tip')) {
                $qty   += (int)($row[self::ACC_QTY] ?? 0);
                $total += (float)($row[self::ACC_AMOUNT] ?? 0);
            }
        }

        return ['qty' => $qty, 'total' => round($total, 2)];
    }
}
