<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['homebase_user_id', 'start_time', 'end_time', 'is_working',
        'next_first_break', 'next_lunch_break', 'next_second_break'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'homebase_user_id', 'homebase_user_id');
    }
}
