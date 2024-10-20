<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cabin extends Model
{
    public $timestamps = false;

    public function dogs() : HasMany
    {
        return $this->hasMany(Dog::class);
    }


    public function cleaning_status() : HasOne
    {
        return $this->hasOne(CleaningStatus::class);
    }

}
