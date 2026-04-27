<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = ['wiw_user_id', 'role', 'start_time', 'end_time',
        'next_first_break', 'next_lunch_break', 'next_second_break', 'fairness_score'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'wiw_user_id', 'wiw_user_id');
    }
}
