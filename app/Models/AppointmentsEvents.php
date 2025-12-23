<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AppointmentsEvents extends Model
{
    protected $table = 'appointments__events';
    protected $primaryKey = 'group_key';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'ids_json' => 'array',
        'appointment_ids_json' => 'array',
        'order_ids_json' => 'array',
        'booking_ids_json' => 'array',
        'pet_ids_json' => 'array',
        'service_ids_json' => 'array',
        'dog_names_json' => 'array',
        'service_names_json' => 'array',
    ];

    /* ---------- Accessors returning int arrays ---------- */
    public function ids(): array
    {
        return $this->toInts($this->ids_json);
    }

    public function appointmentIds(): array
    {
        return $this->toInts($this->appointment_ids_json);
    }

    public function orderIds(): array
    {
        return $this->toInts($this->order_ids_json);
    }

    public function bookingIds(): array
    {
        return $this->toInts($this->booking_ids_json);
    }

    public function petIds(): array
    {
        return $this->toInts($this->pet_ids_json);
    }

    public function serviceIds(): array
    {
        return $this->toInts($this->service_ids_json);
    }

    protected function toInts(?array $arr): array
    {
        if (!$arr) return [];
        return array_values(array_filter(array_map(fn($v) => is_null($v) ? null : (int)$v, $arr), fn($v) => $v !== null));
    }

    /* ---------- Relationship-like helpers ---------- */

    public function appointments(): Collection
    {
        $ids = $this->ids();
        return empty($ids) ? new Collection() : Appointment::whereIn('id', $ids)->get();
    }

    public function pets(): Collection
    {
        $ids = $this->petIds();
        return empty($ids) ? new Collection() : Dog::whereIn('id', $ids)->get();
    }

    public function services(): Collection
    {
        $ids = $this->serviceIds();
        return empty($ids) ? new Collection() : Service::whereIn('id', $ids)->get();
    }

    public function appointmentsQuery()
    {
        $ids = $this->ids();
        return Appointment::query()->when(!empty($ids), fn($q) => $q->whereIn('id', $ids));
    }

    public function petsQuery()
    {
        $ids = $this->petIds();
        return Dog::query()->when(!empty($ids), fn($q) => $q->whereIn('id', $ids));
    }

    public function servicesQuery()
    {
        $ids = $this->serviceIds();
        return Service::query()->when(!empty($ids), fn($q) => $q->whereIn('id', $ids));
    }

    /* ---------- Useful scopes ---------- */

    public function scopeContainingAppointmentId($q, int $appointmentId)
    {
        // JSON_CONTAINS for numeric arrays
        return $q->whereRaw('JSON_CONTAINS(appointment_ids_json, CAST(? AS JSON))', [json_encode($appointmentId)]);
    }

    public function scopeOverlapping($q, $start, $end)
    {
        return $q->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start);
    }
}
