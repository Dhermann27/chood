<?php

namespace App\Models;

use App\Enums\HousingServiceCodes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = ['gingr_id', 'name', 'category', 'code', 'housing_code', 'duration', 'is_active'];

    public $timestamps = false;

    protected $casts = [
        'housing_code' => HousingServiceCodes::class,
    ];


    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
