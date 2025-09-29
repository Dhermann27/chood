<?php

namespace App\Traits;

use App\Enums\ServiceColor;
use App\Enums\ServiceSyncStatus;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Throwable;

trait AppointmentTrait
{
    /**
     * Reconcile a group of appointments for a stable key (order_id, pet_id, service_id).
     *
     * - If an incoming appointment_id exists locally => update times if changed.
     * - If not found => reuse an unmatched existing row (prefer one with google_event_id), update id/times.
     * - Else create new.
     * - Finally, delete any unmatched FUTURE locals in the group (to avoid drift).
     *
     * @param int $orderId
     * @param int $petId
     * @param int $serviceId
     * @param array<int, array{
     *     appointment_id: string|null,
     *     start: CarbonInterface|null,
     *     end: CarbonInterface|null
     * }> $incoming
     */
    private function reconcileAppointmentsGroup(int $orderId, int $petId, int $serviceId, array $incoming): void
    {
        $existingAppointments = Appointment::where('order_id', $orderId)->where('pet_id', $petId)
            ->where('service_id', $serviceId)->orderBy('scheduled_start')->get();

        // Fast lookup by appointment_id for exact matches
        $appointmentsById = $existingAppointments->keyBy('appointment_id');

        // A pool of rows we can repurpose if RDS rotated appointment_id
        // Prefer rows that already have google_event_id so we preserve linkage
        $reusable = $existingAppointments->sortByDesc(fn($a) => $a->google_event_id ? 1 : 0)->keyBy('id');

        $touchedIds = [];

        foreach ($incoming as $row) {
            $incomingId = $row['appointment_id'] ?? null;
            $start = $row['start'] ?? null;
            $end = $row['end'] ?? null;

            if ($incomingId && $appointmentsById->has($incomingId)) {
                // Exact match: update times if changed, keep google_event_id
                $app = $appointmentsById[$incomingId];

                // Unnecessary, matching appointment_id means event didn't change, but just in case
                $touchedIds[] = $this->updateIfChanged($app, $start, $end);
                unset($reusable[$app->id]); // Remove from list
                continue;
            }

            // No exact match: pick a reusable row (prefer one with google_event_id)
            $reuse = $reusable->shift();
            if ($reuse) {
                $reuse->fill([
                    'appointment_id' => $incomingId,
                    'booking_id' => $row['booking_id'] ?? null,
                    'scheduled_start' => $start,
                    'scheduled_end' => $end,
                    'sync_status' => ServiceSyncStatus::Ready,
                ])->save();

                // Keep indexes consistent for subsequent iterations
                $appointmentsById[$incomingId] = $reuse;
                $touchedIds[] = $reuse->id;
                continue;
            }

            // Nothing to reuse → create new
            $created = Appointment::create([
                'appointment_id' => $incomingId,
                'order_id' => $orderId,
                'booking_id' => $row['booking_id'], // if available on this class
                'pet_id' => $petId,
                'service_id' => $serviceId,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'sync_status' => ServiceSyncStatus::Ready,
            ]);

            $touchedIds[] = $created->id;
            $appointmentsById[$incomingId] = $created;
        }

        // Clean up leftovers — only FUTURE rows (preserve history)
        if (!empty($touchedIds)) {
            Appointment::where('order_id', $orderId)->where('pet_id', $petId)->where('service_id', $serviceId)
                ->whereNotIn('id', $touchedIds)->delete();
        }
    }

    /**
     * Update the scheduled start and/or end times on an Appointment if they differ,
     * and mark the record as ready to be synced.
     *
     * @param Appointment $appointment
     * @param CarbonInterface $start
     * @param CarbonInterface $end
     * @return Appointment
     */
    public function updateIfChanged(Appointment $appointment, CarbonInterface $start, CarbonInterface $end): Appointment
    {
        $updates = [];
        if (optional($appointment->scheduled_start)->format('Y-m-d H:i:s') !== $start->format('Y-m-d H:i:s')) {
            $updates['scheduled_start'] = $start;
        }
        if (optional($appointment->scheduled_end)->format('Y-m-d H:i:s') !== $end->format('Y-m-d H:i:s')) {
            $updates['scheduled_end'] = $end;
        }
        if ($updates) {
            $updates['sync_status'] = ServiceSyncStatus::Ready;
            $appointment->update($updates);
        }
        return $appointment;
    }

    /**
     * Mark an appointment as successfully synced to Google.
     */
    public function markSynced(Appointment $appointment, string $googleEventId, ServiceColor $color): void
    {
        $appointment->fill([
            'google_event_id' => $googleEventId,
            'google_color' => $color,
            'sync_status' => ServiceSyncStatus::Synced,
            'retry_count' => 0,
            'last_error_code' => null,
            'last_error_at' => null,
            'last_error_message' => null,
        ])->save();
    }

    /**
     * Mark an appointment as a sync error (with retry policy).
     */
    public function markSyncError(Appointment $appointment, Throwable $e, bool $retryable): void
    {
        $code = $e->getCode() ?? 0;

        $appointment->fill([
            'sync_status' => ServiceSyncStatus::Error,
            'retry_count' => ($appointment->retry_count ?? 0) + 1,
            'last_error_code' => (string)$code,
            'last_error_at' => now(),
            'last_error_message' => Str::of($e->getMessage())->limit(500),
        ]);

        if (!$retryable) {
            $appointment->retry_count = 999;
        }

        $appointment->save();
    }

}
