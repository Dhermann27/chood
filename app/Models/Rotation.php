<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rotation extends Model
{
    public $timestamps = false;

    public function scopeForToday(Builder $query): Builder
    {
        return $query->when(now()->isSunday(), fn($q) => $q->where('is_sunday_hour', 1))
            ->orderBy('start_time');
    }

    public function employeeYardRotations(): HasMany
    {
        return $this->hasMany(EmployeeYardRotation::class);
    }

}
