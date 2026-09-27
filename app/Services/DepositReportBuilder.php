<?php

namespace App\Services;

use App\Traits\ParsesServiceCategory;

class DepositReportBuilder
{
    use ParsesServiceCategory;

    private const array CUSTOM_ORDER = ['Daycare', 'Boarding', 'Enrichment', 'Grooming', 'Training'];
    private const array ORIENTATION_PKG_NAMES = ['Bath', 'Enrichment', 'Full Day Camp'];
    private const float ORIENTATION_PRICE = 99.0;

    public function build(array $data): array
    {
        $services = $data['services'] ?? [];
        $packages = $data['packages'] ?? [];

        // Extract orientations before grouping so their components don't affect category accrual math
        [$packages, $accrualPackages, $orientationQty] = $this->extractOrientations($packages, $data['accrual_packages'] ?? []);

        $pkgByCategory = $this->groupByCategory($packages);
        $accrualPkgByCategory = $this->groupByCategory($accrualPackages);

        // Cash charges minus packages sold (deferred) plus redemptions (deferred revenue earned)
        $usedServices = [];
        $allCategories = array_unique(array_merge(array_keys($services), array_keys($pkgByCategory), array_keys($accrualPkgByCategory)));
        foreach ($allCategories as $category) {
            $usedServices[$category] = [
                'qty' => max(0, ($services[$category]['qty'] ?? 0) - ($pkgByCategory[$category]['qty'] ?? 0) + ($accrualPkgByCategory[$category]['qty'] ?? 0)),
                'total' => max(0.0, (float)($services[$category]['total'] ?? 0) - (float)($pkgByCategory[$category]['total'] ?? 0) + (float)($accrualPkgByCategory[$category]['total'] ?? 0)),
            ];
        }

        // Boarding Used = tonight's boarders at nightly rate, not today's paid charges
        if (isset($data['boarding_accrual'])) {
            $usedServices['Boarding'] = [
                'qty' => $data['boarding_accrual']['qty'],
                'total' => $data['boarding_accrual']['total'],
            ];
        }

        // Daycare Used = occupancy headcount (full + half + interview); amount = package redemptions only
        if (isset($data['occupancy'])) {
            $occ = $data['occupancy'];
            $usedServices['Daycare'] = [
                'qty' => ($occ['daycare_full'] ?? 0) + ($occ['daycare_half'] ?? 0) + ($occ['interview'] ?? 0),
                'total' => round((float)($accrualPkgByCategory['Daycare']['total'] ?? 0), 2),
            ];
        }

        $combined = $this->mergeGroups($services, $usedServices);
        foreach (self::CUSTOM_ORDER as $cat) {
            $combined[$cat] ??= ['sold_qty' => 0, 'sold_total' => 0, 'used_qty' => 0, 'used_total' => 0];
        }

        $data['combined_services'] = collect($combined)
            ->sortBy(fn($v, $k) => array_search($k, self::CUSTOM_ORDER) ?? PHP_INT_MAX)
            ->all();

        $data['orientations'] = [
            'pkg_qty' => $orientationQty,
            'pkg_total' => round($orientationQty * self::ORIENTATION_PRICE, 2),
        ];

        $data['combined_packages'] = $this->mergeGroups($packages, $accrualPackages);

        if (isset($data['occupancy'], $data['boarding_accrual'])) {
            $data['occupancy']['boarding'] = $data['boarding_accrual']['qty'];
            $data['occupancy']['grooming'] = (int)($services['Grooming']['qty'] ?? 0);
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

    private function extractOrientations(array $packages, array $accrualPackages): array
    {
        $qty = 0;
        foreach (array_keys($packages) as $name) {
            if (in_array(trim(preg_replace('/\s*@.*$/i', '', $name)), self::ORIENTATION_PKG_NAMES)) {
                $qty = max($qty, $packages[$name]['qty'] ?? 0);
                unset($packages[$name]);
            }
        }
        foreach (array_keys($accrualPackages) as $name) {
            if (in_array(trim(preg_replace('/\s*@.*$/i', '', $name)), self::ORIENTATION_PKG_NAMES)) {
                unset($accrualPackages[$name]);
            }
        }
        return [$packages, $accrualPackages, $qty];
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
