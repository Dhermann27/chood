<?php

namespace App\Http\Controllers;

use App\Traits\ChoodTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ApiController extends Controller
{
    use ChoodTrait;

    function fullmap(string $checksum): JsonResponse
    {
        $dogs = $this->getDogs(true)->mapWithKeys(function ($dog) {
            return [$dog->cabin_id => $dog];
        });
        $new_checksum = md5($dogs->toJson());
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,         // The original collection of dogs
                'checksum' => $new_checksum, // The computed checksum
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
                'dogs' => $dogs,         // The original collection of dogs
                'checksum' => $new_checksum, // The computed checksum
            ];

            return Response::json($response);
        }
        return Response::json(false);

    }
}
