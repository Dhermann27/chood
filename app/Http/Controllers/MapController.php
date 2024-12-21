<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Traits\ChoodTrait;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    use ChoodTrait;

    const rowviews = ['last' => [2055, 2099, -20],
        'mid' => [2019, 2054, -14],
        'first' => [0, 2018, 0]

    ];

    public function fullmap(): Response
    {
        $dogs = $this->getDogsByCabin();
        $outhouseDogs = $dogs->flatten()->filter(function ($dog) {
            return $dog->is_inhouse == 0;
        })->values()->all();

        return Inertia::render('Fullmap', [
            'photoUri' => config('services.puppeteer.uris.photo'),
            'dogs' => $dogs,
            'outhouseDogs' => $outhouseDogs,
            'cabins' => $this->getCabins(),
            'services' => Service::all(),
            'checksum' => md5($dogs->toJson())
        ]);
    }

    public function rowmap($row): Response
    {
        $dogs = $this->getDogsByCabin();

        return Inertia::render('Rowmap' . $row, [
            'photoUri' => config('services.puppeteer.uris.photo'),
            'dogs' => $dogs,
            'cabins' => $this->getCabins(self::rowviews[$row][0], self::rowviews[$row][1], self::rowviews[$row][2]),
            'checksum' => md5($dogs->toJson())
        ]);
    }

    public function yardmap($size): Response
    {
        $sizes = $size === 'small' ? ['Medium', 'Small', 'Extra Small'] : ['Medium', 'Large', 'Extra Large'];
        $dogs = $this->getDogs(false, $sizes);

        return Inertia::render('Yardmap' . $size, [
            'photoUri' => config('services.puppeteer.uris.photo'),
            'dogs' => $dogs,
            'checksum' => md5($dogs->toJson())
        ]);
    }


}
