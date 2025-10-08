<?php

namespace App\Http\Controllers;

use App\Models\Cabin;
use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\Feeding;
use App\Traits\ChoodTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TaskController extends Controller
{
    use ChoodTrait;

    public function index(): \Inertia\Response
    {
        return Inertia::render('Task/TaskEntry', [
            'cabins' => $this->getCabins(),
            'photoUri' => config('services.dd.uris.photo'),
        ]);
    }

    // TODO: Add cool way to see status messages from others
    function getData(string $checksum = null): JsonResponse
    {
        $dogs = $this->getDogs();
        $statuses = CleaningStatus::whereNull('completed_at')->pluck('cleaning_type', 'cabin_id')->toArray();
        $employees = Employee::whereHas('shifts', function ($query) {
            $query->where('is_working', true);
        })->orderBy('first_name')->get();
        $new_checksum = md5($dogs->toJson() . $employees->toJson() . json_encode($statuses));
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'statuses' => $statuses,
                'employees' => $employees,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);
    }

    public function markCleaned(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'homebase_user_id' => 'required|exists:employees,homebase_user_id',
                'cabin_id' => 'required|exists:cabins,id'
            ]);
            $cleaningStatus = CleaningStatus::where('cabin_id', $validatedData['cabin_id'])->firstOrFail();
            if ($cleaningStatus->cleaning_type == CleaningStatus::STATUS_DEEP && Carbon::today()->isSunday()) {
                $cleaningStatus->delete();
            } else {
                $cleaningStatus->update([
                    'homebase_user_id' => $validatedData['homebase_user_id'],
                    'completed_at' => Carbon::now(),
                    'updated_by' => 'ApiMarkClean',
                    'updated_at' => Carbon::now()]);
            }
            return response()->json([
                'message' => 'Cabin ' . $cleaningStatus->cabin->cabinName . ' successfully marked as clean'], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->errors(),
                'message' => 'There was a validation error.',
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Cleaning status was not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Unknown error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function assignDogsToCabin(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'cabin_id' => 'required|exists:cabins,id',
                'dogsToAssign.*.id' => 'required|exists:dogs,id',
            ]);

            $cabin = Cabin::findOrFail($validatedData['cabin_id']);
            $dogs = collect($validatedData['dogsToAssign']);
            $names = collect($request->input('dogsToAssign'))->pluck('firstname')->filter()->values()->toArray();
            Dog::whereIn('id', $dogs->pluck('id')->toArray())->update(['cabin_id' => $validatedData['cabin_id']]);
            return response()->json([
                'message' => implode(', ', $names) . ' assigned to Cabin ' . $cabin->cabinName], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->errors(),
                'message' => 'There was a validation error.',
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Cabin was not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Unknown error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setLunch(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'dogsToAssign.*.pet_id' => 'required|exists:dogs,pet_id',
            'lunch_notes' => 'nullable|string|max:255',
        ]);
        $lunchNotes = trim($request->input('lunch_notes'));
        $petIds = collect($validatedData['dogsToAssign'])->pluck('pet_id')->filter()->unique()->values();
        if ($lunchNotes != '') {
            foreach ($petIds as $petId) {
                Feeding::updateOrCreate(
                    ['pet_id' => $petId, 'is_task' => 1],
                    ['description' => $lunchNotes]
                );
            }
        } else {
            Feeding::whereIn('pet_id', $petIds)->where('is_task', '1')->delete();
        }

        $names = implode(',', collect($request->input('dogsToAssign'))->pluck('firstname')->filter()->values()->toArray());
        return response()->json(['message' => "Lunch set for {$names}"]);
    }

    public function startBreak(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'dogsToAssign.*.id' => 'required|exists:dogs,id',
            'break_duration' => 'required|numeric',
        ]);

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $onePmToday = Carbon::today($tz)->setTime(13, 0);
        $minutesUntil1pm = max(0, $now->diffInMinutes($onePmToday, false));

        Dog::whereIn('id', collect($validatedData['dogsToAssign']))->update([
            'rest_starts_at' => now(),
            'rest_duration_minutes' => $validatedData['break_duration'] === '1000' ? $minutesUntil1pm : $validatedData['break_duration'],
        ]);

        $names = implode(',', collect($request->input('dogsToAssign'))->pluck('firstname')->filter()->values()->toArray());
        return response()->json(['message' => "Rest started for {$names}"]);
    }

    public function markReturned(string $dog_id): JsonResponse
    {
        $dog = Dog::findOrFail($dog_id);
        $dog->update([
            'rest_starts_at' => null,
            'rest_duration_minutes' => null,
        ]);

        return response()->json(['message' => "Marked {$dog->firstname} as returned to yard"]);
    }

}
