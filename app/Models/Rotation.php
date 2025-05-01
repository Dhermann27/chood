<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rotation extends Model
{
    public $timestamps = false;

    public function employeeYardRotations()
    {
        return $this->hasMany(EmployeeYardRotation::class);
    }

}
