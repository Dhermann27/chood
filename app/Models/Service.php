<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Service extends Model
{
    protected $fillable = ['dd_id', 'name', 'category', 'code', 'duration', 'is_active'];

    public $timestamps = false;


    public function dogServices(): HasMany
    {
        return $this->hasMany(DogService::class);
    }

    public function dogs(): HasManyThrough
    {
        return $this->hasManyThrough(Dog::class, DogService::class, 'service_id', 'id', 'id', 'dog_id');
    }
}
