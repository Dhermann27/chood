<?php

namespace App\Jobs\Report;

use App\Models\Report;
use App\Services\FetchDataService;
use App\Traits\BuildsReportParams;
use App\Traits\ParsesHtmlReport;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchOccupancyJob implements ShouldQueue
{
    use Queueable, BuildsReportParams, ParsesHtmlReport;

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
            config('services.gingr.uris.occupancy'),
            $this->buildOccupancyParams($report->report_date),
            $this->cookies
        );

        $data = $report->data ?? [];
        $data['occupancy'] = $this->parseOccupancy($html);
        $report->data = $data;
        $report->updated_at = now();
        $report->save();
    }

    private function parseOccupancy(string $html): array
    {
        $counts = [
            'daycare_full' => 0,
            'daycare_half' => 0,
            'interview' => 0,
            'grooming' => 0,
        ];

        $xpath = $this->loadXPath($html);
        $rows = $xpath->query('//table[@id="reservations"]/tbody/tr');
        if (!$rows) return $counts;

        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 2) continue;

            $type = preg_replace('/\s+/', ' ', trim($cells->item(0)->textContent ?? ''));
            if (strtolower($type) === 'totals') continue;

            $countSpan = $xpath->query('.//span[@class="number-reservations"]', $cells->item(1))->item(0);
            if (!$countSpan) continue;
            $spanText = preg_replace('/\s+/', ' ', trim($countSpan->textContent ?? ''));
            $count = preg_match('/^(\d+)/', $spanText, $m) ? (int)$m[1] : 0;
            if ($count === 0) continue;

            $lower = strtolower($type);
            if (str_contains($lower, 'boarding') || str_contains($lower, 'lodging transfer')) {
                continue;
            } elseif (str_contains($lower, 'day camp') && str_contains($lower, 'half')) {
                $counts['daycare_half'] += $count;
            } elseif (str_contains($lower, 'day camp')) {
                $counts['daycare_full'] += $count;
            } elseif (str_contains($lower, 'interview')) {
                $counts['interview'] += $count;
            } elseif (str_contains($lower, 'groom')) {
                $counts['grooming'] += $count;
            }
        }

        return $counts;
    }
}
