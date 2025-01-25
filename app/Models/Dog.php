<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['pet_id', 'firstname', 'lastname', 'gender', 'photoUri', 'weight', 'cabin_id',
        'is_inhouse', 'checkout'];

    protected $casts = ['checkout' => 'datetime'];

    protected $appends = ['size_letter'];

    public function getSizeLetterAttribute()
    {
        if ($this->weight == null) return '?';
        else if ($this->weight > 40) return 'L';
        else if ($this->weight >= 30) return 'M';
        else if ($this->weight >= 10) return 'S';
        else return 'XS';
    }

    public function cabin(): HasOne
    {
        return $this->hasOne(Cabin::class);
    }


    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
