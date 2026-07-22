<?php

namespace App\Http\Controllers;

use App\Models\BreakType;
use App\Models\Cabin;
use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\Feeding;
use App\Models\Timeslot;
use App\Models\Yard;
use App\Services\RotationSettings;
use App\Traits\ChoodTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TaskController extends Controller
{
    use ChoodTrait;

    public function index(): Response
    {
        return Inertia::render('Task/TaskEntry', [
            'cabins' => $this->getCabins(),
            'photoUri' => config('services.gingr.uris.photo'),
            'breakTypes' => BreakType::orderBy('display_order')->get(),
        ]);
    }

    // TODO: Add cool way to see status messages from others
    public function getData(?string $checksum = null): JsonResponse
    {
        $dogs = $this->getDogs(false, null, true);
        $yards = Yard::whereIn('id', RotationSettings::get()->allowedYards(false))
            ->orderBy('display_order')->get();
        $statuses = CleaningStatus::whereNull('completed_at')->pluck('cleaning_type', 'cabin_id')->toArray();
        $employees = Employee::whereHas('shifts', function ($query) {
            $query->where('start_time', '<=', now()->addHour())->where('end_time', '>=', now()->subHour());
        })->orderBy('first_name')->get();
        $new_checksum = md5($dogs->toJson() . $employees->toJson() . json_encode($statuses));
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'openYards' => $yards,
                'statuses' => $statuses,
                'employees' => $employees,
                'sectionCounts' => array_merge(
                    Cache::get('section_counts', ['checkin_today' => null, 'checkout_today' => null]),
                    ['in_house' => Dog::inHouse()->count()]
                ),
                'checksum' => $new_checksum,
            ];

            return response()->json($response);
        }
        return response()->json(false);
    }

    public function markCleaned(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'wiw_user_id' => 'required|exists:employees,wiw_user_id',
                'cabin_id' => 'required|exists:cabins,id',
                'is_cleaned' => 'required|boolean',
            ]);
            $isClean = $validatedData['is_cleaned'];

            $cleaningStatus = CleaningStatus::firstOrNew(['cabin_id' => $validatedData['cabin_id']]);
            if (!$cleaningStatus->exists) {
                $cleaningStatus->created_by = $isClean ? 'ApiMarkClean' : 'ApiMarkDirty';
                $cleaningStatus->created_at = Carbon::now();
            } else {
                $cleaningStatus->updated_by = $isClean ? 'ApiMarkClean' : 'ApiMarkDirty';
                $cleaningStatus->updated_at = Carbon::now();
            }
            $cleaningStatus->wiw_user_id = $validatedData['wiw_user_id'];
            $cleaningStatus->completed_at = $isClean ? now() : null;
            $cleaningStatus->save();

            if ($isClean) {
                Dog::where('cabin_id', $validatedData['cabin_id'])->whereNotNull('checked_out_at')->delete();
            }

            return response()->json([
                'message' => 'Cabin ' . $cleaningStatus->cabin->cabinName . ' successfully marked as ' . ($isClean ? 'clean' : 'dirty')], 200);
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
                    ['timeslot_id' => Timeslot::LUNCH, 'description' => $lunchNotes]
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
            'break_type_id' => 'required|exists:break_types,id',
        ]);

        Dog::whereIn('id', collect($validatedData['dogsToAssign'])->pluck('id'))->update([
            'rest_starts_at' => now()->startOfMinute(),
            'break_type_id' => $validatedData['break_type_id'],
        ]);

        $names = implode(',', collect($request->input('dogsToAssign'))->pluck('firstname')->filter()->values()->toArray());
        return response()->json(['message' => "Rest started for {$names}"]);
    }

    public function markReturned(string $dog_id): JsonResponse
    {
        $dog = Dog::with('breakType')->findOrFail($dog_id);

        if ($dog->breakType?->behavior === 'walks_only' && $dog->rest_starts_at) {
            $elapsed = $dog->rest_starts_at->diffInMinutes(now());
            if ($elapsed >= $dog->breakType->duration_minutes) {
                // Was showing "Time for Walk" — reset the timer
                $dog->update(['rest_starts_at' => now()->startOfMinute()]);
                return response()->json(['message' => "Walk timer reset for {$dog->firstname}"]);
            }
        }

        $dog->update(['rest_starts_at' => null, 'break_type_id' => null]);
        return response()->json(['message' => "Marked {$dog->firstname} as returned to yard"]);
    }

    /**
     * @throws Throwable
     */
    public function clearFeedingCabin(Request $request): JsonResponse
    {
        $request->validate(['cabin_id' => 'required|exists:cabins,id']);
        Dog::whereNull('pet_id')->where('cabin_id', $request->input('cabin_id'))->delete();
        return response()->json(['message' => 'Feeding cabin cleared']);
    }

    public function assignFeedingCabin(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cabin_id' => 'required|exists:cabins,id',
                'dogsToAssign.id' => 'required|exists:dogs,id',
            ]);

            $dog = Dog::findOrFail($request->input('dogsToAssign.id'));
            $cabin = Cabin::findOrFail($request->input('cabin_id'));

            Dog::firstOrCreate(
                ['cabin_id' => $cabin->id, 'account_id' => $dog->account_id, 'pet_id' => null],
                ['display_name' => $dog->display_name, 'firstname' => $dog->firstname, 'lastname' => $dog->lastname, 'photoUri' => $dog->photoUri]
            );

            return response()->json(['message' => "Feeding cabin set for {$dog->display_name}"]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors(), 'message' => 'Validation error.'], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Not found.', 'error' => $e->getMessage()], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Unknown error.', 'error' => $e->getMessage()], 500);
        }
    }

    public function moveDogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'yardsToAssign' => ['required', 'array', 'min:1'],
            'yardsToAssign.*.dog_id' => ['required', 'integer', 'exists:dogs,id'],
            'yardsToAssign.*.yard_id' => ['required', 'integer', 'exists:yards,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['yardsToAssign'] as $move) {
                Dog::where('id', $move['dog_id'])->update(['yard_id' => $move['yard_id']]);
            }
        });

        return response()->json(['message' => "Assigned dogs to yard"]);
    }

}
