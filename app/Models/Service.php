<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = ['dd_id', 'name', 'category', 'code', 'duration', 'is_active'];

    public $timestamps = false;


    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
