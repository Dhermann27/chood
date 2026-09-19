<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $primaryKey = 'wiw_user_id';
    protected $fillable = ['wiw_user_id', 'first_name', 'last_name'];

    public function employeeYardRotations(): HasMany
    {
        return $this->hasMany(EmployeeYardRotation::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'wiw_user_id', 'wiw_user_id');
    }

}
