<?php

namespace App\Http\Controllers;

use App\Jobs\Report\GoFetchReports;
use App\Models\Report;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private FetchDataService $fetchDataService) {}

    public function report(): Response
    {
        return Inertia::render('DepositFinder/Report', [
            'sbUser' => config('services.gingr.sandbox_username'),
            'sbPass' => config('services.gingr.sandbox_password'),
        ]);
    }

    public function overall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'date'     => 'required|date',
        ]);

        try {
            $cookies = $this->fetchDataService->authenticateUser(
                $validated['username'],
                $validated['password']
            );

            $report = Report::updateOrCreate(
                ['username' => $validated['username'], 'report_date' => $validated['date']],
                ['data' => []]
            );

            GoFetchReports::dispatch($report->id, $cookies);

            return response()->json($report);

        } catch (Exception $e) {
            return response()->json([
                'error'  => 'Fetching failed',
                'output' => $e->getMessage(),
            ], 500);
        }
    }

    public function results($reportId): JsonResponse
    {
        $report = Report::findOrFail($reportId);
        $data   = $report->data ?? [];

        $customOrder = ['Daycare', 'Boarding', 'Enrichment', 'Grooming', 'Training'];

        $services = $data['services'] ?? [];
        $packages = $data['packages'] ?? [];

        // Group packages sold by service category so we can subtract from Services Used
        $pkgByCategory = [];
        foreach ($packages as $name => $entry) {
            $cat = $this->serviceCategory($name);
            if (!$cat) continue;
            $pkgByCategory[$cat] ??= ['qty' => 0, 'total' => 0.0];
            $pkgByCategory[$cat]['qty']   += $entry['qty'] ?? 0;
            $pkgByCategory[$cat]['total'] += (float)($entry['total'] ?? 0);
        }

        // Services Used = Paid - packages sold in that category (packages are deferred service)
        $usedServices = [];
        foreach ($services as $category => $entry) {
            $usedServices[$category] = [
                'qty'   => max(0, ($entry['qty'] ?? 0) - ($pkgByCategory[$category]['qty'] ?? 0)),
                'total' => max(0.0, (float)($entry['total'] ?? 0) - (float)($pkgByCategory[$category]['total'] ?? 0)),
            ];
        }

        // Boarding Used also includes overnight occupancy (not yet charged)
        if (isset($data['boarding_accrual'])) {
            $usedServices['Boarding'] ??= ['qty' => 0, 'total' => 0.0];
            $usedServices['Boarding']['qty']   += $data['boarding_accrual']['qty'];
            $usedServices['Boarding']['total'] += $data['boarding_accrual']['total'];
        }

        $combined = $this->mergeGroups($services, $usedServices);
        $combined['Training'] ??= ['sold_qty' => 0, 'sold_total' => 0, 'used_qty' => 0, 'used_total' => 0];

        $data['combined_services'] = collect($combined)
            ->sortBy(fn($v, $k) => array_search($k, $customOrder) ?? PHP_INT_MAX)
            ->all();

        $data['combined_packages'] = $this->mergeGroups(
            $data['packages'] ?? [], $data['accrual_packages'] ?? []
        );

        // Overall totals — Services rows + Tips
        $tipsTotal = (float)($data['tips']['total'] ?? 0);
        $tipsQty   = (int)($data['tips']['qty'] ?? 0);

        $data['overall_paid']  = [
            'qty'   => array_sum(array_column($data['combined_services'], 'sold_qty')) + $tipsQty,
            'total' => round(array_sum(array_column($data['combined_services'], 'sold_total')) + $tipsTotal, 2),
        ];
        $data['accrual_total'] = [
            'qty'   => array_sum(array_column($data['combined_services'], 'used_qty')) + $tipsQty,
            'total' => round(array_sum(array_column($data['combined_services'], 'used_total')) + $tipsTotal, 2),
        ];

        $report->data = $data;
        return response()->json($report);
    }

    private function serviceCategory(string $name): ?string
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'day care') || str_contains($lower, 'daycare') || str_contains($lower, 'day camp')) return 'Daycare';
        if (str_contains($lower, 'board'))                                                                              return 'Boarding';
        if (str_contains($lower, 'train'))                                                                              return 'Training';
        if (str_contains($lower, 'groom') || str_contains($lower, 'bath') || str_contains($lower, 'nail'))             return 'Grooming';
        if (str_contains($lower, 'enrich'))                                                                             return 'Enrichment';
        return null;
    }

    private function mergeGroups(array $primary = [], array $secondary = [], string $prefix1 = 'sold', string $prefix2 = 'used'): array
    {
        $allKeys = collect($primary)->keys()
            ->merge(array_keys($secondary))
            ->unique();

        return $allKeys->mapWithKeys(function ($key) use ($primary, $secondary, $prefix1, $prefix2) {
            return [$key => [
                "{$prefix1}_qty"   => $primary[$key]['qty'] ?? 0,
                "{$prefix1}_total" => $primary[$key]['total'] ?? 0,
                "{$prefix2}_qty"   => $secondary[$key]['qty'] ?? 0,
                "{$prefix2}_total" => $secondary[$key]['total'] ?? 0,
            ]];
        })->sortKeys()->toArray();
    }
}
