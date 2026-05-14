<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Yard extends Model
{
    public $timestamps = false;
    protected $casts = ['is_large' => 'boolean'];

    public function employeeYardRotations()
    {
        return $this->hasMany(EmployeeYardRotation::class);
    }

}
