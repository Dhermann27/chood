<?php

namespace App\Jobs\Report;

use App\Enums\HousingServiceCodes;
use App\Models\Report;
use App\Services\FetchDataService;
use App\Traits\BuildsReportParams;
use App\Traits\ParsesHtmlReport;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchBoardingJob implements ShouldQueue
{
    use Queueable, BuildsReportParams, ParsesHtmlReport;

    private const array BASE_RATES = [
        'BRDC' => 65.00,
        'BRDL' => 100.00,
    ];

    // Per-dog discount off base rate when multiple dogs share a cabin
    private const array MULTI_DOG_DISCOUNTS = [
        'BRDC' => [2 => 4, 3 => 7],
        'BRDL' => [2 => 5, 3 => 10],
    ];

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

        $html = $fetchDataService->fetchOccupancy(
            config('services.gingr.uris.lodging_occupancy'),
            $this->buildOccupancyParams($report->report_date),
            $this->cookies
        );

        [$qty, $total] = $this->parseOccupancy($html);

        $data = $report->data ?? [];
        $data['boarding_accrual'] = ['qty' => $qty, 'total' => round($total, 2)];
        $report->data = $data;
        $report->updated_at = now();
        $report->save();
    }

    private function parseOccupancy(string $html): array
    {
        $qty = 0;
        $total = 0.0;

        $xpath = $this->loadXPath($html);
        $rows = $xpath->query('//table[@id="reservations"]/tbody/tr');
        if (!$rows) return [$qty, $total];

        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 3) continue;

            $lodging = trim($cells->item(0)->textContent ?? '');
            if (strtolower($lodging) === 'totals') continue;

            $area = trim($cells->item(1)->textContent ?? '');
            $isLuxury = str_contains(strtolower($area), 'luxury');
            $code = $isLuxury ? HousingServiceCodes::BRDL->value : HousingServiceCodes::BRDC->value;
            $base = self::BASE_RATES[$code];

            // Date column is index 2 — first number in the span text is the dog count
            $dateCell = $cells->item(2);
            $countSpan = $xpath->query('.//span[@class="number-reservations"]', $dateCell)->item(0);
            if (!$countSpan) continue;

            $spanText = preg_replace('/\s+/', ' ', trim($countSpan->textContent ?? ''));
            $count = preg_match('/^(\d+)/', $spanText, $m) ? (int)$m[1] : 0;
            if ($count === 0) continue;

            $discounts = self::MULTI_DOG_DISCOUNTS[$code];
            $discount = $discounts[min($count, max(array_keys($discounts)))] ?? 0;
            $rate = $base - $discount;

            $qty += $count;
            $total += $rate * $count;
        }

        return [$qty, $total];
    }
}
