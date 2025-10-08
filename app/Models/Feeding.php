<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feeding extends Model
{
    protected $fillable = ['feeding_id', 'pet_id', 'type', 'description', 'is_task', 'modified_at'];
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
    }
}
