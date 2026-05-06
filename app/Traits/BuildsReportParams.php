<?php

namespace App\Traits;

use Carbon\Carbon;

trait BuildsReportParams
{
    private function buildParams(string $date): array
    {
        $formatted = Carbon::parse($date)->format('m/d/Y');

        return [
            'date_from' => $formatted,
            'time_from' => '00:00:00',
            'date_to' => $formatted,
            'time_to' => '23:59:59',
            'payment_method' => range(1, 12),
            'location' => [config('services.gingr.location_id')],
        ];
    }
}
