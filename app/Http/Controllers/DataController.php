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
use stdClass;

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
        $assignments = YardAssignment::with('employee')->where(function ($query) {
                $isSunday = Carbon::now()->isSunday();
                if ($isSunday) {
                    $query->whereNotBetween('start_time', ['10:00:00', '14:00:00']);
                }
            })->get()->groupBy('start_time')->map(function ($hourAssignments) {
                return $hourAssignments->map(function ($assignment) {
                    if ($assignment->yard_number == 99) {
                        return $assignment->description;
                    }
                    return $assignment->employee;
                });
            });

        $employees = Employee::whereNotNull('next_first_break')->orderBy('first_name')->get();
        $dogs = Dog::whereHas('feedings', function ($query) {
            $query->whereRaw('DATE(feedings.modified_at) >= DATE(dogs.checkin)');
        })->orWhereHas('medications', function ($query) {
            $query->whereRaw('DATE(medications.modified_at) >= DATE(dogs.checkin)');
        })->orWhereHas('allergies')->with(['feedings', 'medications', 'allergies', 'cabin', 'services'])
            ->orderBy('cabin_id')->orderBy('firstname')->get();


        if (Carbon::today()->isSunday()) {
            $employees = $employees->filter(function ($employee) {
                return $employee->next_first_break !== '10:00:00' || $employee->next_second_break !== null;
            });

            $nextBreak = new stdClass();
            $nextBreak->first_name = 'Everyone';
            $nextBreak->next_first_break = '10:00:00';
            $employees->unshift($nextBreak);
        }

        $new_checksum = md5($assignments . $employees . $dogs);
        if ($checksum !== $new_checksum) {
            $response = [
                'breaks' => $employees,
                'dogs' => $dogs,
                'hours' => $assignments,
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
        $assignments = YardAssignment::where('yard_number', '!=', '99')->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)->orderBy('yard_number')->with('employee')->get();
        if ($now->isSunday() && $now->hour < 12) {
            $nextBreak = new \stdClass();
            $nextBreak->first_name = 'Everyone';
            $nextBreak->next_break = '10:00:00';
            $nextLunch = null;
        } else {
            $nextFirstBreak = Employee::whereNotNull('next_first_break')->where('next_first_break', '>=', $now)
                ->orderBy('next_first_break')->first();
            $nextSecondBreak = Employee::whereNotNull('next_second_break')->where('next_second_break', '>=', $now)
                ->orderBy('next_second_break')->first();
            $nextBreak = null;
            if ($nextFirstBreak && $nextSecondBreak) {
                if (Carbon::parse($nextFirstBreak->next_first_break)
                    ->lessThanOrEqualTo(Carbon::parse($nextSecondBreak->next_second_break))) {
                    $nextBreak = $nextFirstBreak;
                    $nextBreak->next_break = $nextFirstBreak->next_first_break;
                } else {
                    $nextBreak = $nextSecondBreak;
                    $nextBreak->next_break = $nextSecondBreak->next_second_break;
                }
            } elseif ($nextFirstBreak) {
                $nextBreak = $nextFirstBreak;
                $nextBreak->next_break = $nextFirstBreak->next_first_break;
            } elseif ($nextSecondBreak) {
                $nextBreak = $nextSecondBreak;
                $nextBreak->next_break = $nextSecondBreak->next_second_break;
            }

            $nextLunch = Employee::whereNotNull('next_lunch_break')->where('next_lunch_break', '>=', $now)
                ->orderBy('next_lunch_break')->first();
        }

        $new_checksum = md5($dogs->toJson() . $assignments->toJson() . json_encode($nextBreak) .
            json_encode($nextLunch));
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
