<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RestBreak extends Model
{
    protected $fillable = ['pet_id', 'dog_name', 'break_type_id', 'rest_minutes', 'started_at', 'ended_at'];
    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function breakType(): BelongsTo
    {
        return $this->belongsTo(BreakType::class);
    }

    public function reasons(): BelongsToMany
    {
        return $this->belongsToMany(BreakReason::class, 'rest_break_reasons');
    }

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
    }
}
