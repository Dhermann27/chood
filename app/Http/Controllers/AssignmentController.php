<?php

namespace App\Http\Controllers;

use App\Models\Dog;
use App\Models\DogService;
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
                'service_ids.*.id' => 'required|exists:services,id'
            ]);

            $filteredValues = array_filter($validatedData, function ($value) {
                return !is_null($value);
            });

            if (array_key_exists('dogs', $filteredValues)) {
                foreach ($filteredValues['dogs'] as $dog) {
                    $dog = Dog::updateOrCreate(['id' => $dog['id']], ['cabin_id' => $filteredValues['cabin_id']]);

                    $this->createServices($filteredValues, $dog->id);
                }
            } else {
                $filteredValues['is_inhouse'] = 0;
                $dog = Dog::create($filteredValues);
                $this->createServices($filteredValues, $dog->id);
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
            'service_ids.*.id' => 'nullable|exists:services,id'
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
            if (array_key_exists('service_ids', $filteredValues)) {
                $serviceIds = collect($filteredValues['service_ids'])->pluck('id')->toArray();

                // Delete services that are not in the new list
                DogService::where('dog_id', $dog->id)->whereNotIn('service_id', $serviceIds)->delete();

                foreach ($filteredValues['service_ids'] as $service_id) {
                    DogService::updateOrCreate(['dog_id' => $dog->id, 'service_id' => $service_id['id']]);
                }
            }
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

    /**
     * @param array $values
     * @param int $dogId
     * @return void
     */
    private function createServices(array $values, int $dogId): void
    {
        if (array_key_exists('service_ids', $values)) {
            foreach ($values['service_ids'] as $service_id) {
                DogService::updateOrCreate(['dog_id' => $dogId, 'service_id' => $service_id['id']]);
            }
        }
    }
}
