<?php

namespace App\Models;

use App\Enums\HousingServiceCodes;
use App\Enums\YardCodes;
use App\Enums\YardIds;
use App\Services\RotationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dog extends Model
{
    use HasFactory;

    public bool $hideYardIcon = false;

    protected $fillable = ['order_id', 'account_id', 'pet_id', 'firstname', 'lastname', 'display_name', 'gender',
        'photoUri', 'weight', 'yard_id', 'cabin_id', 'housing_code', 'checkin', 'checkout', 'checked_out_at',
        'food_type', 'feeding_method', 'feeding_notes', 'services_string', 'owner_id', 'color', 'breed',
    ];

    protected $casts = ['checkin' => 'datetime', 'checkout' => 'datetime', 'checked_out_at' => 'datetime'];

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

    public function getLeftIconsAttribute(): array
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

    public function getRightIconsAttribute(): array
    {
        $icons = [];

        if ($this->weight) {
            $icons[] = ['icon' => 'weight-hanging', 'text' => $this->size_letter];
        }

        // TODO: add droplet/sheep icons by parsing services_string for bath/FSG keywords

        if (!$this->hideYardIcon && $this->yard_id && $this->yard_id !== YardIds::FLOAT->value
            && RotationSettings::get() !== YardCodes::DEFAULT) {
            $preset = RotationSettings::get();
            $hour = now()->hour;
            $allowed = $preset->allowedYards($hour >= 11 && $hour < 13);
            $yardCode = match ($this->yard_id) {
                YardIds::ACTIVE->value => 'A',
                YardIds::LARGE->value => in_array(YardIds::ACTIVE->value, $allowed) ? 'L' : null,
                YardIds::MEDIUM->value => 'M',
                YardIds::SMALL->value => in_array(YardIds::MEDIUM->value, $allowed) ? 'S' : null,
                default => null,
            };
            if ($yardCode) {
                $icons[] = ['icon' => 'star', 'text' => $yardCode];
            }
        }

        return $icons;
    }

    public function scopeInHouse(Builder $query): Builder
    {
        return $query->whereNull('checked_out_at')->whereNotNull('pet_id');
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class, 'pet_id', 'pet_id');
    }

    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class, 'cabin_id');
    }

    public function activeRestBreak(): HasOne
    {
        return $this->hasOne(RestBreak::class, 'pet_id', 'pet_id')->whereNull('ended_at');
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
