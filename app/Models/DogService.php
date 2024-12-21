<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DogService extends Model
{
    protected $table = 'dog_service';
    protected $fillable = ['dog_id', 'service_id', 'completed'];

    public function dog(): HasOne
    {
        return $this->hasOne(Dog::class, 'id', 'dog_id');
    }

    public function service(): HasOne
    {
        return $this->hasOne(Service::class, 'id', 'service_id');
    }

}
