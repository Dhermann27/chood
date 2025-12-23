<?php

namespace App\Models;

use App\Enums\ServiceSyncStatus;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = ['appointment_id', 'order_id', 'booking_id', 'pet_id', 'pet_name', 'service_id',
        'scheduled_start', 'scheduled_end', 'google_event_id', 'sync_status', 'sync_token', 'completed_at',
        'completed_by', 'retry_count', 'last_error_code', 'last_error_at', 'last_error_message',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'retry_count' => 'integer',
        'last_error_at' => 'datetime',

        'sync_status' => ServiceSyncStatus::class,   // backed enum
    ];

    public function dog()
    {
        return $this->belongsTo(Dog::class, 'pet_id', 'pet_id');
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
