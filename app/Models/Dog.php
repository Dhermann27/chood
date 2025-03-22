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

    protected $appends = ['size_letter', 'left_icons'];

    public function getSizeLetterAttribute()
    {
        if ($this->weight == null) return '?';
        else if ($this->weight > 40) return 'L';
        else if ($this->weight >= 30) return 'M';
        else if ($this->weight >= 10) return 'S';
        else return 'XS';
    }

    public function getLeftIconsAttribute()
    {
        $icons = [];

        // Add icon data based on some conditions
        if ($this->cabin && $this->cabin->short_name) {
            $icons[] = ['icon' => 'house-chimney-blank', 'text' => $this->cabin->short_name];
        }
        if ($this->gender) {
            $icons[] = ['icon' => $this->gender == 'M' ? 'mars' : 'venus'];
        }

        return $icons;
    }
    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class, 'pet_id', 'pet_id');
    }

    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class, 'cabin_id');
    }

    public function feedings(): HasMany
    {
        return $this->hasMany(Feeding::class, 'pet_id', 'pet_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class, 'pet_id', 'pet_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
