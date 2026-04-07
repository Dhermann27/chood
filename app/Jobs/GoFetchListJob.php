<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
use App\Models\Appointment;
use App\Models\Cabin;
use App\Models\Dog;
use App\Models\Service;
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
        $activeOwnerIds = [];
        $dispatchedOwnerIds = [];
        $seenTypeIds = [];

        foreach ($output['data'] as $row) {
            if (!empty($row['type_id']) && !isset($seenTypeIds[$row['type_id']])) {
                $seenTypeIds[$row['type_id']] = true;
                Service::updateOrCreate(
                    ['gingr_id' => (int) $row['type_id']],
                    ['name' => $row['type'], 'housing_code' => HousingServiceCodes::fromServiceName($row['type'] ?? '')]
                );
            }
            $dog = Dog::updateOrCreate(
                ['pet_id' => $row['a_id']],
                $this->getUpdateValues($row, $cabins)
            );
            $activePetIds[] = $dog->pet_id;
            $activeOwnerIds[] = $row['o_id'];

            if (!in_array($row['o_id'], $dispatchedOwnerIds)) {
                GoFetchAnimalDataJob::dispatch($row['o_id']);
                $dispatchedOwnerIds[] = $row['o_id'];
            }
        }

        $this->resolveDisplayNames($activePetIds);
        GoFetchIconsJob::dispatch($activePetIds, $activeOwnerIds);

        $inactivePetIds = Dog::whereNotIn('pet_id', $activePetIds)->pluck('pet_id');
        Appointment::whereIn('pet_id', $inactivePetIds)->delete();
        Dog::whereIn('pet_id', $inactivePetIds)->delete();

        $delay = config('services.gingr.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }

    private function resolveDisplayNames(array $activePetIds): void
    {
        $dogs = Dog::whereIn('pet_id', $activePetIds)->get(['id', 'firstname', 'lastname']);

        foreach ($dogs->groupBy('firstname') as $firstname => $group) {
            if ($group->count() === 1) {
                $group->first()->update(['display_name' => $firstname]);
                continue;
            }

            $maxLen = $group->max(fn($d) => strlen($d->lastname ?? ''));
            $resolved = false;

            for ($i = 1; $i <= $maxLen; $i++) {
                $names = $group->map(fn($d) => $firstname . ' ' . ucfirst(strtolower(substr($d->lastname ?? '', 0, $i))));
                if ($names->unique()->count() === $group->count()) {
                    $group->each(fn($d) => $d->update([
                        'display_name' => $firstname . ' ' . ucfirst(strtolower(substr($d->lastname ?? '', 0, $i))),
                    ]));
                    $resolved = true;
                    break;
                }
            }

            if (!$resolved) {
                $group->each(fn($d) => $d->update(['display_name' => trim("$firstname {$d->lastname}")]));
            }
        }
    }

    private function getUpdateValues(array $row, $cabins): array
    {
        return array_filter([
            'order_id' => $row['id'],
            'account_id' => $row['o_id'],
            'firstname' => $row['a_first'] ?: null,
            'lastname' => $row['o_last'] ?: null,
            'weight' => $row['weight'] ? (int)$row['weight'] : null,
            'cabin_id' => $cabins->get($this->normCabinName($row['run_name'] ?? ''), null),
            'housing_code' => $this->housingCodeFromTypeId($row['type_id'] ?? ''),
            'photoUri' => $row['image'] ?: null,
            'checkin' => $row['check_in_stamp'] ? Carbon::createFromTimestamp($row['check_in_stamp']) : null,
            'checkout' => $row['end_date'] ? Carbon::createFromTimestamp($row['end_date']) : null,
        ], fn($v) => !is_null($v));
    }

    private function housingCodeFromTypeId(string $typeId): string
    {
        return Service::where('gingr_id', (int)$typeId)->first()?->housing_code?->value
            ?? HousingServiceCodes::UNKNOWN->value;
    }

    private function normCabinName(string $key): string
    {
        return strtolower(preg_replace('/[\s\-—–]+/', '', $key));
    }
}
