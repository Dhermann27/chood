<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
use App\Models\Cabin;
use App\Models\CleaningStatus;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $listChanged = false;

        foreach ($output['data'] as $row) {
            if (!empty($row['type_id']) && !isset($seenTypeIds[$row['type_id']])) {
                $seenTypeIds[$row['type_id']] = true;
                Service::updateOrCreate(
                    ['gingr_id' => (int)$row['type_id']],
                    ['name' => $row['type'], 'housing_code' => HousingServiceCodes::fromServiceName($row['type'] ?? '')]
                );
            }
            $servicesString = $row['services_string'] ?? null ?: null;
            $dog = Dog::updateOrCreate(
                ['pet_id' => $row['a_id']],
                array_merge($this->getUpdateValues($row, $cabins), [
                    'checked_out_at' => null,
                    'services_string' => $servicesString,
                ])
            );
            if ($dog->wasRecentlyCreated) {
                $listChanged = true;
            }
            $activePetIds[] = $dog->pet_id;
            $activeOwnerIds[] = $row['o_id'];

            if (!in_array($row['o_id'], $dispatchedOwnerIds)) {
                GoFetchAnimalDataJob::dispatch($row['o_id']);
                $dispatchedOwnerIds[] = $row['o_id'];
            }
        }

        $this->resolveDisplayNames($activePetIds);
        GoFetchIconsJob::dispatch($activePetIds, $activeOwnerIds);

        // Mark newly departed dogs (first time missing from Gingr feed)
        $newlyGoneDogs = Dog::whereNotNull('pet_id')->whereNull('checked_out_at')->whereNotIn('pet_id', $activePetIds)->get(['pet_id', 'cabin_id', 'account_id']);
        Dog::whereIn('pet_id', $newlyGoneDogs->pluck('pet_id'))->update(['checked_out_at' => now()]);
        if ($newlyGoneDogs->isNotEmpty()) {
            DB::table('dog_icons')->whereIn('pet_id', $newlyGoneDogs->pluck('pet_id'))->delete();
        }

        if ($listChanged || $newlyGoneDogs->isNotEmpty() || !Cache::has('section_counts')) {
            GoFetchSectionCountsJob::dispatch();
        }

        $feedingBlocks = Dog::whereNull('pet_id')
            ->whereIn('account_id', $newlyGoneDogs->pluck('account_id')->filter()->unique())
            ->get(['id', 'cabin_id']);

        $cabinsToMark = $newlyGoneDogs->pluck('cabin_id')
            ->merge($feedingBlocks->pluck('cabin_id'))
            ->filter()
            ->unique();

        foreach ($cabinsToMark as $cabinId) {
            $status = CleaningStatus::where('cabin_id', $cabinId)->first();
            if (!$status) {
                CleaningStatus::create(['cabin_id' => $cabinId, 'cleaning_type' => CleaningStatus::STATUS_DAILY, 'created_by' => 'GoFetchListJob', 'created_at' => now()]);
            } elseif ($status->completed_at !== null) {
                $status->update(['cleaning_type' => CleaningStatus::STATUS_DAILY, 'wiw_user_id' => null, 'completed_at' => null, 'updated_by' => 'GoFetchListJob', 'updated_at' => now()]);
            }
            // completed_at IS NULL: already pending (daily or deep) — leave it alone
        }

        Dog::whereIn('id', $feedingBlocks->pluck('id'))->delete();

        $delay = config('services.gingr.queue_delay');
        usleep(mt_rand($delay, $delay + 1000) * 1000);
    }

    private function resolveDisplayNames(array $activePetIds): void
    {
        $dogs = Dog::whereIn('pet_id', $activePetIds)->get(['id', 'firstname', 'lastname']);

        foreach ($dogs->groupBy(fn($d) => trim($d->firstname ?? '')) as $firstname => $group) {
            if ($group->count() === 1) {
                $group->first()->update(['display_name' => $firstname]);
                continue;
            }

            $maxLen = $group->max(fn($d) => strlen(trim($d->lastname ?? '')));
            $resolved = false;

            for ($i = 1; $i <= $maxLen; $i++) {
                $names = $group->map(fn($d) => $firstname . ' ' . ucfirst(strtolower(substr(trim($d->lastname ?? ''), 0, $i))));
                if ($names->unique()->count() === $group->count()) {
                    $group->each(fn($d) => $d->update([
                        'display_name' => $firstname . ' ' . ucfirst(strtolower(substr(trim($d->lastname ?? ''), 0, $i))),
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
            'firstname' => isset($row['a_first']) ? trim($row['a_first']) ?: null : null,
            'lastname' => isset($row['o_last']) ? trim($row['o_last']) ?: null : null,
            'weight' => $row['weight'] ? (int)$row['weight'] : null,
            'cabin_id' => $cabins->get($this->normCabinName($row['run_name'] ?? ''), null),
            'housing_code' => $this->resolveHousingCode($row),
            'photoUri' => $row['image'] ?: null,
            'checkin' => $row['check_in_stamp'] ? Carbon::createFromTimestamp($row['check_in_stamp'], config('app.timezone')) : null,
            'checkout' => $row['end_date'] ? Carbon::createFromTimestamp($row['end_date'], config('app.timezone')) : null,
        ], fn($v) => !is_null($v));
    }

    private function resolveHousingCode(array $row): string
    {
        // Orientation dogs may be checked in as Grooming Services with Interview/Evaluation added on.
        $sl = strtolower($row['services_string'] ?? '');
        if (str_contains($sl, 'interview') || str_contains($sl, 'evaluation') || str_contains($sl, 'orientation')) {
            return HousingServiceCodes::INTV->value;
        }

        return $this->housingCodeFromTypeId($row['type_id'] ?? '');
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
