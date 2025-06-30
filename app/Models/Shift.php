<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = ['homebase_user_id', 'role', 'start_time', 'end_time', 'is_working',
        'next_first_break', 'next_lunch_break', 'next_second_break', 'fairness_score'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'homebase_user_id', 'homebase_user_id');
    }
}
