<?php

namespace App\Http\Controllers;

use App\Models\CleaningStatus;
use App\Models\Dog;
use App\Traits\ChoodTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class DataController extends Controller
{
    use ChoodTrait;

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
}
