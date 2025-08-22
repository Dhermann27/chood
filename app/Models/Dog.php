<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'account_id', 'pet_id', 'firstname', 'lastname', 'gender', 'photoUri',
        'nickname', 'weight', 'cabin_id', 'is_inhouse', 'rest_starts_at', 'rest_duration_minutes'];

    protected $appends = ['left_icons', 'right_icons'];

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

        if ($this->appointments) {
            foreach ($this->appointments as $appointment) {
                $start = Carbon::parse($appointment->scheduled_start);
                $today = Carbon::today();
                if (config('services.dd.sandbox_service_condition') === '<=' ? $start->lessThanOrEqualTo($today)
                    : $start->isSameDay($today)) {
                    if (in_array($appointment->service->category, config('services.dd.bath_service_cats')) &&
                        !array_search('droplet', array_column($icons, 'icon'))) {
                        $icons[] = ['icon' => 'droplet', 'text' => substr($start->format('ga'), 0, 2),
                            'transform' => 'grow-1', 'start' => $appointment->scheduled_start, 'checkout' => $this->checkout,
                            'completed' => $appointment->completed_at != null];
                    } elseif (in_array($appointment->service->category, config('services.dd.fsg_service_cats')) &&
                        !array_search('sheep', array_column($icons, 'icon'))) {
                        $icons[] = ['icon' => 'sheep', 'text' => substr($start->format('ga'), 0, 2),
                            'transform' => 'grow-1 right-2', 'start' => $appointment->scheduled_start, 'checkout' => $this->checkout,
                            'completed' => $appointment->completed_at != null];
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

    /** A dog has many appointments */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'pet_id', 'pet_id');
    }

}
