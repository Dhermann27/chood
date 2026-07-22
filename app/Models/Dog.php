<?php

namespace App\Models;

use App\Enums\HousingServiceCodes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'account_id', 'pet_id', 'firstname', 'lastname', 'display_name', 'gender', 'photoUri',
        'weight', 'yard_id', 'cabin_id', 'housing_code', 'checkin', 'checkout', 'checked_out_at',
        'rest_starts_at', 'break_type_id', 'food_type', 'feeding_method', 'feeding_notes', 'services_string',
    ];

    protected $casts = ['checkin' => 'datetime', 'checkout' => 'datetime',
        'checked_out_at' => 'datetime', 'rest_starts_at' => 'datetime'];

    protected $appends = ['is_boarding', 'is_daycare', 'is_interview', 'size_letter', 'left_icons', 'right_icons'];

    public function getSizeLetterAttribute(): string
    {
        $map = [
            'Large Dog Playgroup' => 'L',
            'Small Dog Playgroup' => 'S',
            'Float 30 - 40lbs' => 'LS',
            'Tea Cup' => 'T',
            'Float 11-16 lbs.' => 'ST',
        ];

        $sizeIcon = $this->icons->first(fn($icon) => $icon->group_name === 'Size Group');
        if ($sizeIcon && isset($map[$sizeIcon->title])) {
            return $map[$sizeIcon->title];
        }

        return match (true) {
            $this->weight >= 40 => 'L',
            $this->weight >= 30 => 'LS',
            $this->weight >= 15 => 'S',
            $this->weight >= 10 => 'ST',
            default => 'T',
        };
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

    public function getRightIconsAttribute()
    {
        $icons = [];

        if ($this->weight) {
            $icons[] = ['icon' => 'weight-hanging', 'text' => $this->size_letter];
        }

        // TODO: add droplet/sheep icons by parsing services_string for bath/FSG keywords

        return $icons;
    }

    public function scopeInHouse($query)
    {
        return $query->whereNull('checked_out_at');
    }

    public function getIsBoardingAttribute(): bool
    {
        return $this->housing_code === HousingServiceCodes::BRDC->value || $this->housing_code === HousingServiceCodes::BRDL->value;
    }

    public function getIsDaycareAttribute(): bool
    {
        return $this->housing_code === HousingServiceCodes::DCFD->value || $this->housing_code === HousingServiceCodes::DCHD->value;
    }

    public function getIsInterviewAttribute(): bool
    {
        return $this->housing_code === HousingServiceCodes::INTV->value;
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class, 'pet_id', 'pet_id');
    }

    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class, 'cabin_id');
    }

    public function breakType(): BelongsTo
    {
        return $this->belongsTo(BreakType::class, 'break_type_id');
    }

    public function feedings(): HasMany
    {
        return $this->hasMany(Feeding::class, 'pet_id', 'pet_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class, 'pet_id', 'pet_id');
    }

    public function icons(): BelongsToMany
    {
        return $this->belongsToMany(Icon::class, 'dog_icons', 'pet_id', 'icon_id', 'pet_id');
    }


}
