<?php

namespace App\Jobs\Report;

use App\Enums\HousingServiceCodes;
use App\Models\Report;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchBoardingJob implements ShouldQueue
{
    use Queueable;

    private const array BASE_RATES = [
        'BRDC' => 65.00,
        'BRDL' => 100.00,
    ];

    public function __construct(public readonly string $reportId, public readonly array $cookies)
    {
        $this->onQueue('high');
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $report = Report::findOrFail($this->reportId);

        $cookieHeader = collect($this->cookies)->map(fn($v, $k) => "$k=$v")->implode('; ');

        $response = Http::withHeaders([
            'Cookie' => $cookieHeader,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
        ])->get(config('services.gingr.uris.reservation_widget'), [
            'key' => config('services.gingr.widget_key'),
            'timestamp' => $report->report_date,
        ]);

        if (!$response->successful()) {
            Log::error('FetchBoardingJob: widget API failed', ['status' => $response->status()]);
            return;
        }

        [$boardingAccrual, $occupancy] = $this->parseWidget($response->json('data', []));

        $data = $report->data ?? [];
        $data['boarding_accrual'] = $boardingAccrual;
        $data['occupancy'] = $occupancy;
        $report->data = $data;
        $report->updated_at = now();
        $report->save();
    }

    private function parseWidget(array $data): array
    {
        $boardingQty = 0;
        $boardingTotal = 0.0;
        $occupancy = ['daycare_full' => 0, 'daycare_half' => 0, 'interview' => 0];

        foreach ($data as $label => $counts) {
            $lower = strtolower($label);

            if (str_contains($lower, 'boarding')) {
                $active = (int)($counts['active'] ?? 0) - (int)($counts['check_outs'] ?? 0);
                if ($active <= 0) continue;
                $code = str_contains($lower, 'luxury') ? HousingServiceCodes::BRDL->value : HousingServiceCodes::BRDC->value;
                $boardingQty += $active;
                $boardingTotal += self::BASE_RATES[$code] * $active;
            } elseif (str_contains($lower, 'half day')) {
                $occupancy['daycare_half'] += (int)($counts['active'] ?? 0);
            } elseif (str_contains($lower, 'day camp')) {
                $occupancy['daycare_full'] += (int)($counts['active'] ?? 0);
            } elseif (str_contains($lower, 'interview')) {
                $occupancy['interview'] += (int)($counts['active'] ?? 0);
            }
        }

        return [
            ['qty' => $boardingQty, 'total' => round($boardingTotal, 2)],
            $occupancy,
        ];
    }
}
