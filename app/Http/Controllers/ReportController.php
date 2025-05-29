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
    protected FetchDataService $fetchDataService;

    public function __construct(FetchDataService $fetchDataService)
    {
        $this->fetchDataService = $fetchDataService;
    }

    public function report(): Response
    {
        return Inertia::render('DepositFinder/Report', [
            'sbUser' => config('services.dd.sandbox_username'),
            'sbPass' => config('services.dd.sandbox_password'),
        ]);
    }

    public function overall(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'date' => 'required|date'
        ]);
        try {
            $payload = [
                "username" => $validatedData['username'],
                "password" => $validatedData['password'],
                "dataToPost" => [
                    "timestamp" => time(),
                    "data" => [
                        [
                            "limit" => 50,
                            "order" => [],
                            "criteria" => [
                                [
                                    "key" => "dateFrom",
                                    "operator" => "gte",
                                    "value" => $validatedData['date']
                                ],
                                [
                                    "key" => "dateTo",
                                    "operator" => "lte",
                                    "value" => $validatedData['date']
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $output = $this->fetchDataService->fetchData(config('services.dd.uris.reports.overall'), $payload)
                ->getData(true);

            $data = [];
            foreach ($output['data'][0]['columns'] as $index => $column) {
                if ($column['filterKey'] === 'category') $catColIndex = $index;
                if ($column['filterKey'] === 'count') $qtyColIndex = $index;
                if ($column['filterKey'] === 'total') $totColIndex = $index;
                if ($column['filterKey'] === 'tax') $taxColIndex = $index;
            }

            $index = array_search("Tip", array_column($output['data'][0]['rows'], $catColIndex));
            if ($index !== false) {
                $data['tips'] = [
                    'qty' => $output['data'][0]['rows'][$index][$qtyColIndex],
                    'total' => $output['data'][0]['rows'][$index][$totColIndex]
                ];
            } else {
                $data['tips'] = ['qty' => 0, 'total' => 0];
            }

            $index = array_search("Product", array_column($output['data'][0]['rows'], $catColIndex));
            if ($index !== false) {
                $data['product'] = [
                    'qty' => $output['data'][0]['rows'][$index][$qtyColIndex],
                    'total' => $output['data'][0]['rows'][$index][$totColIndex]
                ];
                $data['tax'] = [
                    'qty' => $output['data'][0]['rows'][$index][$qtyColIndex],
                    'total' => $output['data'][0]['rows'][$index][$taxColIndex],
                ];
            } else {
                $data['product'] = ['qty' => 0, 'total' => 0];
                $data['tax'] = ['qty' => 0, 'total' => 0];
            }

            $report = Report::updateOrCreate(
                ['username' => $validatedData['username'], 'report_date' => $validatedData['date']],
                ['username' => $validatedData['username'], 'report_date' => $validatedData['date'], 'data' => $data]
            );

            GoFetchReports::dispatch($report->id, $validatedData['username']);

            return response()->json($report);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Fetching failed',
                'username' => $validatedData['username'],
                'output' => $e->getMessage(),
            ], 500);
        }
    }

    public function results($reportId): JsonResponse
    {
        $report = Report::findOrFail($reportId);
        $data = $report->data;

        $customOrder = ['Daycare', 'Boarding', 'Enrichment', 'Grooming', 'Training'];

        // Sort services and acc_services (if they exist)
        foreach (['services', 'acc_services'] as $key) {
            if (!empty($data[$key])) {
                $data[$key] = collect($data[$key])
                    ->sortBy(fn($v, $k) => array_search($k, $customOrder) ?? PHP_INT_MAX)
                    ->all();
            }
        }

        // Merge and apply the same sort to combined_services
        $data['combined_services'] = $this->mergeGroups(
            $data['services'] ?? [], $data['acc_services'] ?? [],
        );

        $data['combined_services'] = collect($data['combined_services'])
            ->sortBy(fn($v, $k) => array_search($k, $customOrder) ?? PHP_INT_MAX)
            ->all();

        // Merge packages without custom sort
        $data['combined_packages'] = $this->mergeGroups(
            $data['packages'] ?? [], $data['accrual_packages'] ?? [],
        );

        $report->data = $data;
        return response()->json($report);
    }


    private function mergeGroups(array $primary = [], array $secondary = [], string $prefix1 = 'sold', string $prefix2 = 'used'): array
    {
        $allKeys = collect($primary)->keys()
            ->merge(array_keys($secondary))
            ->unique();

        return $allKeys->mapWithKeys(function ($key) use ($primary, $secondary, $prefix1, $prefix2) {
            return [$key => [
                "{$prefix1}_qty" => $primary[$key]['qty'] ?? 0,
                "{$prefix1}_total" => $primary[$key]['total'] ?? 0,
                "{$prefix2}_qty" => $secondary[$key]['qty'] ?? 0,
                "{$prefix2}_total" => $secondary[$key]['total'] ?? 0,
            ]];
        })->sortKeys()->toArray();
    }

}
