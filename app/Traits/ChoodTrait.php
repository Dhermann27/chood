<?php

namespace App\Traits;

use App\Enums\HousingServiceCodes;
use App\Http\Controllers\MapController;
use App\Models\Cabin;
use App\Models\Dog;
// use App\Models\Service; // TODO: restore when Gingr service sync is verified in prod
// use Carbon\Carbon;
use Illuminate\Support\Collection;


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
        return new Collection($this->getDogs(false)
            ->groupBy(function ($dog) {
                return $dog->cabin_id ?? 'unassigned';
            })
            ->map(function ($dogs) {
                return $dogs->values()->all(); // Ensure each group is converted into an array
            }));
    }

    /**
     * @param bool $filterByCabinId
     * @param string|null $size
     * @return Collection
     */
    public function getDogs(bool $filterByCabinId = false, string $size = null): Collection
    {
        // TODO: re-add appointments.service once Gingr service sync is verified in prod
        $dogs = Dog::with(/*'appointments.service',*/ 'cabin', 'breakType', 'icons');
        if ($filterByCabinId) $dogs->whereNotNull('cabin_id');
        if ($size) $dogs->whereIn('housing_code', HousingServiceCodes::housingValues());

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

//        $specialServiceIds = Service::whereIn('category', config('services.gingr.special_service_cats'))->pluck('id');
//        $today = Carbon::today();
//
//        return Dog::select('dogs.*')->distinct()->join('appointments', 'appointments.pet_id', '=', 'dogs.pet_id')
//            ->whereIn('appointments.service_id', $specialServiceIds)
//            ->whereDate('appointments.scheduled_start', config('services.gingr.sandbox_service_condition'), $today)
//            ->with(['appointments.service'])->get()->sortBy(fn($dog) => optional(
//                $dog->appointments->firstWhere(fn($ds) => in_array($ds->service_id, $specialServiceIds->all(), true)
//                    && Carbon::parse($ds->scheduled_start)->isSameDay($today)
//                )
//            )?->scheduled_start)->values();
    }

}
