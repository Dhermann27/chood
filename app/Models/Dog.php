<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['accountId', 'pet_id', 'firstname', 'lastname', 'gender', 'photoUri', 'weight', 'cabin_id',
        'is_inhouse', 'checkin', 'checkout'];

    protected $casts = ['checkin' => 'datetime', 'checkout' => 'datetime'];

    protected $appends = ['size_letter'];

    public function getSizeLetterAttribute()
    {
        if ($this->weight == null) return '?';
        else if ($this->weight > 40) return 'L';
        else if ($this->weight >= 30) return 'M';
        else if ($this->weight >= 10) return 'S';
        else return 'XS';
    }

    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class, 'cabin_id');
    }

    public function feedings(): HasMany
    {
        return $this->hasMany(Feeding::class, 'pet_id', 'pet_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
