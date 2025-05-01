<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeYardRotation extends Model
{
    protected $fillable = ['homebase_user_id', 'yard_id', 'rotation_id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'homebase_user_id', 'homebase_user_id');
    }

    public function yard()
    {
        return $this->belongsTo(Yard::class);
    }

    public function rotation()
    {
        return $this->belongsTo(Rotation::class);
    }

}
