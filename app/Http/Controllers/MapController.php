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
        return Inertia::render('Fullmap', [
            'photoUri' => config('services.puppeteer.uris.photo'),
            'cabins' => $this->getCabins(),
            'services' => Service::all(),
        ]);
    }

    public function rowmap($row): Response
    {
        // 0 means First rowmap
        return Inertia::render('Rowmap' . $row, [
            'photoUri' => config('services.puppeteer.uris.photo'),
            'cabins' => $this->getCabins(self::rowviews[$row][0], self::rowviews[$row][1], self::rowviews[$row][2]),
        ]);
    }

    public function yardmap($size): Response
    {
        return Inertia::render('Yardmap', [
            'size' => $size,
            'photoUri' => config('services.puppeteer.uris.photo'),
        ]);
    }


}
