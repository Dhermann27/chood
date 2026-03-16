<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
use App\Models\Cabin;
use App\Models\Dog;
use App\Services\FetchDataService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoFetchListJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function shouldDispatch(): bool
    {
        return !app()->isDownForMaintenance();
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $output = $fetchDataService->fetchApi('checkedIn');

        if (empty($output['data']) || !is_array($output['data'])) {
            Log::warning('GoFetchListJob returned no usable data.', ['output' => $output]);
            throw new Exception('No usable data found in response.');
        }

        $cabins = Cabin::all()
            ->keyBy(fn($c) => $this->normCabinName($c->cabinName))
            ->map(fn($c) => $c->id);

        $activePetIds = [];
        $dispatchedOwnerIds = [];

        foreach ($output['data'] as $row) {
            $dog = Dog::updateOrCreate(
                ['pet_id' => $row['a_id']],
                $this->getUpdateValues($row, $cabins)
            );
            $activePetIds[] = $dog->pet_id;

            if (!in_array($row['o_id'], $dispatchedOwnerIds)) {
                GoFetchOwnerDataJob::dispatch($row['o_id']);
                $dispatchedOwnerIds[] = $row['o_id'];
            }
        }

        Dog::whereNotIn('pet_id', $activePetIds)->delete();

        $delay = config('services.gingr.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }

    private function getUpdateValues(array $row, $cabins): array
    {
        return array_filter([
            'order_id'     => $row['id'],
            'account_id'   => $row['o_id'],
            'firstname'    => $row['a_first'] ?: null,
            'weight'       => $row['weight'] ? (int) $row['weight'] : null,
            'cabin_id'     => $cabins->get($this->normCabinName($row['run_name'] ?? ''), null),
            'housing_code' => $this->housingCodeFromTypeId($row['type_id']),
            'photoUri'     => $row['image'] ?: null,
            'checkin'      => $row['check_in_stamp'] ? Carbon::createFromTimestamp($row['check_in_stamp']) : null,
            'checkout'     => $row['end_date'] ? Carbon::createFromTimestamp($row['end_date']) : null,
        ], fn($v) => !is_null($v));
    }

    private function housingCodeFromTypeId(string $typeId): string
    {
        return match($typeId) {
            '1' => HousingServiceCodes::BRDC->value,
            '2' => HousingServiceCodes::BRDL->value,
            '3' => HousingServiceCodes::DCFD->value,
            '4' => HousingServiceCodes::DCHD->value,
            default => HousingServiceCodes::UNKNOWN->value,
        };
    }

    private function normCabinName(string $key): string
    {
        return strtolower(preg_replace('/[\s\-—–]+/', '', $key));
    }
}
