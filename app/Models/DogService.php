<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DogService extends Model
{
    protected $fillable = ['pet_id', 'service_id', 'scheduled_start', 'completed_at', 'completed_by'];

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

}
