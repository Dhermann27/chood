<?php

namespace App\Models;

use App\Enums\ServiceColor;
use App\Enums\ServiceSyncStatus;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $primaryKey = 'appointment_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['appointment_id', 'booking_id', 'service_id', 'pet_id', 'scheduled_start', 'scheduled_end',
        'google_event_id', 'google_color', 'sync_status', 'is_archived', 'completed_at', 'completed_by'];

    protected $casts = [
        'google_color' => ServiceColor::class,
        'sync_status' => ServiceSyncStatus::class,
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
    ];

    public function dog()
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }
    
    /** Appointment belongs to a service */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** Appointment optionally belongs to an employee who completed it */
    public function completedBy()
    {
        return $this->belongsTo(Employee::class, 'completed_by', 'homebase_user_id');
    }

}
