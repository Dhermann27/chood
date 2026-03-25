<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medication extends Model
{
    protected $fillable = ['medication_id', 'pet_id', 'type_id', 'type', 'timeslot_id', 'quantity', 'unit', 'start_date', 'end_date', 'description'];
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
    }
}
