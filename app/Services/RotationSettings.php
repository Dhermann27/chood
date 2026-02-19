<?php

namespace App\Services;

use App\Enums\YardCodes;
use Illuminate\Support\Facades\Cache;

class RotationSettings
{
    public static function key(): string
    {
        return 'yard_preset:' . today()->toDateString();
    }

    public static function get(): YardCodes
    {
        $value = Cache::get(self::key());

        return YardCodes::tryFrom($value) ?? YardCodes::DEFAULT;
    }

    public static function put(YardCodes $preset): void
    {
        Cache::put(self::key(), $preset->value, now()->endOfDay());
    }
}

