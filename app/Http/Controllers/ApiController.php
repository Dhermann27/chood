<?php

namespace App\Http\Controllers;

use App\Models\CleaningStatus;
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

    function yardmap(string $size, string $checksum = null): JsonResponse
    {
        $dogs = $this->getDogs(false, $size);

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
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'dogs.*.id' => 'nullable|exists:dogs,id',
            'cabin_id' => 'required|exists:cabins,id',
            'service_ids.*.id' => 'required|exists:services,id'
        ]);

        $filteredValues = array_filter($validatedData, function ($value) {
            return !is_null($value);
        });

        if (array_key_exists('dogs', $filteredValues)) {
            foreach ($filteredValues['dogs'] as $dog) {
                $dog = Dog::updateOrCreate(['id' => $dog['id']], [
                    'firstname' => $filteredValues['firstname'],
                    'lastname' => $filteredValues['lastname'],
                    'pet_id' => $dog['pet_id'],
                    'cabin_id' => $filteredValues['cabin_id'],
                ]);

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
     * @param $dog
     * @return void
     */
    public function createServices(array $values, int $dogId): void
    {
        if (array_key_exists('service_ids', $values)) {
            foreach ($values['service_ids'] as $service_id) {
                DogService::updateOrCreate(['dog_id' => $dogId, 'service_id' => $service_id['id']]);
            }
        }
    }

}
