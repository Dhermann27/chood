<?php

namespace App\Http\Controllers;

use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\EmployeeYardRotation;
use App\Models\RotationYardView;
use App\Models\Shift;
use App\Models\Yard;
use App\Traits\ChoodTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class DataController extends Controller
{
    const BREAK_COLUMNS = ['next_first_break', 'next_lunch_break', 'next_second_break'];
    use ChoodTrait;

    function fullmap(string $checksum = null): JsonResponse
    {
        $dogs = $this->getDogsByCabin();
        $statuses = CleaningStatus::whereNull('completed_at')->pluck('cleaning_type', 'cabin_id')->toArray();
        $new_checksum = md5($dogs->toJson() . json_encode($statuses));
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'statuses' => $statuses,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);
    }

    function mealmap(string $checksum = null): JsonResponse
    {
        $yards = Yard::orderBy('display_order')->get();

        $assignments = RotationYardView::orderBy('rotation_id')->orderBy('display_order')->get()
            ->groupBy('rotation_id')->map(function ($rotationGroup) {
                return $rotationGroup->groupBy('yard_id')->map(function ($yardGroup) {
                    return $yardGroup->pluck('homebase_user_id')->filter()
                        ->map(fn($id) => ['homebase_user_id' => $id])->values();
                });
            });

        $employees = Employee::whereHas('shift')->with('shift')->get()->map(function ($employee) {
            $shift = $employee->shift;

            return [
                'homebase_user_id' => $employee->homebase_user_id,
                'first_name' => $employee->first_name,
                'next_first_break' => optional($shift->next_first_break ? Carbon::parse($shift->next_first_break) : null)->format('h:ia'),
                'next_lunch_break' => optional($shift->next_lunch_break ? Carbon::parse($shift->next_lunch_break) : null)->format('h:ia'),
                'next_second_break' => optional($shift->next_second_break ? Carbon::parse($shift->next_second_break) : null)->format('h:ia'),
            ];
        })->sortBy('first_name')->values();


        $dogs = Dog::whereHas('feedings', function ($query) {
            $query->whereRaw('DATE(feedings.modified_at) >= DATE(dogs.checkin)');
        })->orWhereHas('medications', function ($query) {
            $query->whereRaw('DATE(medications.modified_at) >= DATE(dogs.checkin)');
        })->orWhereHas('allergies')->with(['feedings', 'medications', 'allergies', 'cabin', 'dogServices.service'])
            ->orderBy('cabin_id')->orderBy('firstname')->get();


        if (now()->isSunday()) {
            $employees = $employees->filter(fn($e) => $e['next_first_break'] !== '10:00am' || $e['next_second_break'] !== null
            );

            $employees->prepend([
                'first_name' => 'Everyone',
                'next_first_break' => '10:00am',
                'next_lunch_break' => null,
                'next_second_break' => null,
            ]);
        }


        // In DD's infinite wisdom, child tables updated_at are being continually updated
        $md5Dogs = $dogs->map(function ($dog) {
            $dogData = collect($dog->toArray());
            $dogData['feedings'] = collect($dogData['feedings'])->map(function ($feeding) {
                return collect($feeding)->except(['created_at', 'updated_at'])->toArray();
            });
            $dogData['medications'] = collect($dogData['medications'])->map(function ($medication) {
                return collect($medication)->except(['created_at', 'updated_at'])->toArray();
            });
            $dogData['allergies'] = collect($dogData['allergies'])->map(function ($allergy) {
                return collect($allergy)->except(['created_at', 'updated_at'])->toArray();
            });
            return $dogData->toArray();
        });
        $new_checksum = md5(json_encode($assignments) . $employees . $md5Dogs);
        if ($checksum !== $new_checksum) {
            $response = [
                'breaks' => $employees,
                'dogs' => $dogs,
                'fohStaff' => Cache::get('foh_staff'),
                'assignments' => $assignments,
                'yards' => $yards,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);

    }

    function assignYard(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'rotation_id' => 'required|exists:rotations,id',
                'yard_id' => 'required|exists:yards,id',
                'homebase_user_id' => 'array',
                'homebase_user_id.*' => 'nullable|exists:employees,homebase_user_id',
            ]);

            $homebaseUserIds = $validatedData['homebase_user_id'] ?? [];

            if (!empty($homebaseUserIds)) {
                EmployeeYardRotation::where('rotation_id', $validatedData['rotation_id'])
                    ->whereNot('yard_id', $validatedData['yard_id'])
                    ->whereIn('homebase_user_id', $homebaseUserIds)->delete();

                EmployeeYardRotation::where('rotation_id', $validatedData['rotation_id'])
                    ->where('yard_id', $validatedData['yard_id'])
                    ->whereNotIn('homebase_user_id', $homebaseUserIds)->delete();

                $rows = collect($homebaseUserIds)->map(function ($userId) use ($validatedData) {
                    return [
                        'rotation_id' => $validatedData['rotation_id'],
                        'yard_id' => $validatedData['yard_id'],
                        'homebase_user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();
                DB::table('employee_yard_rotations')->insertOrIgnore($rows);
            } else {
                EmployeeYardRotation::where('rotation_id', $validatedData['rotation_id'])
                    ->where('yard_id', $validatedData['yard_id'])->delete();
            }


            return response()->json([
                'message' => 'Joy.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'There was a validation error.',
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    function assignBreak(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'next_first_break' => 'nullable|date_format:h:ia',
                'next_lunch_break' => 'nullable|date_format:h:ia',
                'next_second_break' => 'nullable|date_format:h:ia',
                'homebase_user_id' => 'required|exists:employees,homebase_user_id',
            ]);

            foreach (self::BREAK_COLUMNS as $field) {
                if (!empty($validatedData[$field])) {
                    $validatedData[$field] = Carbon::createFromFormat('h:ia', $validatedData[$field])
                        ->format('H:i:s');
                }
            }

            Shift::where('homebase_user_id', $validatedData['homebase_user_id'])
                ->update(Arr::only($validatedData, self::BREAK_COLUMNS));

            return response()->json([
                'message' => 'Happy.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'There was a validation error.',
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the assignment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    function yardmap(string $size, string $checksum = null): JsonResponse
    {
        $now = Carbon::now();
        $dogs = $this->getDogs(false, $size);
        $assignments = EmployeeYardRotation::join('rotations', 'employee_yard_rotations.rotation_id', '=', 'rotations.id')
            ->join('yards', 'employee_yard_rotations.yard_id', '=', 'yards.id')
            ->where('employee_yard_rotations.yard_id', '!=', 999)
            ->whereTime('rotations.start_time', '<=', $now)
            ->whereTime('rotations.end_time', '>=', $now)
            ->orderBy('yards.display_order')->with('employee', 'rotation', 'yard')->get();

        if ($now->isSunday() && $now->hour < 12) {
            $nextBreak = new \stdClass();
            $nextBreak->first_name = 'Everyone';
            $nextBreak->next_break = '10:00:00';
            $nextLunch = null;
        } else {
            $nextFirstBreak = Employee::whereNotNull('next_first_break')->whereTime('next_first_break', '>=', $now)
                ->orderBy('next_first_break')->first();
            $nextSecondBreak = Employee::whereNotNull('next_second_break')->whereTime('next_second_break', '>=', $now)
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

            $nextLunch = Employee::whereNotNull('next_lunch_break')->whereTime('next_lunch_break', '>=', $now)
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

    function groommap(string $checksum = null): JsonResponse
    {
        $dogs = $this->getGroomingDogsToday();
        $new_checksum = md5($dogs->toJson());
        if ($checksum !== $new_checksum) {
            return Response::json([
                'dogs' => $dogs,
                'checksum' => $new_checksum,
            ]);
        }
        return Response::json(false);
    }
}
