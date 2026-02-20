<?php

namespace App\Http\Controllers;

use App\Enums\YardCodes;
use App\Jobs\Homebase\GoFetchShiftsJob;
use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\EmployeeYardRotation;
use App\Models\Feeding;
use App\Models\Rotation;
use App\Models\RotationYardView;
use App\Models\Shift;
use App\Services\RotationSettings;
use App\Traits\ChoodTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class DataController extends Controller
{
    const array BREAK_COLUMNS = ['next_first_break', 'next_lunch_break', 'next_second_break'];
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
        $preset = RotationSettings::get();

        $rotations = Rotation::query()
            ->when(now()->isSunday(), fn($q) => $q->where('is_sunday_hour', 1))
            ->orderBy('start_time')->get(['id', 'is_midday']);

        $openByRotation = $rotations->mapWithKeys(function ($r) use ($preset) {
            $allowed = $preset->allowedYards((bool)$r->is_midday);
            $allowed[] = 999;
            return [$r->id => array_values(array_unique($allowed))];
        });

        $headerYardIds = $openByRotation->flatten()->unique()->values();

        $assignments = RotationYardView::query()->get(['rotation_id', 'yard_id', 'homebase_user_id', 'first_name'])
            ->groupBy('rotation_id')->map(fn($group) => $group->mapWithKeys(fn($row) => [
                $row->yard_id => $row->homebase_user_id ?
                    ['homebase_user_id' => $row->homebase_user_id, 'first_name' => $row->first_name,] : null,
            ]));


        $breaks = Shift::where('role', 'Camp Counselor')->orWhere('role', 'Shift Supervisor')
            ->with('employee')->get()->map(function ($shift) {
                return [
                    'homebase_user_id' => $shift->employee->homebase_user_id,
                    'first_name' => $shift->employee->first_name,
                    'shift_start' => $shift->start_time,
                    'shift_end' => $shift->end_time,
                    'next_first_break' => optional($shift->next_first_break ? Carbon::parse($shift->next_first_break) : null)->format('h:ia'),
                    'next_lunch_break' => optional($shift->next_lunch_break ? Carbon::parse($shift->next_lunch_break) : null)->format('h:ia'),
                    'next_second_break' => optional($shift->next_second_break ? Carbon::parse($shift->next_second_break) : null)->format('h:ia'),
                ];
            })->sortBy('first_name')->values();

//        $dogs = Dog::where(function ($query) {
//            $query->whereHas('feedings', function ($q) {
//                $q->where(function ($sub) {
//                    $sub->whereRaw('DATE(feedings.modified_at) <= DATE(dogs.checkin)')
//                        ->orWhereHas('dog.appointments.service', function ($s) {
//                            $s->where('code', 'like', '%BRD%');
//                        });
//                });
//            })->orWhereHas('medications', function ($q) {
//                $q->whereRaw('DATE(medications.modified_at) >= DATE(dogs.checkin)');
//            })->orWhereHas('allergies');
//        })->with(['feedings', 'medications', 'allergies', 'cabin', 'appointments.service'])
//            ->orderBy('cabin_id')->orderBy('firstname')->get();


        $lunchDogs = Feeding::select('id', 'pet_id', 'type', 'description')->whereHas('dog')->where(function ($query) {
            $query->where('is_task', 1)->orWhereRaw('LOWER(description) LIKE ?', ['%lunch%']);
        })->with('dog')->get()->unique('pet_id')->map(function ($feeding) {
            $dog = $feeding->dog;
            if (!$dog) return null; // safety
            $dog->lunch_notes = $feeding->description;
            return $dog;
        })->filter()->sortBy('cabin_id')->values();

        $medicatedDogs = Dog::where(function ($query) {
            $query->whereHas('medications')->orWhereHas('allergies');
        })->with('medications', 'allergies')->orderBy('cabin_id')->get();

        $groupedEmployees = Employee::with('shifts')->orderBy('first_name')->get()
            ->groupBy(function ($employee) {
                return $employee->shifts->contains('is_working', 1) ? 'Scheduled' : 'Unscheduled';
            })->map(function ($group, $status) {
                return ['status' => $status, 'employees' => $group];
            })->sortBy(fn($group) => $group['status'] === 'Scheduled' ? 0 : 1)->values()->all();

        // In DD's infinite wisdom, child tables updated_at are being continually updated
        $md5Dogs = $medicatedDogs->map(function ($dog) {
            $dogData = collect($dog->toArray());
            $dogData['medications'] = collect($dogData['medications'])->map(function ($medication) {
                return collect($medication)->except(['created_at', 'updated_at'])->toArray();
            });
            $dogData['allergies'] = collect($dogData['allergies'])->map(function ($allergy) {
                return collect($allergy)->except(['created_at', 'updated_at'])->toArray();
            });
            return $dogData->toArray();
        });
        $new_checksum = md5(json_encode($assignments) . json_encode($breaks) . json_encode($groupedEmployees)
            . json_encode($lunchDogs) . $md5Dogs . $preset->value . json_encode($headerYardIds)
            . json_encode($openByRotation));
        if ($checksum !== $new_checksum) {
            $response = [
                'assignments' => $assignments,
                'breaks' => $breaks,
                'lunchDogs' => $lunchDogs,
                'medicatedDogs' => $medicatedDogs,
                'employees' => $groupedEmployees,
                'fohStaff' => Cache::get('foh_staff'),
                'preset' => $preset->value,
                'headerYards' => $headerYardIds,
                'openYardsByRotation' => $openByRotation,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);

    }

    function markActiveYards(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'preset' => 'required|string|in:default,three-two,three,four-two,four-three,four',
                'overwrite' => 'required|boolean'
            ]);

            RotationSettings::put(YardCodes::from($validatedData['preset']));

            if ($validatedData['overwrite']) {
                GoFetchShiftsJob::dispatchSync();
            }

            return response()->json([
                'message' => 'Noice.',
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

    public function assignYard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rotation_id' => ['required', 'exists:rotations,id'],
            'yard_id' => ['required', 'exists:yards,id'],
            'homebase_user_id' => ['nullable', 'exists:employees,homebase_user_id'],
        ]);

        $rotationId = $validated['rotation_id'];
        $yardId = $validated['yard_id'];
        $homebaseUserId = $validated['homebase_user_id'] ?? null;

        // Unassign
        if ($homebaseUserId === null) {
            EmployeeYardRotation::where('rotation_id', $rotationId)
                ->where('yard_id', $yardId)
                ->delete();

            return response()->json(['message' => 'Joy.'], 200);
        }

        // Ensure employee is not assigned to a different yard this hour
        EmployeeYardRotation::where('rotation_id', $rotationId)
            ->where('homebase_user_id', $homebaseUserId)
            ->where('yard_id', '!=', $yardId)
            ->delete();

        // Set (replace whatever is currently in this slot)
        EmployeeYardRotation::where('rotation_id', $rotationId)
            ->where('yard_id', $yardId)
            ->delete();

        EmployeeYardRotation::create([
            'rotation_id' => $rotationId,
            'yard_id' => $yardId,
            'homebase_user_id' => $homebaseUserId,
        ]);

        return response()->json(['message' => 'Joy.'], 200);
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
            ->whereTime('rotations.start_time', '<=', $now)
            ->whereTime('rotations.end_time', '>=', $now)
            ->orderBy('yards.display_order')->with('employee', 'rotation', 'yard')->get();

        if ($now->isSunday() && $now->hour < 12) {
            $nextBreak = [
                'employee' => ['first_name' => 'Everyone'],
                'next_break' => '10:00:00',
            ];
            $nextLunch = null;
        } else {
            $nextFirstBreak = Shift::with('employee')->whereNotNull('next_first_break')
                ->whereTime('next_first_break', '>=', $now)
                ->orderBy('next_first_break')->first();
            $nextSecondBreak = Shift::with('employee')->whereNotNull('next_second_break')
                ->whereTime('next_second_break', '>=', $now)
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

            $nextLunch = Shift::with('employee')->whereNotNull('next_lunch_break')
                ->whereTime('next_lunch_break', '>=', $now)
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
