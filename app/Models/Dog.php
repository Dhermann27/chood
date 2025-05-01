<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['accountId', 'pet_id', 'firstname', 'lastname', 'gender', 'photoUri', 'weight', 'cabin_id',
        'is_inhouse', 'checkin', 'checkout'];

    protected $casts = ['checkin' => 'datetime:Y-m-d H:i:s', 'checkout' => 'datetime:Y-m-d H:i:s'];

    protected $appends = ['left_icons', 'right_icons'];

    public function getSizeLetter()
    {
        if ($this->weight > 40) return 'L';
        else if ($this->weight >= 30) return 'LS';
        else if ($this->weight >= 15) return 'S';
        else if ($this->weight >= 10) return 'ST';
        else return 'T';
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
            $icons[] = ['icon' => 'weight-hanging', 'text' => $this->getSizeLetter()];
        }

        if ($this->dogServices) {
            foreach ($this->dogServices as $dogService) {
                $start = Carbon::parse($dogService->scheduled_start);
                $today = Carbon::today();
                if (config('services.dd.sandbox_service_condition') === '<=' ? $start->lessThanOrEqualTo($today)
                    : $start->isSameDay($today)) {
                    if (in_array($dogService->service->category, config('services.dd.bath_service_cats')) &&
                        !array_search('droplet', array_column($icons, 'icon'))) {
                        $icons[] = ['icon' => 'droplet', 'text' => substr($start->format('ga'), 0, 2),
                            'transform' => 'grow-1', 'start' => $dogService->scheduled_start, 'checkout' => $this->checkout,
                            'completed' => $dogService->completed_at != null];
                    } elseif (in_array($dogService->service->category, config('services.dd.fsg_service_cats')) &&
                        !array_search('sheep', array_column($icons, 'icon'))) {
                        $icons[] = ['icon' => 'sheep', 'text' => substr($start->format('ga'), 0, 2),
                            'transform' => 'grow-1 right-2', 'start' => $dogService->scheduled_start, 'checkout' => $this->checkout,
                            'completed' => $dogService->completed_at != null];
                    }
                }
            }
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

    public function dogServices(): HasMany
    {
        return $this->hasMany(DogService::class, 'pet_id', 'pet_id');
    }

    public function services(): HasManyThrough // Only use if no schedule information is needed
    {
        return $this->hasManyThrough(Service::class, DogService::class, 'pet_id', 'id', 'pet_id', 'service_id');
    }

}
