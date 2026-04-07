<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cabin extends Model
{
    public $timestamps = false;
    protected $appends = ['short_name'];

    public function getShortNameAttribute()
    {
        $patterns = ['/Luxury Suite /', '/^Cabin 0*(\d+) - \d+x\d+$/', '/Teacup Condo /'];
        $replacements = ['L', '$1', 'T'];
        return preg_replace($patterns, $replacements, $this->cabinName);
    }

    public function dogs(): HasMany
    {
        return $this->hasMany(Dog::class);
    }


    public function cleaningStatus(): HasOne
    {
        return $this->hasOne(CleaningStatus::class);
    }

}
