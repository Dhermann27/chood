<?php

namespace App\Services;

use App\Enums\ServiceColor;
use App\Models\AppointmentsEvents;
use Carbon\Carbon;
use Exception;
use Google\Http\Batch;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\Resource\Events;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;


/**
 * Service for interacting with Google Calendar via the Google API PHP Client.
 */
class GoogleCalendarService
{
    protected Calendar $calendar;

    public function __construct(Calendar $calendar)
    {
        $this->calendar = $calendar;
    }

    public function buildRowsFromAppointments(Collection $appointments, string $runToken): array
    {
        $tz = config('app.timezone');

        return $appointments->map(function (AppointmentsEvents $event) use ($runToken, $tz) {
            $isGroom = str_starts_with($event->group_key, 'groom|');
            $isTreat = str_starts_with($event->group_key, 'treat|');

            $dogNames = array_values(array_unique(array_filter($event->dog_names_json ?? [])));
            if (!$dogNames) $dogNames = ['Dog'];
            sort($dogNames, SORT_NATURAL | SORT_FLAG_CASE);
            $serviceNames = array_values(array_unique(array_filter($event->service_names_json ?? [])));

            $start = Carbon::parse($event->scheduled_start)->toRfc3339String();
            $end = Carbon::parse($event->scheduled_end)->toRfc3339String();

            // Services will only be size > 1 if isGroom or isTreat
            $colorEnum = $isGroom ? ServiceColor::BasicGrooming
                : ($isTreat ? ServiceColor::Enrichment
                    : ServiceColor::forCategory($event->service_category ?? 'needs updating'));

            $summary = $isGroom && count($serviceNames) > 1
                ? (($dogNames[0] ?? 'Dog') . ' – Grooming')
                : ($isTreat ? 'Treat Enrichment (' . count($dogNames) . ')'
                    : (($dogNames[0] ?? 'Dog') . ' – ' . ($serviceNames[0] ?? 'Service')));

            $desc = [
                $isGroom && count($serviceNames) > 1 ? ('Services: ' . implode(', ', $serviceNames) . "\n\n") : null,
                $isTreat ? ('Dogs: ' . implode(', ', $dogNames) . "\n\n") : null,
                'Group Key: ' . $event->group_key,
                'Member Appointments: ' . implode(', ', $event->ids_json ?? []),
                "Color: {$colorEnum->value} {$colorEnum->googleColorId()}",
                'Updated at: ' . now($tz)->format('Y-m-d H:i:s'),
            ];
            $description = implode("\n", array_filter($desc));

            return [
                'google_event_id' => $event->google_event_id,
                'start_rfc3339' => $start,
                'end_rfc3339' => $end,
                'payload' => [
                    'summary' => $summary,
                    'description' => $description,
                    'start' => ['dateTime' => $start, 'timeZone' => $tz],
                    'end' => ['dateTime' => $end, 'timeZone' => $tz],
                    'colorId' => $colorEnum->googleColorId(),
                    'extendedProperties' => [
                        'private' => [
                            'source' => 'cbw-crestwood',
                            'group_key' => (string)$event->group_key,
                            'group_type' => $isGroom ? 'GROOM' : ($isTreat ? 'TE' : 'ONE'),
                            'sync_run' => $runToken,
                            'appointment_ids' => json_encode($event->appointment_ids_json ?? ''),
                            'order_ids' => json_encode($event->order_ids_json ?? ''),
                            'booking_ids' => json_encode($event->booking_ids_json ?? ''),
                            'pet_ids' => $isTreat ? '' : json_encode($event->pet_ids_json ?? ''),
                            'service_id' => json_encode($event->service_ids_json ?? ''),
                        ],
                    ],
                ]
            ];
        })->all();

    }


