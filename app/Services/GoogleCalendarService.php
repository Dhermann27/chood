<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\Resource\Events;
use Google\Service\Exception;

/**
 * Service for interacting with Google Calendar via the Google API PHP Client.
 */
class GoogleCalendarService
{
    protected Calendar $calendar;
    protected string $calendarId;

    /**
     * Initialize Google Calendar client with service account credentials.
     */
    public function __construct(private readonly Calendar $client)
    {
    }


    /**
     * Upsert an event using a deterministic ID.
     *
     * - Attempts to patch first (idempotent if exists).
     * - If not found (404/410), inserts with the deterministic ID.
     * - If insert conflicts (409), retries with patch.
     *
     * @param string $eventId Deterministic Google event ID (e.g. "appt-123")
     * @param array<string,mixed> $payload Event payload data
     * @return Event The created or updated Google Calendar event
     *
     * @throws Exception On permanent API errors
     */
    public function upsertByDeterministicId(string $eventId, array $payload): Event
    {
        $calendarId = config('services.google_calendar.calendar_id');

        // Always try patch first (idempotent if exists)
        try {
            return $this->withRetry(function () use ($calendarId, $eventId, $payload) {
                return $this->events()->patch($calendarId, $eventId, new Event($payload));
            });
        } catch (Exception $e) {
            if (!in_array((int)$e->getCode(), [404, 410], true)) {
                throw $e;
            }
        }

        // Ensure deterministic ID is set on insert
        if (empty($payload['id'])) {
            $payload['id'] = $eventId;
        }

        try {
            return $this->withRetry(function () use ($calendarId, $payload) {
                return $this->events()->insert($calendarId, new Event($payload));
            });
        } catch (Exception $e) {
            if ((int)$e->getCode() === 409) {
                return $this->withRetry(function () use ($calendarId, $eventId, $payload) {
                    return $this->events()->patch($calendarId, $eventId, new Event($payload));
                });
            }
            throw $e;
        }
    }

    /**
     * List events from Google Calendar within a given time range.
     *
     * @param CarbonInterface $start Start of the time window
     * @param CarbonInterface $end End of the time window
     * @param array<string,mixed> $opts Optional query parameters
     * @return Event[] Array of Google Calendar Event objects
     *
     * @throws Exception On API errors
     */
    public function listEvents(CarbonInterface $start, CarbonInterface $end, array $opts = []): array
    {
        $calendarId = config('services.google.calendar_id');

        $params = array_merge([
            'singleEvents' => true,
            'timeMin' => $start->toRfc3339String(),
            'timeMax' => $end->toRfc3339String(),
            'maxResults' => 2500,
            'orderBy' => 'startTime',
        ], $opts);

        $events = [];
        $pageToken = null;
        do {
            $params['pageToken'] = $pageToken;
            $batch = $this->withRetry(function () use ($calendarId, $params) {
                return $this->events()->listEvents($calendarId, $params);
            });
            $events = array_merge($events, $batch->getItems() ?? []);
            $pageToken = $batch->getNextPageToken();
        } while ($pageToken);

        return $events;
    }

    /**
     * Accessor for the Google Calendar Events resource.
     *
     * @return Events
     */
    private function events(): Events
    {
        return $this->client->events;
    }

    /**
     * Execute a callable with exponential backoff retries for throttling errors.
     *
     * Retries on 429 and transient 5xx errors up to $maxAttempts.
     *
     * @template T
     * @param callable():T $fn The function to execute
     * @return T
     *
     * @throws Exception If the error cannot be retried or max attempts exceeded
     */
    private function withRetry(callable $fn)
    {
        $attempt = 0;
        $delayMs = 250;

        start:
        try {
            return $fn();
        } catch (Exception $e) {
            $code = (int)$e->getCode();
            if (in_array($code, [429, 500, 502, 503, 504], true) && ++$attempt < 5) {
                usleep($delayMs * 1000);
                $delayMs = min(4000, (int)($delayMs * 1.8));
                goto start;
            }
            throw $e;
        }
    }
}
