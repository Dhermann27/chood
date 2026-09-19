<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Yard extends Model
{
    public $timestamps = false;
    protected $casts = ['is_large' => 'boolean'];

    public function employeeYardRotations(): HasMany
    {
        return $this->hasMany(EmployeeYardRotation::class);
    }

}
