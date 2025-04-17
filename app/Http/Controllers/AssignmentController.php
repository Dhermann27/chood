<?php

namespace App\Http\Controllers;

use App\Models\Dog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;


class AssignmentController extends Controller
{
    public function storeAssignment(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'firstname' => 'nullable|string|max:255|required_without:dogs',
                'lastname' => 'nullable|string|max:255|required_without:dogs',
                'dogs.*.id' => 'nullable|exists:dogs,id',
                'cabin_id' => 'required|exists:cabins,id',
            ]);

            $filteredValues = array_filter($validatedData, function ($value) {
                return !is_null($value);
            });

            if (array_key_exists('dogs', $filteredValues)) {
                foreach ($filteredValues['dogs'] as $dog) {
                    $dog = Dog::updateOrCreate(['id' => $dog['id']], ['cabin_id' => $filteredValues['cabin_id']]);
                }
            } else {
                $filteredValues['is_inhouse'] = 0;
                Dog::create($filteredValues);
            }

            return response()->json([
                'message' => 'Great success.',
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

    public function updateAssignment(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'id' => 'nullable|exists:dogs,id',
            'cabin_id' => 'required|exists:cabins,id',
            'dogs.*.id' => 'nullable|exists:dogs,id',
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
        ]);

        $filteredValues = array_filter($validatedData, function ($value) {
            return !is_null($value);
        });

        if (array_key_exists('dogs', $filteredValues)) {
            foreach ($filteredValues['dogs'] as $dog) {
                $dog = Dog::updateOrCreate(['id' => $dog['id']], ['cabin_id' => $filteredValues['cabin_id']]);
            }
        } else {
            $dog = Dog::updateOrCreate(['id' => $filteredValues['id']], $filteredValues);
        }

        return response()->json('Very nice', 200);
    }

    public function deleteAssignment(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'dogs.*.id' => 'nullable|exists:dogs,id',
        ]);
        $ids = collect($validatedData['dogs'])->pluck('id')->toArray();
        return response()->json(Dog::whereIn('id', $ids)->delete(), 200);
    }
}
