<?php

namespace App\Http\Controllers;

use App\Models\Dog;
use App\Models\DogService;
use App\Traits\ChoodTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    use ChoodTrait;

    const ERROR_MESSAGES = ['name.required_without' => 'You must specify the dog\'s name with no dog selected.',
    ];

    function fullmap(string $checksum): JsonResponse
    {
        $dogs = $this->getDogsByCabin();
        $new_checksum = md5($dogs->toJson());
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);
    }

    function yardmap(string $size, string $checksum): JsonResponse
    {
        $sizes = $size === 'small' ? ['Medium', 'Small', 'Extra Small'] : ['Medium', 'Large', 'Extra Large'];
        $dogs = $this->getDogs(false, $sizes);

        $new_checksum = md5($dogs->toJson());
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,
                'checksum' => $new_checksum,
            ];

            return Response::json($response);
        }
        return Response::json(false);

    }

    public function storeAssignment(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'firstname' => 'nullable|string|max:255|required_without:dogs',
                'lastname' => 'nullable|string|max:255|required_without:dogs',
                'dogs.*.id' => 'nullable|exists:dogs,id',
                'cabin_id' => 'required|exists:cabins,id',
                'service_ids.*.id' => 'required|exists:services,id'
            ], self::ERROR_MESSAGES);

            $filteredValues = array_filter($validatedData, function ($value) {
                return !is_null($value);
            });

            $dog = null;
            if (array_key_exists('dogs', $filteredValues)) {
                $dog = Dog::updateOrCreate(['id' => $filteredValues['dogs'][0]['id']], $filteredValues);
            } else {
                $filteredValues['is_inhouse'] = 0;
                $dog = Dog::create($filteredValues);
            }

            if(array_key_exists('service_ids', $filteredValues)) {
                foreach ($filteredValues['service_ids'] as $service_id) {
                    DogService::updateOrCreate(['dog_id' => $dog->id, 'service_id' => $service_id['id']]);
                }
            }

            return response()->json($dog, 200);
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

    public function updateAssignment(Request $request, $id): JsonResponse
    {
        $validatedData = $request->validate([
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'dogs.0.pet_id' => 'nullable|exists:dogs,pet_id',
            'cabin_id' => 'required|exists:cabins,id',
            'service_ids.*.id' => 'required|exists:services,id'
        ]);

        $dog = Dog::findOrFail($id)->update([
            'firstname' => $validatedData['firstname'],
            'lastname' => $validatedData['lastname'],
            'pet_id' => $validatedData['dogs'][0]['pet_id'],
            'cabin_id' => $validatedData['cabin_id'],
        ]);

        $serviceIds = collect($validatedData['service_ids'])->pluck('id')->toArray();
        $currentServiceIds = DogService::where('dog_id', $id)->pluck('service_id')->toArray();
        $idsToDelete = array_diff($currentServiceIds, $serviceIds);
        if (!empty($idsToDelete)) {
            DogService::where('dog_id', $id)->whereIn('service_id', $idsToDelete)->delete();
        }

        foreach ($validatedData['service_ids'] as $service_id) {
            DogService::updateOrCreate(['dog_id' => $id, 'service_id' => $service_id['id']]);
        }

        return response()->json($dog, 200);
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
