<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
use App\Enums\ServiceColor;
use App\Enums\ServiceSyncStatus;
use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use App\Traits\AppointmentTrait;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AppointmentTrait;

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
    public function handle(GoogleCalendarService $calendar): void
    {
        $reservations = Appointment::query()->whereHas('service', function ($q) {
            $q->whereNotIn('category', HousingServiceCodes::HOUSING_CODES_ARRAY);
        })->where(function ($q) {
            $q->where('sync_status', ServiceSyncStatus::Ready)
                ->orWhere(function ($q2) {
                    $q2->where('sync_status', ServiceSyncStatus::Error)
                        ->where('retry_count', '<', 5);
                });
        })->where('is_archived', false)
            ->whereNotNull('scheduled_start')->whereNotNull('scheduled_end')->with(['dog', 'service'])
            ->get()->groupBy(fn($a) => $a->order_id . '|' . $a->pet_id);

        foreach ($reservations as $reservation) {
            // Build a compact collection of rows the service understands
            $rows = $reservation->map(function ($appointment) {
                $colorEnum = ServiceColor::forCategory($appointment->service->category);

                return [
                    'google_event_id' => $appointment->google_event_id,
                    'appointment_id' => (string)$appointment->id,
                    'order_id' => (string)$appointment->order_id,
                    'pet_id' => (string)$appointment->pet_id,
                    'service_cat' => $appointment->service->category,
                    'start_rfc3339' => $appointment->scheduled_start->toRfc3339String(),
                    'end_rfc3339' => $appointment->scheduled_end->toRfc3339String(),
                    'payload' => [
                        'summary' => "{$appointment->dog->firstname} – {$appointment->service->name}",
                        'description' => "Service ID: {$appointment->id}\nOrder ID: {$appointment->order_id}\nPet ID: {$appointment->pet_id}\nUpdated at: " . now(config('app.timezone'))->format('Y-m-d H:i:s'),
                        'start' => ['dateTime' => $appointment->scheduled_start->toRfc3339String(), 'timeZone' => config('app.timezone')],
                        'end' => ['dateTime' => $appointment->scheduled_end->toRfc3339String(), 'timeZone' => config('app.timezone')],
                        'colorId' => $colorEnum->googleColorId(),
                        'extendedProperties' => [
                            'private' => [
                                'source' => 'cbw-crestwood',
                                'row_id' => (string)$appointment->id,
                                'order_id' => (string)$appointment->order_id,
                                'pet_id' => (string)$appointment->pet_id,
                                'appointment_id' => (string)$appointment->appointment_id,
                                'service_cat' => (string)$appointment->service->category,
                            ],
                        ],
                    ],
                ];
            });

            try {
                // Service returns appointment_id => google_event_id (or local id => event id, use the same key you built)
                $resultMap = $calendar->upsertAndReconcileGroup($rows->values()->all());

                foreach ($reservation as $appointment) {
                    // IMPORTANT: make sure the key matches what you used when building $rows
                    $key = (string)$appointment->id; // or (string) $appointment->appointment_id if that's your map key
                    if (isset($resultMap[$key])) {
                        $colorEnum = ServiceColor::forCategory($appointment->service->category);
                        $this->markSynced($appointment, $resultMap[$key], $colorEnum);
                    }
                }
            } catch (Throwable $e) {
                Log::error("Failed to sync Reservation: {$e->getMessage()}");

                $retryable = in_array((int)($e->getCode() ?? 0), [429, 500, 502, 503, 504], true);

                foreach ($reservation as $appointment) {
                    $this->markSyncError($appointment, $e, $retryable);
                }
            }

        }
    }
}
