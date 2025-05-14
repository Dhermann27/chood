<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Rotation;
use App\Traits\ChoodTrait;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    use ChoodTrait;

    const ROW_VIEWS = ['last' => [2046, 2099, -18],
        'mid' => [2016, 2045, -12],
        'first' => [0, 2015, 0]

    ];

    public function fullmap(): Response
    {
        return Inertia::render('Fullmap', [
            'photoUri' => config('services.dd.uris.photo'),
            'cabins' => $this->getCabins(),
        ]);
    }

    public function rowmap($row): Response
    {
        // 0 means First rowmap
        return Inertia::render('Rowmap' . $row, [
            'photoUri' => config('services.dd.uris.photo'),
            'cabins' => $this->getCabins(self::ROW_VIEWS[$row][0], self::ROW_VIEWS[$row][1], self::ROW_VIEWS[$row][2]),
        ]);
    }

    public function yardmap($size): Response
    {
        return Inertia::render('Yardmap', [
            'size' => $size,
            'photoUri' => config('services.dd.uris.photo'),
        ]);
    }

    public function mealmap(): Response
    {
        $groupedEmployees = Employee::with('shift')->orderBy('first_name')->get()
            ->groupBy(function ($employee) {
                return $employee->shift && $employee->shift->is_working ? 'Scheduled' : 'Unscheduled';
            })->map(function ($group, $status) {
                return ['status' => $status, 'employees' => $group];
            })->sortBy(fn($group) => $group['status'] === 'Scheduled' ? 0 : 1)->values()->all();

        return Inertia::render('Mealmap', [
            'dogsPerPage' => intval(config('services.dd.mealmap_dpp')),
            'photoUri' => config('services.dd.uris.photo'),
            'employees' => $groupedEmployees,
            'rotations' => Rotation::orderBy('start_time')->get(),
        ]);
    }

    public function groommap(): Response
    {
        return Inertia::render('Groommap', [
            'photoUri' => config('services.dd.uris.photo'),
        ]);
    }


}
