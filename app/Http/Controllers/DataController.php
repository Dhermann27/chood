<?php

namespace App\Http\Controllers;

use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\YardAssignment;
use App\Traits\ChoodTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class DataController extends Controller
{
    use ChoodTrait;

    function fullmap(string $checksum = null): JsonResponse
    {
        $dogs = $this->getDogsByCabin();
        $statuses = CleaningStatus::whereNull('completed_at')->pluck('cleaning_type', 'cabin_id')->toArray();
        $outhouseDogs = Dog::whereNull('cabin_id')->orderBy('firstname')->get(); // TODO: Unnecessary with unassigned dogs?
        $new_checksum = md5($dogs->toJson() . json_encode($statuses));
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'statuses' => $statuses,
                'outhouseDogs' => $outhouseDogs,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);
    }

    function mealmap(string $checksum = null): JsonResponse
    {
        $assignments = YardAssignment::with('employee')->get()->groupBy('start_time')
            ->map(function ($hourAssignments) {
                return $hourAssignments->map(function ($assignment) {
                    return $assignment->employee;
                });
            });
        $employees = Employee::whereNotNull('next_first_break')->orderBy('first_name')->get();

        $new_checksum = md5($assignments . $employees);
        if ($checksum !== $new_checksum) {
            $response = [
                'hours' => $assignments,
                'breaks' => $employees,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);

    }

    function yardmap(string $size, string $checksum = null): JsonResponse
    {
        $now = Carbon::now();
        $dogs = $this->getDogs(false, $size);
        $assignments = YardAssignment::where('start_time', '<=', $now)->where('end_time', '>=', $now)
            ->orderBy('yard_number')->with('employee')->get();
        $nextBreak = Employee::selectRaw('*, GREATEST(COALESCE(next_first_break,0), COALESCE(next_second_break,0)) as next_break')
            ->where(function ($query) use ($now) {
                $query->where('next_first_break', '>', $now)
                    ->orWhere('next_second_break', '>', $now);
            })->orderBy('next_break', 'ASC')->first();
        $nextLunch = Employee::whereNotNull('next_lunch_break')->where('next_lunch_break', '>=', $now)
            ->orderBy('next_lunch_break')->first();

        $new_checksum = md5($dogs->toJson() . $assignments . $nextBreak . $nextLunch);
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'assignments' => $assignments,
                'nextBreak' => $nextBreak,
                'nextLunch' => $nextLunch,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);

    }
}
