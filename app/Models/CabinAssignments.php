<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CabinAssignments extends Model
{
    protected $fillable = ['name', 'dog_id', 'cabin_id', 'service_ids'];

    public function dog() : HasOne {
        return $this->hasOne(Dog::class);
    }

    public function cabin() : HasOne {
        return $this->hasOne(Cabin::class);
    }


    public function services() : HasMany {
        return $this->hasMany(Service::class);
    }
}
