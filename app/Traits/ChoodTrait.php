<?php

namespace App\Traits;

use App\Enums\HousingServiceCodes;
use App\Http\Controllers\MapController;
use App\Models\Cabin;
use App\Models\Dog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

// use App\Models\Service; // TODO: restore when Gingr service sync is verified in prod


trait ChoodTrait
{
    /**
     * @param int $start
     * @param int $end
     * @param int $subtractor
     * @return Collection
     */
    public function getCabins(int $start = 0, int $end = 9999, int $subtractor = 0): Collection
    {
        return Cabin::where('rho', '>', '0')->where('kappa', '>', '0')->whereBetween('id', [$start, $end])
            ->get()->map(function ($cabin) use ($subtractor, $end) {
                $cabin->kappa += $subtractor;
                if ($end == MapController::ROW_VIEWS['first'][1] && $cabin->id < 1500) {
                    $cabin->rho = 6;
                    if ($cabin->id == 1000) $cabin->kappa = 7;
                    if ($cabin->id == 1001) $cabin->kappa = 9;
                    if ($cabin->id == 1002) $cabin->kappa = 10;
                }
                return $cabin;
            });
    }

    public function getDogsByCabin(): Collection
    {
        return $this->getDogs(false, null, true)
            ->groupBy(fn($dog) => $dog->cabin_id ?? 'unassigned')
            ->map(fn($dogs) => $dogs->values()->all());
    }

    /**
     * @param bool $filterByCabinId
     * @param string|null $size
     * @param bool $includeCheckedOut
     * @return Collection
     */
    public function getDogs(bool $filterByCabinId = false, string $size = null, bool $includeCheckedOut = false): Collection
    {
        // TODO: re-add appointments.service once Gingr service sync is verified in prod
        $dogs = Dog::with(/*'appointments.service',*/ 'cabin', 'breakType', 'icons');
        if ($filterByCabinId) $dogs->whereNotNull('cabin_id');
        if ($size) {
            $dogs->whereIn('housing_code', HousingServiceCodes::housingValues());
            $dogs->whereNotNull('pet_id');
        }

        if (!$includeCheckedOut) {
            // Yardmap: in-house + checked out within 120s
            $dogs->where(fn($q) => $q->whereNull('checked_out_at')
                ->orWhere('checked_out_at', '>=', Carbon::now()->subSeconds(120)));
        }
        $result = $dogs->orderBy('firstname')->get();

        if ($size === 'small') {
            return $result->filter(fn($dog) => str_contains($dog->size_letter, 'S') || str_contains($dog->size_letter, 'T'))->values();
        }
        if ($size === 'large') {
            return $result->filter(fn($dog) => str_contains($dog->size_letter, 'L'))->values();
        }

        return $result;
    }

    /**
     * @return Collection
     */
    public function getGroomingDogsToday(): Collection
    {
        // TODO: rewrite for Gingr once service sync is verified in prod
        return new Collection();
    }

}
