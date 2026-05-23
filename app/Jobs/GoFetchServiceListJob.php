<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
use App\Enums\ReportCategory;
use App\Models\Service;
use App\Services\FetchDataService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoFetchServiceListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $location = config('services.gingr.location_id');
        $typeIds = config('services.gingr.service_type_ids', []);
        $seen = [];

        foreach ($typeIds as $typeId) {
            try {
                $output = $fetchDataService->fetchApi('servicesByType', [
                    'type_id' => $typeId,
                    'location' => $location,
                    'include_precheck_services' => 'false',
                    'precheck_id' => '',
                ]);
            } catch (Exception $e) {
                Log::warning("GoFetchServiceListJob: type_id {$typeId} failed: " . $e->getMessage());
                continue;
            }

            foreach ($output['allowable_services'] ?? [] as $svc) {
                $gingrId = (int)$svc['id'];
                if (isset($seen[$gingrId])) continue;
                $seen[$gingrId] = true;

                $name = trim(preg_replace('/ (?:Starting At: )?@.+$/', '', $svc['name']));

                Service::updateOrCreate(
                    ['gingr_id' => $gingrId],
                    [
                        'name' => $name,
                        'housing_code' => HousingServiceCodes::fromServiceName($name),
                        'report_category' => ReportCategory::resolve($svc['booking_category_id'], $svc['account_code_id'], $name),
                        'booking_category_id' => $svc['booking_category_id'] ?: null,
                        'account_code_id' => $svc['account_code_id'] ?: null,
                        'duration' => (int)$svc['duration'],
                        'is_active' => $svc['is_deleted'] === '0' && $svc['status'] === '1',
                    ]
                );
            }
        }

        Log::info('GoFetchServiceListJob: synced ' . count($seen) . ' services.');
    }

}
