<?php

namespace App\Http\Controllers;

use App\Models\CleaningStatus;
use App\Models\Employee;
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
            'employees' => Employee::orderBy('first_name')->get(),
            'cabins' => $this->getCabins(),
            'statuses' => CleaningStatus::whereNull('completed_at')->pluck('cleaning_type', 'cabin_id')->toArray()
        ]);
    }

    // TODO: Add cool way to see status messages from others
    function getData(string $checksum = null): JsonResponse
    {
        $statuses = CleaningStatus::whereNull('completed_at')->pluck('cleaning_type', 'cabin_id')->toArray();
        $employees = Employee::orderBy('first_name')->get();
        $new_checksum = md5($employees->toJson() . json_encode($statuses));
        if ($checksum !== $new_checksum) {
            $response = [
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
                'homebase_id' => 'required|exists:employees,homebase_id',
                'cabin_id' => 'required|exists:cabins,id'
            ]);
            $cleaningStatus = CleaningStatus::where('cabin_id', $validatedData['cabin_id'])->firstOrFail();
            if ($cleaningStatus->cleaning_type == CleaningStatus::STATUS_DEEP && Carbon::today()->isSunday()) {
                $cleaningStatus->delete();
            } else {
                $cleaningStatus->update([
                    'homebase_id' => $validatedData['homebase_id'],
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
}
