<?php

namespace App\Http\Controllers;

use App\Enums\HousingServiceCodes;
use App\Enums\ReportCategory;
use App\Jobs\Report\GoFetchReports;
use App\Models\Dog;
use App\Models\Report;
use App\Models\Service;
use App\Services\FetchDataService;
use App\Services\JournalEntryTransformer;
use App\Traits\ParsesServiceCategory;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use ParsesServiceCategory;

    public function __construct(
        private FetchDataService        $fetchDataService,
        private JournalEntryTransformer $journalTransformer,
    )
    {
    }

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
            'date' => 'required|date',
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
                'error' => 'Fetching failed',
                'output' => $e->getMessage(),
            ], 500);
        }
    }

    public function results($reportId): JsonResponse
    {
        $report = Report::findOrFail($reportId);
        $data = $report->data ?? [];

        $customOrder = ['Daycare', 'Boarding', 'Enrichment', 'Grooming', 'Training'];

        $services = $data['services'] ?? [];
        $packages = $data['packages'] ?? [];

        // Group packages sold by service category so we can subtract from Services Used
        $pkgByCategory = [];
        foreach ($packages as $name => $entry) {
            $cat = $this->serviceCategory($name);
            if (!$cat) continue;
            $pkgByCategory[$cat] ??= ['qty' => 0, 'total' => 0.0];
            $pkgByCategory[$cat]['qty'] += $entry['qty'] ?? 0;
            $pkgByCategory[$cat]['total'] += (float)($entry['total'] ?? 0);
        }

        // Services Used = Paid - packages sold in that category (packages are deferred service)
        $usedServices = [];
        foreach ($services as $category => $entry) {
            $usedServices[$category] = [
                'qty' => max(0, ($entry['qty'] ?? 0) - ($pkgByCategory[$category]['qty'] ?? 0)),
                'total' => max(0.0, (float)($entry['total'] ?? 0) - (float)($pkgByCategory[$category]['total'] ?? 0)),
            ];
        }

        // Boarding Used also includes overnight occupancy (not yet charged)
        if (isset($data['boarding_accrual'])) {
            $usedServices['Boarding'] ??= ['qty' => 0, 'total' => 0.0];
            $usedServices['Boarding']['qty'] += $data['boarding_accrual']['qty'];
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
        $tipsQty = (int)($data['tips']['qty'] ?? 0);

        $data['overall_paid'] = [
            'qty' => array_sum(array_column($data['combined_services'], 'sold_qty')) + $tipsQty,
            'total' => round(array_sum(array_column($data['combined_services'], 'sold_total')) + $tipsTotal, 2),
        ];
        $data['accrual_total'] = [
            'qty' => array_sum(array_column($data['combined_services'], 'used_qty')) + $tipsQty,
            'total' => round(array_sum(array_column($data['combined_services'], 'used_total')) + $tipsTotal, 2),
        ];

        $report->data = $data;
        return response()->json($report);
    }

    public function dailyReports(string $date = null): Response
    {
        $today = $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
        $serviceIds = Service::where('is_active', true)->whereNotNull('gingr_id')->pluck('gingr_id')->toArray();

        $rows = [];
        if (!empty($serviceIds)) {
            try {
                $html = $this->fetchDataService->fetchServicesByDate($today->format('m/d/Y'), $serviceIds);
                $rows = $this->parseServicesByDate($html);
            } catch (Exception $e) {
                Log::warning('dailyReports: fetch failed: ' . $e->getMessage());
            }
        }

        $petIds = collect($rows)->pluck('pet_id')->unique();
        $dogs = Dog::whereIn('pet_id', $petIds)->with('allergies', 'cabin')->get()->keyBy('pet_id');

        $buildEntry = function (array $row) use ($dogs): array {
            $dog = $dogs->get($row['pet_id']);
            return [
                'pet_id' => $row['pet_id'],
                'display_name' => trim(preg_replace('/\s*\(.+\)\s*$/', '', $dog?->display_name ?? $row['pet_name'])),
                'breed' => $row['breed'],
                'gender' => $dog?->gender,
                'weight' => $dog?->weight,
                'size_letter' => $dog?->size_letter,
                'cabin' => $dog?->cabin,
                'allergies' => $dog?->allergies ?? collect(),
                'report_category' => ReportCategory::fromServiceName($row['service_name'])->value,
                'service_name' => $row['service_name'],
                'assigned_to' => $row['assigned_to'],
                'scheduled_start' => $row['scheduled_start'],
            ];
        };

        $entries = collect($rows)->map($buildEntry);

        $sortByTimeThenName = fn($col) => $col
            ->sortBy('display_name')
            ->sortBy(fn($e) => $e['scheduled_start'] ?? '9999-99-99')
            ->values();

        $interviews = Dog::with('allergies')
            ->where('housing_code', HousingServiceCodes::INTV->value)
            ->whereDate('checkin', $today)
            ->get();

        return Inertia::render('DailyReports', [
            'date' => $today->format('l, F j, Y'),
            'fsg' => $sortByTimeThenName($entries->filter(fn($e) => $e['report_category'] === ReportCategory::FSG->value)),
            'enrichment' => $sortByTimeThenName($entries->filter(fn($e) => $e['report_category'] === ReportCategory::Enrichment->value)),
            'bath' => $sortByTimeThenName($entries->filter(fn($e) => in_array($e['report_category'], [ReportCategory::Bath->value, ReportCategory::Nail->value]))),
            'interviews' => $interviews->sortBy('display_name')->sortBy('checkin')->values(),
        ]);
    }

    private function parseServicesByDate(string $html): array
    {
        $services = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//tr[.//*[contains(@class,"completeCheck") and @data-id]]');

        foreach ($rows as $row) {
            $checkNode = $xpath->query('.//*[contains(@class,"completeCheck") and @data-id]', $row)->item(0);
            $serviceId = (int)($checkNode?->getAttribute('data-id') ?? 0);
            if (!$serviceId) continue;

            $tds = $xpath->query('./td', $row);
            if ($tds->length < 12) continue;

            $link = $xpath->query('.//a[contains(@href,"/animals/view/id/")]', $tds->item(3))->item(0);
            if (!$link) continue;
            preg_match('~/animals/view/id/(\d+)~', $link->getAttribute('href'), $animalMatch);
            $petId = (int)($animalMatch[1] ?? 0);
            if (!$petId) continue;
            $linkText = trim(preg_replace('/\s+/', ' ', $link->textContent));
            preg_match('/\((.+)\)\s*$/', $linkText, $breedMatch);
            $breed = $breedMatch[1] ?? null;
            $petName = trim(preg_replace('/\s*\(.+\)\s*$/', '', $linkText));

            $boldNode = $xpath->query('.//*[contains(@class,"font-bold")]', $tds->item(1))->item(0);
            $name = trim($boldNode?->textContent ?? '');
            $name = trim(preg_replace('/ (?:Starting At: )?@.+$/', '', $name));
            if (!$name) continue;

            $timeText = trim(preg_replace('/\s+/', ' ', $tds->item(11)->textContent));
            $scheduledStart = $timeText ? Carbon::createFromFormat('l, n/j/Y g:i a', $timeText) : null;

            $assignedTo = trim(preg_replace('/\s+/', ' ', $tds->item(10)->textContent)) ?: null;

            $services[] = [
                'service_id' => $serviceId,
                'pet_id' => $petId,
                'pet_name' => $petName,
                'breed' => $breed,
                'service_name' => $name,
                'assigned_to' => $assignedTo,
                'scheduled_start' => $scheduledStart,
            ];
        }

        return $services;
    }

    public function journalMaker(): Response
    {
        return Inertia::render('JournalEntry/Index');
    }

    public function journalTransform(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'bank_account' => 'required|string|max:255',
            'csv' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $content = file_get_contents($request->file('csv')->getRealPath());
        $result = $this->journalTransformer->transform($content, $validated['date'], $validated['bank_account']);

        return response()->json($result);
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
