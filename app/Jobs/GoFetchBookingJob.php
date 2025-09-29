<?php

namespace App\Jobs;

use App\Enums\HousingServiceCodes;
use App\Enums\ServiceSyncStatus;
use App\Models\Appointment;
use App\Models\Dog;
use App\Models\Service;
use App\Services\FetchDataService;
use App\Traits\AppointmentTrait;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoFetchBookingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AppointmentTrait;

    protected string $bookingId;
    protected string $orderId;


    /**
     * Create a new job instance.
     */
    public function __construct(string $bookingId, string $orderId)
    {
        $this->bookingId = $bookingId;
        $this->orderId = $orderId;
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
            'username' => config('services.dd.username'),
            'password' => config('services.dd.password'),
        ];

        $response = $fetchDataService
            ->fetchData(config('services.dd.uris.booking') . $this->bookingId, $payload)
            ->getData(true);

        if (!isset($response['data'][0]) || !is_array($response['data'][0])) {
            return;
        }

        $ddServices = $response['data'][0]['services'] ?? [];

        $serviceMapByDDID = Cache::remember('serviceMapByDDID:v1', now()->addDay(), fn() => Service::whereNotNull('dd_id')->pluck('id', 'dd_id')->all());

        /**
         * 1) Dogs: batch update only when changed
         */
        $dogsToUpsert = collect($ddServices)->filter(fn($s) => !empty($s['contactId']))->unique('contactId')
            ->map(function ($s) {
                return [
                    'pet_id' => (string)$s['contactId'],
                    'photoUri' => $s['contact']['s3Photo'] ?? null,
                    'nickname' => $s['contact']['nickName'] ?? null,
                    'updated_at' => now(),
                ];
            })->values()->all();

        if ($dogsToUpsert) {
            Dog::upsert($dogsToUpsert, ['pet_id'], ['photoUri', 'nickname', 'updated_at']);
        }

        $validLocalIds = [];

        $groupsByPet = Appointment::where('order_id', $this->orderId)->get()->groupBy('pet_id')
            ->map(fn($c) => $c->keyBy('id'));


        foreach ($ddServices as $service) {
            $norm = $this->normalizeServiceRow($service, $serviceMapByDDID);
            if (!$norm) continue;

            ['petId' => $petId, 'localServiceId' => $localServiceId, 'start' => $start, 'end' => $end,
                'incomingApptId' => $incomingApptId] = $norm;
            $startStr = $start?->format('Y-m-d H:i:s');
            $group = $groupsByPet->get($petId, collect());

            // 0) No-op: if appointment_id exists under (order_id, pet_id), skip entirely
            if ($incomingApptId && ($existing = $group->firstWhere('appointment_id', $incomingApptId))) {
                $validLocalIds[] = $existing->id;
                $group->forget($existing->id);
                Log::info('No-op: appointment_id match', ['appointment_id' => $incomingApptId]);
                continue;
            }

            $row = null;
            $matchedBy = null;

            // 1) Same service + same start time (local)
            if ($startStr) {
                $row = $group->first(function ($r) use ($localServiceId, $startStr) {
                    return $r->service_id === $localServiceId && $r->scheduled_start
                        && $r->scheduled_start->format('Y-m-d H:i:s') === $startStr;
                });
                if ($row) $matchedBy = 'service+start';
            }

            // 2) Same start time regardless of service (service code changed)
            if (!$row && $startStr) {
                $row = $group->first(function ($r) use ($startStr) {
                    return $r->scheduled_start && $r->scheduled_start->format('Y-m-d H:i:s') === $startStr;
                });
                if ($row) $matchedBy = 'start_only';
            }

            // 3) Same service (last-ditch reuse to preserve google id)
            if (!$row) {
                $row = $group->firstWhere('service_id', $localServiceId);
                if ($row) $matchedBy = 'service_only';
            }

            if ($row) {
                $group->forget($row->id);
                $row->fill([
                    'appointment_id' => $incomingApptId,
                    'service_id' => $localServiceId,
                    'scheduled_start' => $start,  // save as local Carbon
                    'scheduled_end' => $end,    // save as local Carbon
                    'booking_id' => $this->bookingId, // booking_id churns; keep current
                    'sync_status' => ServiceSyncStatus::Ready,
                ])->save();

                $validLocalIds[] = $row->id;

                Log::info('Update: reused existing row', [
                    'order_id' => $this->orderId,
                    'pet_id' => $petId,
                    'matched_by' => $matchedBy,
                    'row_id' => $row->id
                ]);
            } else {
                $created = Appointment::create([
                    'appointment_id' => $incomingApptId,
                    'order_id' => $this->orderId,
                    'booking_id' => $this->bookingId,
                    'pet_id' => $petId,
                    'service_id' => $localServiceId,
                    'scheduled_start' => $start,
                    'scheduled_end' => $end,
                    'sync_status' => ServiceSyncStatus::Ready,
                ]);
                $validLocalIds[] = $created->id;

                Log::info('Create: new row', [
                    'order_id' => $this->orderId,
                    'pet_id' => $petId,
                    'row_id' => $created->id,
                    'service_id' => $localServiceId
                ]);
            }

        }

        if ($validLocalIds) {
            Appointment::where('order_id', $this->orderId)->whereNotIn('id', $validLocalIds)->delete();
        }

    }

    /**
     * Validate + map + parse one DD service row.
     * Returns null when the row should be skipped (and logs why).
     *
     * @param array $service
     * @param array $serviceMapByDDID // ddServiceId => local service_id
     * @return array|null { petId, localServiceId, start, end, incomingApptId }
     */
    private function normalizeServiceRow(array $service, array $serviceMapByDDID): ?array
    {
        $petId = $service['contactId'] ?? null;
        $ddServiceId = $service['serviceItem']['id'] ?? ($service['serviceId'] ?? null);

        if (!$petId || !$ddServiceId) {
            Log::error('Skip: missing petId or ddServiceId', [
                'order_id' => $this->orderId,
                'booking_id' => $this->bookingId,
                'pet_id' => $petId,
                'ddServiceId' => $ddServiceId,
            ]);
            return null;
        }

        $serviceCode = trim($service['serviceItem']['code'] ?? '');
        Log::info('serviceCode: ' . $serviceCode, [HousingServiceCodes::isHousingCode($serviceCode)]);
        // Skip housing services up front
        if ($serviceCode === '' || HousingServiceCodes::isHousingCode($serviceCode)) {
//            Log::info('Skip: housing service', [
//                'order_id' => $this->orderId,
//                'booking_id' => $this->bookingId,
//                'pet_id' => $petId,
//                'ddServiceId' => $ddServiceId,
//                'service_code' => $serviceCode,
//            ]); Too common to log
            return null;
        }

        $localServiceId = $serviceMapByDDID[$ddServiceId] ?? null;
        if (!$localServiceId) {
            Log::error('Skip: ddServiceId not mapped', [
                'order_id' => $this->orderId,
                'booking_id' => $this->bookingId,
                'ddServiceId' => $ddServiceId,
            ]);
            return null;
        }

        try {
            $tz = config('app.timezone', 'America/Chicago');
            $start = isset($service['checkInDateTime']) ? CarbonImmutable::parse($service['checkInDateTime'], $tz) : null;
            $end = isset($service['checkOutDateTime']) ? CarbonImmutable::parse($service['checkOutDateTime'], $tz) : null;
        } catch (Throwable $e) {
            Log::error('Skip: invalid datetime from service', [
                'order_id' => $this->orderId,
                'booking_id' => $this->bookingId,
                'pet_id' => $petId,
                'checkInDateTime' => $service['checkInDateTime'] ?? null,
                'checkOutDateTime' => $service['checkOutDateTime'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $incomingApptId = trim((string)$this->firstAppointmentId($service)) ?: null;

        return compact('petId', 'localServiceId', 'start', 'end', 'incomingApptId');
    }

    /**
     * Get the first appointment id from a service payload.
     * Handles: null | scalar | CSV string | array
     */
    private function firstAppointmentId(array $service): ?string
    {
        $raw = data_get($service, 'appointmentIds') ?? data_get($service, 'appointmentId');
        if ($raw === null) return null;

        // If it's already an array, return first non-empty
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $v = trim((string)$v);
                if ($v !== '') return $v;
            }
            return null;
        }

        // It's a scalar string/int – normalize and split CSV if needed
        $str = trim((string)$raw);
        if ($str === '') return null;

        // CSV? take the first token
        if (str_contains($str, ',')) {
            $parts = array_filter(array_map('trim', explode(',', $str)));
            return $parts ? reset($parts) : null;
        }

        return $str;
    }

}
