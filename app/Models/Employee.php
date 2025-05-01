<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $primaryKey = 'homebase_user_id';
    protected $fillable = ['homebase_user_id', 'first_name', 'last_name', 'is_working',
        'next_first_break', 'next_lunch_break', 'next_second_break'];

    public function employeeYardRotations()
    {
        return $this->hasMany(EmployeeYardRotation::class);
    }
}
