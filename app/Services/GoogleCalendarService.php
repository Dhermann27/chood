<?php

namespace App\Services;

use App\Traits\AppointmentTrait;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\Resource\Events;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;
use RuntimeException;


/**
 * Service for interacting with Google Calendar via the Google API PHP Client.
 */
class GoogleCalendarService
{
    use AppointmentTrait;

    protected Calendar $calendar;

    /**
     * Initialize Google Calendar client with service account credentials.
     */
    public function __construct(Calendar $calendar)
    {
        $this->calendar = $calendar;
    }

    /**
     * Sync a group of appointments with Google Calendar.
     *
     * @param array<int,array<string,mixed>> $rows
     *   Compact rows including:
     *     - appointment_id (string)
     *     - order_id, pet_id, service_cat
     *     - start_rfc3339, end_rfc3339
     *     - payload (array: Google event payload)
     *
     * @return array<string,string>
     *   Map of local appointment_id => google_event_id
     * @throws GoogleServiceException
     * @throws \Exception
     */
    public function upsertAndReconcileGroup(array $rows): array
    {
        // normalize to plain array
        $rows = collect($rows)->values()->all();
        if (empty($rows)) return [];

        $calendarId = config('services.google_calendar.calendar_id');

        $orderId = $rows[0]['order_id'];
        $petId = $rows[0]['pet_id'];

        // 1) Fetch existing events for this group (filter by privateExtendedProperty)
        $existing = $this->listEvents([
            'privateExtendedProperty' => [
                'source=cbw-crestwood',
                "order_id={$orderId}",
                "pet_id={$petId}",
            ],
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'timeMin' => '2000-01-01T00:00:00Z',
            'timeMax' => '2100-01-01T00:00:00Z',
            'maxResults' => 2500,
        ]);

        // ===== Prefetch indexes (unchanged, but add a summary log) =====
        $existingByApptId = [];
        foreach ($existing as $e) {
            $exclusive = $e->getExtendedProperties()->getPrivate() ?? [];
            if (!empty($exclusive['appointment_id'])) {
                $existingByApptId[(string)$exclusive['appointment_id']] = $e;
            }
        }

        $resultMap = [];
        $unmatched = collect($existing)->keyBy(fn(Event $e) => $e->getId());

        // ===== Upsert loop =====
        foreach ($rows as $row) {
            $svc = (string)$row['service_cat'];
            $st = (string)$row['start_rfc3339'];
            $en = (string)$row['end_rfc3339'];
            $apptId = (string)$row['appointment_id'];
            $localKey = $apptId;

            // normalize gid for safety (whitespace/null)
            $gid = $row['google_event_id'] ? trim($row['google_event_id']) : null;

            if (isset($existingByApptId[$apptId])) {
                $event = $existingByApptId[$apptId];
                Log::info('CalSync: no-op (same appt_id)', [
                    'gid' => $event->getId(), 'svc' => $svc, 'start' => $st, 'end' => $en, 'appt' => $apptId
                ]);
                unset($unmatched[$event->getId()]);
                $resultMap[$localKey] = $event->getId();
                continue;
            }

            if ($gid) {
                Log::info('CalSync: patch-by-gid', ['gid' => $gid, 'svc' => $svc, 'start' => $st, 'end' => $en, 'appt' => $apptId]);
                $this->withRetry(fn() => $this->events()->patch($calendarId, $gid, new Event($row['payload']),
                    ['sendUpdates' => 'none']));
                unset($unmatched[$gid]);
                $resultMap[$localKey] = $gid;
                continue;
            }

            Log::info('CalSync: insert (no gid / no appt match)', [
                'svc' => $svc, 'start' => $st, 'end' => $en, 'appt' => $apptId
            ]);
            $created = $this->withRetry(fn() => $this->events()->insert($calendarId, new Event($row['payload']),
                ['sendUpdates' => 'none']));
            $resultMap[$localKey] = $created->getId();
        }

        foreach ($unmatched as $event) {
            $exclusive = $event->getExtendedProperties()->getPrivate() ?? [];
            Log::info('CalSync: delete-unmatched', [
                'gid' => $event->getId(),
                'start' => $event->getStart()->getDateTime(),
                'appt' => $exclusive['appointment_id'] ?? null,
                'svc' => $exclusive['service_cat'] ?? null,
                'order' => $exclusive['order_id'] ?? null,
                'pet' => $exclusive['pet_id'] ?? null,
            ]);

            $this->withRetry(fn() => $this->events()->delete($calendarId, $event->getId(), ['sendUpdates' => 'none']));
        }

        return $resultMap;
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

            /** @var Calendar\Events $resp */
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
