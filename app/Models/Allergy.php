<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allergy extends Model
{
    protected $table = 'allergies';
    protected $fillable = ['allergy_id', 'pet_id', 'description'];
    
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
    }
}
