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
use Throwable;

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

        Appointment::query()->whereHas('service', function ($q) {
                $q->whereIn('category', array_merge(config('services.dd.special_service_cats'), ['Interview']));
            })->where(function ($q) {
                $q->where('sync_status', ServiceSyncStatus::Ready)
                    ->orWhere(function ($q2) {
                        $q2->where('sync_status', ServiceSyncStatus::Error)
                            ->where('retry_count', '<', 5)
                            ->where(function ($q3) {
                                $q3->whereIn('last_error_code', ['429','500','502','503','504'])
                                    ->orWhereNull('last_error_code');
                            });
                    });
            })->where('is_archived', false)
            ->whereNotNull('scheduled_start')->whereNotNull('scheduled_end')
            ->with(['dog', 'service'])->orderBy('id')
            ->chunkById(250, function ($appointments) use ($calendar) {
                foreach ($appointments as $appointment) {
                    try {
                        $color = ServiceColor::forCategory($appointment->service->category);

                        // Deterministic Google Event ID
                        $eventId = 'appt' . $appointment->id;

                        $payload = [
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
                                    'source'        => 'cbw-crestwood',
                                    'order_id'      => (string) $appointment->order_rds_id,
                                    'pet_id'        => (string) $appointment->pet_id,
                                    'service_cat'   => (string) $appointment->service->category,
                                    // diagnostics only (volatile)
                                    'appointment_id'=> (string) $appointment->id,
                                ],
                            ],
                        ];

                        // Authoritative upsert: patch if exists, otherwise insert with deterministic id
                        $result = $calendar->upsertByDeterministicId($eventId, $payload);

                        // Persist linkage + status
                        $appointment->fill([
                            'google_event_id' => $result->getId(),
                            'google_color' => $color,
                            'sync_status' => ServiceSyncStatus::Synced,
                            'retry_count' => 0,
                            'last_error_code' => null,
                            'last_error_at' => null,
                            'last_error_message' => null,
                        ])->save();

                    } catch (Throwable $e) {
                        Log::error("Failed to sync Appointment ID {$appointment->id}: {$e->getMessage()}");
                        $code = ($e instanceof Exception) ? (int)$e->getCode() : 0;
                        $retryable = in_array($code, [429, 500, 502, 503, 504], true);

                        $appointment->sync_status = ServiceSyncStatus::Error;
                        $appointment->retry_count = ($appointment->retry_count ?? 0) + 1;
                        $appointment->last_error_code = (string)$code;
                        $appointment->last_error_at = now();
                        $appointment->last_error_message = str($e->getMessage())->limit(500);

                        // Optional: permanently stop retrying on non-retryable
                        if (!$retryable) {
                            $appointment->retry_count = 999; // sentinel to stop
                        }

                        $appointment->save();
                    }
                }
            });
    }
}
