<?php

namespace App\Services;

use App\Traits\ParsesServiceCategory;

class DepositReportBuilder
{
    use ParsesServiceCategory;

    private const array CUSTOM_ORDER = ['Daycare', 'Boarding', 'Enrichment', 'Grooming', 'Training'];

    public function build(array $data): array
    {
        $services = $data['services'] ?? [];
        $packages = $data['packages'] ?? [];

        $pkgByCategory = $this->groupByCategory($packages);
        $accrualPkgByCategory = $this->groupByCategory($data['accrual_packages'] ?? []);

        // Cash charges minus packages sold (deferred) plus redemptions (deferred revenue earned)
        $usedServices = [];
        foreach ($services as $category => $entry) {
            $usedServices[$category] = [
                'qty' => max(0, ($entry['qty'] ?? 0) - ($pkgByCategory[$category]['qty'] ?? 0) + ($accrualPkgByCategory[$category]['qty'] ?? 0)),
                'total' => max(0.0, (float)($entry['total'] ?? 0) - (float)($pkgByCategory[$category]['total'] ?? 0) + (float)($accrualPkgByCategory[$category]['total'] ?? 0)),
            ];
        }

        // Boarding Used = tonight's boarders at nightly rate, not today's paid charges
        if (isset($data['boarding_accrual'])) {
            $usedServices['Boarding'] = [
                'qty' => $data['boarding_accrual']['qty'],
                'total' => $data['boarding_accrual']['total'],
            ];
        }

        $combined = $this->mergeGroups($services, $usedServices);
        foreach (self::CUSTOM_ORDER as $cat) {
            $combined[$cat] ??= ['sold_qty' => 0, 'sold_total' => 0, 'used_qty' => 0, 'used_total' => 0];
        }

        $data['combined_services'] = collect($combined)
            ->sortBy(fn($v, $k) => array_search($k, self::CUSTOM_ORDER) ?? PHP_INT_MAX)
            ->all();

        $data['combined_packages'] = $this->mergeGroups($packages, $data['accrual_packages'] ?? []);

        if (isset($data['occupancy'], $data['boarding_accrual'])) {
            $data['occupancy']['boarding'] = $data['boarding_accrual']['qty'];
            $data['occupancy']['total'] = array_sum($data['occupancy']);
        }

        $tipsTotal = (float)($data['tips']['total'] ?? 0);
        $tipsQty = (int)($data['tips']['qty'] ?? 0);

        $data['overall_paid'] = [
            'qty' => array_sum(array_column($data['combined_services'], 'sold_qty')) + $tipsQty,
            'total' => round(array_sum(array_column($data['combined_services'], 'sold_total')) + $tipsTotal, 2),
        ];
        $data['accrual_total'] = [
            'qty' => array_sum(array_column($data['combined_services'], 'used_qty')) + $tipsQty,
            'total' => round(array_sum(array_column($data['combined_services'], 'used_total')) + $tipsTotal, 2),
        ];

        $data['complete'] = isset($data['boarding_accrual'], $data['occupancy']);

        return $data;
    }

    private function groupByCategory(array $items): array
    {
        $grouped = [];
        foreach ($items as $name => $entry) {
            $cat = $this->serviceCategory($name);
            if (!$cat) continue;
            $grouped[$cat] ??= ['qty' => 0, 'total' => 0.0];
            $grouped[$cat]['qty'] += $entry['qty'] ?? 0;
            $grouped[$cat]['total'] += (float)($entry['total'] ?? 0);
        }
        return $grouped;
    }

    private function mergeGroups(array $primary, array $secondary): array
    {
        $allKeys = collect($primary)->keys()->merge(array_keys($secondary))->unique();

        return $allKeys->mapWithKeys(fn($key) => [$key => [
            'sold_qty' => $primary[$key]['qty'] ?? 0,
            'sold_total' => $primary[$key]['total'] ?? 0,
            'used_qty' => $secondary[$key]['qty'] ?? 0,
            'used_total' => $secondary[$key]['total'] ?? 0,
        ]])->sortKeys()->toArray();
    }
}
