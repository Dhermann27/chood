<?php

namespace App\Traits;

use App\Http\Controllers\MapController;
use App\Models\Cabin;
use App\Models\Dog;
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
                $cabin->cabinName = preg_replace('/Luxury Suite /', 'LS', $cabin->cabinName);
                $cabin->cabinName = preg_replace('/\dx\d\s?- Cabin /', '', $cabin->cabinName);
                $cabin->kappa += $subtractor;
                if ($end == MapController::ROW_VIEWS['first'][1] && $cabin->id < 2000) {
                    $cabin->rho += 5;
                    $cabin->kappa -= 3;
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
        $dogs = Dog::with('services');
        if ($filterByCabinId) $dogs->whereNotNull('cabin_id');
        if ($size) $dogs->where('weight', $size == 'small' ? '<=' : '>=', $size == 'small' ? 40 : 30);
        return $dogs->orderBy('firstname')->get();
    }
}
