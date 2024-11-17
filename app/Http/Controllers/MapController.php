<?php

namespace App\Http\Controllers;

use App\Models\Cabin;
use App\Models\Dog;
use Inertia\Inertia;

class MapController extends Controller
{
    const rowviews = ['last' => [2054, 2099, -20],
        'mid' => [2019, 2053, -14],
        'first' => [0, 2018, 0]

    ];

    public function fullmap()
    {
        $cabins = $this->getCabins();

        $dogs = Dog::selectRaw('*, LEFT(name, 8) AS shortname')->whereNotNull('cabin_id')->with('services')->get()
            ->mapWithKeys(function ($dog) {
                return [$dog->cabin_id => $dog];
            });

        return Inertia::render('Fullmap', [
            'photoUri' => config('services.panther.uris.photo'),
            'dogs' => $dogs,
            'cabins' => $cabins,
            'checksum' => md5($dogs->toJson())
        ]);
    }

    public function rowmap($row)
    {
        $cabins = $this->getCabins(self::rowviews[$row][0], self::rowviews[$row][1], self::rowviews[$row][2]);

        $dogs = Dog::selectRaw('*, LEFT(name, 12) AS shortname')->whereNotNull('cabin_id')->with('services')->get()
            ->mapWithKeys(function ($dog) {
                return [$dog->cabin_id => $dog];
            });

        return Inertia::render('Rowmap' . $row, [
            'photoUri' => config('services.panther.uris.photo'),
            'dogs' => $dogs,
            'cabins' => $cabins,
            'checksum' => md5($dogs->toJson())
        ]);
    }

    public function yardmap($size)
    {
        $sizes = $size === 'small' ? ['Medium', 'Small', 'Extra Small'] : ['Medium', 'Large', 'Extra Large'];
        $dogs = Dog::selectRaw('*, LEFT(name, 25) AS shortname')->whereIn('size', $sizes)->with('services')
            ->orderBy('name')->get();

        return Inertia::render('Yardmap' . $size, [
            'photoUri' => config('services.panther.uris.photo'),
            'dogs' => $dogs,
            'checksum' => md5($dogs->toJson())
        ]);
    }

    /**
     * @return mixed
     */
    public function getCabins($start = 0, $end = 9999, $subtractor = 0)
    {
        $cabins = Cabin::where('row', '>', '0')->where('column', '>', '0')->whereBetween('id', [$start, $end])
            ->with('cleaning_status')->get()->map(function ($cabin) use ($subtractor) {
                $cabin->cabinName = preg_replace('/Luxury Suite /', 'LS', $cabin->cabinName);
                $cabin->cabinName = preg_replace('/\dx\d - Cabin /', '', $cabin->cabinName);
                $cabin->column = $cabin->column + $subtractor;
                return $cabin;
            });
        return $cabins;
    }
}
