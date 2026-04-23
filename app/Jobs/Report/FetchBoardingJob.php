<?php

namespace App\Jobs\Report;

use App\Enums\HousingServiceCodes;
use App\Models\Report;
use App\Services\FetchDataService;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchBoardingJob implements ShouldQueue
{
    use Queueable;

    private const BASE_RATES = [
        'BRDC' => 65.00,
        'BRDL' => 100.00,
    ];

    // Per-dog discount off base rate when multiple dogs share a cabin
    private const MULTI_DOG_DISCOUNTS = [
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
        $report    = Report::findOrFail($this->reportId);
        $formatted = \Carbon\Carbon::parse($report->report_date)->format('m/d/Y');

        $html = $fetchDataService->fetchOccupancy(
            config('services.gingr.uris.lodging_occupancy'),
            [
                'date_from'   => $formatted,
                'date_to'     => $formatted,
                'location_id' => config('services.gingr.location_id'),
                'csv'         => 'false',
            ],
            $this->cookies
        );

        [$qty, $total, $breakdown] = $this->parseOccupancy($html);

        $data = $report->data ?? [];

        $data['boarding_accrual'] = ['qty' => $qty, 'total' => round($total, 2)];

        $report->data       = $data;
        $report->updated_at = now();
        $report->save();
    }

    private function parseOccupancy(string $html): array
    {
        $qty       = 0;
        $total     = 0.0;
        $breakdown = [];

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//table[@id="reservations"]/tbody/tr');
        if (!$rows) return [$qty, $total, $breakdown];

        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 3) continue;

            $area  = trim($cells->item(1)->textContent ?? '');
            $code  = str_contains(strtolower($area), 'luxury') ? HousingServiceCodes::BRDL->value : HousingServiceCodes::BRDC->value;
            $base  = self::BASE_RATES[$code];

            // Date column is index 2 — extract count from number-reservations span direct text
            $dateCell  = $cells->item(2);
            $countSpan = $xpath->query('.//span[@class="number-reservations"]', $dateCell)->item(0);
            if (!$countSpan) continue;

            $count = 0;
            foreach ($countSpan->childNodes as $node) {
                if ($node->nodeType === XML_TEXT_NODE) {
                    $count = (int)trim($node->textContent);
                    break;
                }
            }
            if ($count === 0) continue;

            $discounts = self::MULTI_DOG_DISCOUNTS[$code];
            $discount  = $discounts[min($count, max(array_keys($discounts)))] ?? 0;
            $rate      = $base - $discount;
            $cabinTotal = $rate * $count;

            $lodging   = trim($cells->item(0)->textContent ?? '');
            $qty      += $count;
            $total    += $cabinTotal;
            $breakdown[] = [
                'label' => 'Boarding: ' . $lodging . ($count > 1 ? " ({$count} dogs)" : ''),
                'qty'   => $count,
                'total' => $cabinTotal,
            ];
        }

        return [$qty, $total, $breakdown];
    }
}
