<?php

namespace App\Jobs;

use App\Enums\ServiceColor;
use App\Enums\ServiceSyncStatus;
use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Google\Service\Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDogServicesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('high');
    }

    /**
     * Sync all active (non-archived) dog services to Google Calendar.
     *
     * @param GoogleCalendarService $calendar
     * @return void
     * @throws Exception
     */
    /**
     * Sync all active (non-archived) dog services to Google Calendar.
     * Only appointments flagged as Ready are synced. Archived are left alone.
     *
     * @throws Exception
     */
    public function handle(GoogleCalendarService $calendar): void
    {
        $start = now()->subMonth();
        $end = now()->addYear();

        Appointment::query()
            ->whereHas('service', function ($query) {
                $query->whereIn('category', array_merge(
                    config('services.dd.special_service_cats'),
                    ['Interview']
                ));
            })
            ->where('sync_status', ServiceSyncStatus::Ready)
            ->where('is_archived', false)
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end')
            ->with(['dog', 'service'])
            ->orderBy('id')
            ->chunkById(250, function ($appointments) use ($calendar) {
                foreach ($appointments as $appointment) {
                    try {
                        $color = ServiceColor::forCategory($appointment->service->category);

                        // Deterministic Google Event ID
                        $eventId = 'appt-' . $appointment->id;

                        $payload = [
                            'id' => $eventId, // deterministic id on insert
                            'summary' => "{$appointment->dog->firstname} – {$appointment->service->name}",
                            'description' => "Service ID: {$appointment->id}",
                            'start' => [
                                'dateTime' => $appointment->scheduled_start->toRfc3339String(),
                                'timeZone' => config('app.timezone'),
                            ],
                            'end' => [
                                'dateTime' => $appointment->scheduled_end->toRfc3339String(),
                                'timeZone' => config('app.timezone'),
                            ],
                            'colorId' => $color->googleColorId(),
                            'extendedProperties' => [
                                'private' => [
                                    'source' => 'cbw-crestwood',
                                    'appointment_id' => (string)$appointment->id,
                                    'service_cat' => (string)$appointment->service->category,
                                ],
                            ],
                        ];

                        // Authoritative upsert: patch if exists, otherwise insert with deterministic id
                        $result = $calendar->upsertByDeterministicId($eventId, $payload);

                        // Persist linkage + status
                        $appointment->google_event_id = $result->getId(); // equals $eventId going forward
                        $appointment->google_color = $color;           // make sure your cast is correct
                        $appointment->sync_status = ServiceSyncStatus::Synced;
                        $appointment->save();
                    } catch (\Throwable $e) {
                        Log::error("Failed to sync Appointment ID {$appointment->id}: {$e->getMessage()}");
                        $appointment->sync_status = ServiceSyncStatus::Error;
                        $appointment->save();
                    }
                }
            });
    }
}
