<?php

namespace App\Http\Controllers;

use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\YardAssignment;
use App\Traits\ChoodTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use stdClass;

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

        $employees = Employee::whereNotNull('next_first_break')->orderBy('first_name')->get()
            ->map(function ($employee) {
                if ($employee->next_first_break) {
                    $employee->next_first_break = Carbon::parse($employee->next_first_break)->format('h:ia');
                }
                if ($employee->next_lunch_break) {
                    $employee->next_lunch_break = Carbon::parse($employee->next_lunch_break)->format('h:ia');
                }
                if ($employee->next_second_break) {
                    $employee->next_second_break = Carbon::parse($employee->next_second_break)->format('h:ia');
                }
                return $employee;
            });
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
            $nextBreak->next_first_break = '10:00am';
            $employees->unshift($nextBreak);
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
        $new_checksum = md5($assignments . $employees . $md5Dogs);
        if ($checksum !== $new_checksum) {
            $response = [
                'breaks' => $employees,
                'dogs' => $dogs,
                'fohStaff' => Cache::get('foh_staff'),
                'hours' => $assignments,
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
                'hour' => 'required|exists:yard_assignments,start_time',
                'yard' => 'required|exists:yard_assignments,yard_number',
                'homebase_user_id' => 'nullable|exists:employees,homebase_user_id',
            ]);

            $yas = YardAssignment::where('start_time', $validatedData['hour'])->get();

            if ($validatedData['homebase_user_id']) {
                $ya = $yas->firstWhere('yard_number', 99);
                $floaters = explode(', ', $ya->description);
                $employee = Employee::find($validatedData['homebase_user_id']);
                $floaters = array_filter($floaters, function ($name) use ($employee) {
                    return trim($name) !== $employee->first_name;
                });
                $ya->update(['description' => count($floaters) > 0 ? implode(', ', $floaters) : null]);
            }

            $yas->firstWhere('yard_number', $validatedData['yard'])
                ->update(['homebase_user_id' => $validatedData['homebase_user_id']]);

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
                'homebase_user_id' => 'nullable|exists:employees,homebase_user_id',
            ]);

            foreach (self::BREAK_COLUMNS as $field) {
                if (!empty($validatedData[$field])) {
                    $validatedData[$field] = Carbon::createFromFormat('h:ia', $validatedData[$field])->format('H:i:s');
                }
            }

            Employee::findOrFail($validatedData['homebase_user_id'])->update(
                Arr::only($validatedData, self::BREAK_COLUMNS)
            );

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
