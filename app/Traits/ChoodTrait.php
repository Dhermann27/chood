<?php

namespace App\Traits;

use App\Http\Controllers\MapController;
use App\Models\Cabin;
use App\Models\Dog;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

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
        $dogs = Dog::with('dogServices.service', 'cabin');
        if ($filterByCabinId) $dogs->whereNotNull('cabin_id');
        if ($size) {
            if ($size === 'small') {
                $dogs->where(function ($query) {
                    $query->where('size_letter', 'LIKE', '%S%')
                        ->orWhere('size_letter', 'LIKE', '%T%');
                });
            } else {
                $dogs->where('size_letter', 'LIKE', '%L%');
            }
            $dogs->whereHas('dogServices.service', function ($query) {
                $query->whereIn('category', config('services.dd.regular_service_cats'));
            });
        }

        return $dogs->orderBy('firstname')->get();
    }

    /**
     * @return Collection
     */
    public function getGroomingDogsToday(): Collection
    {
        $specialServiceIds = Service::whereIn('category', config('services.dd.special_service_cats'))->pluck('id');
        $today = Carbon::today();

        return Dog::select('dogs.*')->distinct()->join('dog_services', 'dog_services.pet_id', '=', 'dogs.pet_id')
            ->whereIn('dog_services.service_id', $specialServiceIds)
            ->whereDate('dog_services.scheduled_start', config('services.dd.sandbox_service_condition'), $today)
            ->with(['dogServices.service'])->get()->sortBy(fn($dog) =>
            optional(
                $dog->dogServices->firstWhere(fn($ds) =>
                    in_array($ds->service_id, $specialServiceIds->all(), true)
                    && Carbon::parse($ds->scheduled_start)->isSameDay($today)
                )
            )?->scheduled_start)->values();
    }

}
