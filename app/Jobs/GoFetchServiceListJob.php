<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
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

    // Gingr reservation type_ids to fetch services for
    private const TYPE_IDS = [1, 2, 3, 4, 5];

    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $location = config('services.gingr.location_id');
        $seen = [];

        foreach (self::TYPE_IDS as $typeId) {
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

                Service::updateOrCreate(
                    ['gingr_id' => $gingrId],
                    [
                        'name' => $svc['name'],
                        'category' => $svc['booking_category_id'],
                        'housing_code' => $this->housingCodeFromName($svc['name']),
                        'duration' => (int)$svc['duration'],
                        'is_active' => $svc['is_deleted'] === '0' && $svc['status'] === '1',
                    ]
                );
            }
        }

        Log::info('GoFetchServiceListJob: synced ' . count($seen) . ' services.');
    }

    private function housingCodeFromName(string $name): ?HousingServiceCodes
    {
        $name = strtolower($name);
        if (str_contains($name, 'boarding') && str_contains($name, 'luxury')) return HousingServiceCodes::BRDL;
        if (str_contains($name, 'boarding')) return HousingServiceCodes::BRDC;
        if (str_contains($name, 'day camp') && str_contains($name, 'half')) return HousingServiceCodes::DCHD;
        if (str_contains($name, 'day camp')) return HousingServiceCodes::DCFD;
        return null;
    }
}