    /**
     * @throws GoogleServiceException
     */
    public function upsertAndReconcileGroup(array $rows, ?string $lastRunToken): void
    {
        if (empty($rows)) {
            Log::warning("Empty Google Calendar rows");
            return;
        }

        // New run token is already baked into the payloads from buildRowsFromAppointments()
        $newRunToken = $rows[0]['payload']['extendedProperties']['private']['sync_run'] ?? null;

        // Grab prior Google events for the LAST run (if there was one)
        $priorEvents = [];
        if ($lastRunToken) {
            $opt = [
                'maxResults' => 2500,
                'singleEvents' => false,
                'privateExtendedProperty' => [
                    'source=cbw-crestwood',
                    'sync_run=' . $lastRunToken,
                ],
            ];
            $priorEvents = $this->listEvents($opt);
        }

        // Index new rows by group_key
        $newByGroupKey = [];
        foreach ($rows as $row) {
            $exclusive = $row['payload']['extendedProperties']['private'] ?? [];
            $gk = $exclusive['group_key'] ?? null;
            if ($gk === null) {
                continue;
            }
            $newByGroupKey[(string)$gk] = $row;
        }

        // Compare prior events to new aggregate rows
        $toPatch = [];    // existing GC events that still have a group
        $toDelete = [];   // GC events whose group is gone
        $indexToRowKey = []; // batch key => row

        foreach ($priorEvents as $googleEvent) {
            $exclusive = $googleEvent->getExtendedProperties()?->getPrivate() ?? [];
            $gk = $exclusive['group_key'] ?? null;
            $eid = $googleEvent->getId();

            if ($gk !== null && isset($newByGroupKey[$gk])) {
                // 5a + 5b: group still exists -> patch full payload with new sync_run
                $row = $newByGroupKey[$gk];
                $toPatch[] = ['eventId' => $eid, 'row' => $row];
                unset($newByGroupKey[$gk]); // remove from insertion set
            } else {
                // 5d: calendar event no longer has a matching group -> delete
                $toDelete[] = $eid;
            }
        }
        // 5c: remaining newByGroupKey entries need inserts
        $toInsert = array_values($newByGroupKey);
        Log::info('GC inserts', [
            'count' => count($toInsert),
            'group_keys' => array_map(
                fn($r) => $r['payload']['extendedProperties']['private']['group_key'] ?? null,
                $toInsert
            ),
        ]);
        Log::info('GC patches', [
            'count' => count($toPatch),
            'group_keys' => array_map(
                fn($p) => $p['row']['payload']['extendedProperties']['private']['group_key'] ?? null,
                $toPatch
            ),
        ]);


        // 6. Single batch: insert + patch + delete
        $client = $this->calendar->getClient();
        $client->setUseBatch(true);
        $batch = new Batch($client, false, 'https://www.googleapis.com', 'batch/calendar/v3');

        $errors = [];

        // No deletes, ask FOH to manually remove from calendar when removing from DD
        //        foreach ($toDelete as $eid) {
        //            $batch->add($this->calendar->events->delete($this->calendarId(), $eid), 'del_' . $eid);
        //        }

        // Patches
        foreach ($toPatch as $i => $item) {
            $eid = $item['eventId'];
            $row = $item['row'];

            $eventBody = new Event($row['payload']);
            $batchKey = 'patch_' . $i;
            $batch->add($this->calendar->events->patch($this->calendarId(), $eid, $eventBody), $batchKey);
            $indexToRowKey[$batchKey] = $row;
        }

        // Inserts
        foreach ($toInsert as $i => $row) {
            $eventBody = new Event($row['payload']);
            $batchKey = 'ins_' . $i;
            $batch->add($this->calendar->events->insert($this->calendarId(), $eventBody), $batchKey);
            $indexToRowKey[$batchKey] = $row;
        }

        $resp = $batch->execute();

        // 7. Collect appointment_id => google_event_id for a single DB update
        $pairs = []; // appointment_id => eventId

        foreach ($resp as $batchKey => $obj) {
            if ($obj instanceof Exception) {
                $errors[] = [
                    'key' => $batchKey,
                    'error' => $obj->getMessage(),
                    'code' => $obj->getCode(),
                ];
                continue;
            }

            if ($obj instanceof Event) {
                $eid = $obj->getId();
                $exclusive = $obj->getExtendedProperties()?->getPrivate() ?? [];

                // decode child appointment ids from private.appointment_ids JSON
                $apptIdsJson = $exclusive['appointment_ids'] ?? null;
                if (!$apptIdsJson) {
                    // fallback to what we sent if Google didn't echo (unlikely)
                    $row = $indexToRowKey[$batchKey] ?? null;
                    $apptIdsJson = $row['payload']['extendedProperties']['private']['appointment_ids'] ?? null;
                }

                if ($apptIdsJson) {
                    $ids = json_decode($apptIdsJson, true);
                    if (is_array($ids)) {
                        foreach ($ids as $aid) {
                            $aid = (int)$aid;
                            if ($aid > 0) {
                                $pairs[$aid] = $eid;
                            }
                        }
                    }
                }
            }
        }

        // Single bulk update for google_event_id + sync_token on appointments
        if (!empty($pairs) && $newRunToken) {
            $ids = array_keys($pairs);
            $idsCsv = implode(',', array_map('intval', $ids));
            $case = collect($pairs)->map(
                fn($gid, $id) => "WHEN {$id} THEN '" . addslashes($gid) . "'"
            )->implode(' ');

            DB::statement("
            UPDATE appointments
               SET google_event_id = CASE appointment_id {$case} END,
                   sync_token      = ?
             WHERE appointment_id IN ({$idsCsv})
        ", [$newRunToken]);
        }

        Log::info('Google Calendar sync completed', [
            'run' => $newRunToken,
            'inserted' => count($toInsert),
            'patched' => count($toPatch),
            'deleted' => count($toDelete),
            'appointments_updated' => count($pairs),
            'appointment_ids' => array_keys($pairs),
        ]);

        if (!empty($errors)) {
            Log::warning('Google Calendar sync encountered errors', [
                'run' => $newRunToken,
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Helper: list events with arbitrary params, no time window.
     * Paginates until hardCap or no nextPageToken.
     *
     * @param array<string,mixed> $params
     * @param int $hardCap
     * @return Event[]
     * @throws GoogleServiceException
     */
    private function listEvents(array $params, int $hardCap = 5000): array
    {
        $calendarId = $this->calendarId();
        $params = array_merge([
            'singleEvents' => true,
            'maxResults' => 2500,
        ], $params);

        $events = [];
        $pageTok = null;

        do {
            $resp = $this->withRetry(fn() => $this->events()->listEvents($calendarId, array_filter($params + ['pageToken' => $pageTok])));

            $items = $resp->getItems() ?? [];
            foreach ($items as $it) {
                $events[] = $it;
                if (count($events) >= $hardCap) {
                    return $events;
                }
            }
            $pageTok = $resp->getNextPageToken();
        } while ($pageTok);

        return $events;
    }

    /**
     * Resolve and validate the Calendar ID from config.
     */
    private function calendarId(): string
    {
        $id = config('services.google_calendar.calendar_id');
        if ($id === '') {
            throw new RuntimeException('Google Calendar ID is not configured. Set services.google.calendar_id / GOOGLE_CALENDAR_ID.');
        }
        return $id;
    }

    /** @return Events */
    private function events(): Events
    {
        return $this->calendar->events;
    }

    /**
     * Retry wrapper with exponential backoff for 429/5xx.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     *
     * @throws GoogleServiceException
     */
    private function withRetry(callable $fn)
    {
        $attempts = 0;
        $backoffMs = [250, 500, 1000, 2000];
        start:
        try {
            return $fn();
        } catch (GoogleServiceException $e) {
            $code = $e->getCode();
            if (in_array($code, [429, 500, 502, 503, 504], true) && $attempts < count($backoffMs)) {
                usleep($backoffMs[$attempts] * 1000);
                $attempts++;
                goto start;
            }
            throw $e;
        }
    }
}
