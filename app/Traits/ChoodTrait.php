<?php

namespace App\Traits;

use App\Models\Cabin;
use App\Models\InhouseDogs;
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
            ->with('cleaning_status')->get()->map(function ($cabin) use ($subtractor) {
                $cabin->cabinName = preg_replace('/Luxury Suite /', 'LS', $cabin->cabinName);
                $cabin->cabinName = preg_replace('/\dx\d - Cabin /', '', $cabin->cabinName);
                $cabin->kappa = $cabin->kappa + $subtractor;
                return $cabin;
            });
    }

    /**
     * @param bool $filterByCabinId
     * @param array $sizes
     * @return Collection
     */
    public function getDogs(bool $filterByCabinId = false, array $sizes = []): Collection
    {
        $dogs = InhouseDogs::with('services');
        if ($filterByCabinId) $dogs->whereNotNull('cabin_id');
        if ($sizes) $dogs->whereIn('size', $sizes);
        return $dogs->get();
    }
}
