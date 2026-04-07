<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = ['appointment_id', 'order_id', 'pet_id', 'pet_name', 'service_id',
        'scheduled_start', 'scheduled_end', 'completed_at', 'completed_by',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
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
