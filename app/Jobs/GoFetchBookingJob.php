<?php

namespace App\Jobs;

use App\Enums\ServiceSyncStatus;
use App\Models\Appointment;
use App\Models\Dog;
use App\Models\Service;
use App\Services\FetchDataService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoFetchBookingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $bookingId;


    /**
     * Create a new job instance.
     */
    public function __construct(string $bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function uniqueId(): string
    {
        return 'booking' . $this->bookingId;
    }

    /**
     * @throws Exception
     */
    public function handle(FetchDataService $fetchDataService): void
    {
        $payload = [
            "username" => config('services.dd.username'),
            "password" => config('services.dd.password'),
        ];

        $response = $fetchDataService->fetchData(config('services.dd.uris.booking') . $this->bookingId, $payload)
            ->getData(true);
        if (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {

            $dogs = Dog::where('booking_id', $this->bookingId)->get()->keyBy('pet_id');
            $serviceMapByDDID = Cache::get('serviceMapByDDID');
            if (!$serviceMapByDDID) {
                // Fallback if cache was cleared
                $serviceMapByDDID = Service::pluck('id', 'dd_id');
                Cache::put('serviceMapByDDID', $serviceMapByDDID, now()->addDay());
            }

            $validChoodAppointmentIds = [];

            foreach (data_get($response, 'data.0.services', []) as $service) {
                $petId = data_get($service, 'contactId');
                if (!$petId || !$dogs->has($petId)) {
                    Log::info('Pet ID not present for booking', ['pet_id' => $petId, 'booking_id' => $this->bookingId]);
                    continue;
                }

                // Update dog only if changed
                $dog = $dogs->get($petId);
                $updates = [
                    'photoUri' => data_get($service, 'contact.s3Photo'),
                    'nickname' => data_get($service, 'contact.nickName'),
                ];
                if ($dog && ($dog->photoUri !== $updates['photoUri'] || $dog->nickname !== $updates['nickname'])) {
                    $dog->update($updates);
                }

                $ddServiceId = data_get($service, 'serviceItem.id');
                $localServiceId = $serviceMapByDDID[$ddServiceId] ?? null;
                if (!$localServiceId) {
                    Log::warning('Service dd_id not mapped', ['dd_id' => $ddServiceId, 'booking_id' => $this->bookingId]);
                    continue;
                }

                $start = Carbon::make(substr((string)data_get($service, 'checkInDateTime'), 0, 19));
                $end = Carbon::make(substr((string)data_get($service, 'checkOutDateTime'), 0, 19));

                $incomingApptId = data_get($service, 'appointmentIds');
                $existingApp = Appointment::where('booking_id', $this->bookingId)->where('pet_id', $petId)
                    ->where('service_id', $localServiceId)
                    ->where(function ($q) use ($incomingApptId) {
                        if ($incomingApptId) {
                            $q->whereNull('appointment_id')
                                ->orWhere('appointment_id', $incomingApptId);
                        } else {
                            $q->whereNull('appointment_id');
                        }
                    })->first();

                if (!$existingApp) {
                    $appointment = Appointment::create([
                        'booking_id' => $this->bookingId,
                        'pet_id' => $petId,
                        'service_id' => $localServiceId,
                        'scheduled_start' => $start,
                        'scheduled_end' => $end,
                        'appointment_id' => $incomingApptId,
                        'sync_status' => ServiceSyncStatus::Ready,
                    ]);
                    $validChoodAppointmentIds[] = $appointment->id;
                } else {
                    $updates = [];

                    if ($start !== null &&
                        optional($existingApp->scheduled_start)->format('Y-m-d H:i:s') !== $start->format('Y-m-d H:i:s')) {
                        $updates['scheduled_start'] = $start;
                    }

                    if ($end !== null &&
                        optional($existingApp->scheduled_end)->format('Y-m-d H:i:s') !== $end->format('Y-m-d H:i:s')) {
                        $updates['scheduled_end'] = $end;
                    }

                    if ($updates) {
                        $updates['sync_status'] = ServiceSyncStatus::Ready;
                        $existingApp->update($updates);
                    }
                    $validChoodAppointmentIds[] = $existingApp->id;
                }
            }

            if (!empty($validChoodAppointmentIds)) {
                Appointment::where('booking_id', $this->bookingId)->whereNotIn('id', $validChoodAppointmentIds)
                    ->delete();
            };

        }

    }


//        $delay = config('services.dd.queue_delay'); Unnecessary due to enormous data payload size
//        usleep(mt_rand($delay, $delay + 1000) * 1000);
}
