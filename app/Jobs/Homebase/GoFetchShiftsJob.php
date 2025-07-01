<?php

namespace App\Jobs\Homebase;

use App\Models\Employee;
use App\Models\EmployeeYardRotation;
use App\Models\Rotation;
use App\Models\Shift;
use App\Models\Yard;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoFetchShiftsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const SHIFTS_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const SHIFTS_URL_SUFFIX = '/shifts?start_date=TODAY&end_date=TODAY&open=false&with_note=false&date_filter=start_at';
    const BACK_OF_HOUSE = 'BOH';
    const TRAINING = 'Training';
    const EVENT = 'Event';
    const SUPERVISOR = 'Supervisor';
    const START_HOUR_OF_DAY = 6;
    const HOURS_IN_DAY = 13;
    const START_LUNCHES_AT_INDEX = 2 * 12 + 6; // 10:30am


    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $day = Carbon::today();
        $url = self::SHIFTS_URL_PREFIX . config('services.homebase.loc_id') .
            str_replace('TODAY', $day->toDateString(), self::SHIFTS_URL_SUFFIX);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.homebase.api_key'),
            ])->get($url);

            if ($response->successful()) {
                Shift::query()->delete();
                $yards = Yard::where('is_active', '1')->orderBy('display_order')->get();

                $homebaseShifts = collect(json_decode($response->getBody()->getContents()))
                    ->sortBy(['start_at', 'role'])
                    ->reject(fn($shift) => Str::contains($shift->department, 'FOH') ||
                        Str::contains($shift->role, self::TRAINING) ||
                        Str::contains($shift->role, self::EVENT)
                    )
                    ->map(function ($shift) use ($yards) {
                        $shift->yardHoursWorked = $yards->pluck('id')->mapWithKeys(fn($id) => [$id => 0])->toArray();
                        $shift->next_lunch_break = null;
                        return $shift;
                    });


                $fohStaff = collect($homebaseShifts)->filter(fn($shift) => str_contains($shift->role, 'Supervisor'))
                    ->map(function ($shift) {
                        $startAt = Carbon::parse($shift->start_at);
                        $endAt = Carbon::parse($shift->end_at);

                        $startTime = $startAt->minute === 0 ? $startAt->format('ga') : $startAt->format('g:ia');
                        $endTime = $endAt->minute === 0 ? $endAt->format('ga') : $endAt->format('g:ia');

                        return "{$shift->first_name} ({$startTime}-{$endTime})";
                    });
                if ($fohStaff->isNotEmpty()) {
                    Cache::put('foh_staff', $fohStaff->implode(', '), Carbon::tomorrow());
                }
                
                if ($day->isSunday()) {
                    $remainingShifts = $this->assignSundayMorningBreaks($homebaseShifts);
                } else {
                    $remainingShifts = $homebaseShifts;
                }

                $breakMatrix = array_fill(0, self::HOURS_IN_DAY * 12, ['breaks' => 0, 'lunches' => 0]);
                $this->assignLunches($remainingShifts, $breakMatrix);
                $this->assignBreaks($remainingShifts, $breakMatrix);
                $shiftInsertData = $remainingShifts->map(function ($shift) use ($day) {
                    return [
                        'homebase_user_id' => $shift->user_id,
                        'role' => $shift->role,
                        'start_time' => Carbon::parse($shift->start_at)->format('Y-m-d H:i:s'),
                        'end_time' => Carbon::parse($shift->end_at)->format('Y-m-d H:i:s'),
                        'next_first_break' => isset($shift->next_first_break) ? $shift->next_first_break->format('Y-m-d H:i:s') : null,
                        'next_lunch_break' => isset($shift->next_lunch_break) ? $shift->next_lunch_break->format('Y-m-d H:i:s') : null,
                        'next_second_break' => isset($shift->next_second_break) ? $shift->next_second_break->format('Y-m-d H:i:s') : null,
                        'fairness_score' => $shift->fairness_score ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();
                Shift::insert($shiftInsertData);

                $this->updateYardAssignments($yards, $homebaseShifts);
            } else {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to fetch Homebase choodShifts.');
            }
        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Assign lunch breaks to eligible shifts (BOH only, non-Trainers, ≥ 6.5 hrs).
     *
     * @param Collection $homebaseShifts
     * @param array $breakMatrix
     * @return void
     * @throws Exception
     */
    public function assignLunches(Collection &$homebaseShifts, array &$breakMatrix): void
    {
        $shifts = $homebaseShifts->filter(fn($shift) => $this->getDurationInSegments($shift) >= 6.5 * 12);

        foreach ($shifts as $shift) {
            $result = $this->findLunchIndex($shift, $breakMatrix);
            if (!is_null($result)) {
                $this->markSlots($result['index'], 6, $breakMatrix);
                $lunchTime = $this->convertIndexToTime($result['index']);
                $shift->next_lunch_break = $lunchTime;
                $shift->fairness_score = ($shift->fairness_score ?? 0) + $result['deviation'];
                Log::info("Assigned lunch for {$shift->first_name} at {$lunchTime->format('g:i a')} @ {$result['deviation']}");
            }
        }
    }


    /**
     * Assign first and second breaks to eligible shifts.
     *
     * @param Collection $homebaseShifts
     * @param array $breakMatrix
     * @return void
     * @throws Exception
     */
    public function assignBreaks(Collection &$homebaseShifts, array &$breakMatrix): void
    {

        foreach ($homebaseShifts as $shift) {
            $duration = $this->getDurationInSegments($shift);

            if ($duration >= 4 * 12) {
                $result = $this->findFirstBreakIndex($shift, $breakMatrix);
                if (!is_null($result)) {
                    $this->markSlots($result['index'], 3, $breakMatrix);
                    $breakTime = $this->convertIndexToTime($result['index']);
                    $shift->next_first_break = $breakTime;
                    $shift->fairness_score = ($shift->fairness_score ?? 0) + $result['deviation'];
                    Log::info("Assigned first break for {$shift->first_name} at {$breakTime->format('g:i a')} @ {$result['deviation']}");
                }
            }

            if ($duration >= 8 * 12) {
                $result = $this->findSecondBreakIndex($shift, $breakMatrix);
                if (!is_null($result)) {
                    $this->markSlots($result['index'], 3, $breakMatrix);
                    $breakTime = $this->convertIndexToTime($result['index']);
                    $shift->next_second_break = $breakTime;
                    $shift->fairness_score = ($shift->fairness_score ?? 0) + $result['deviation'];
                    Log::info("Assigned first break for {$shift->first_name} at {$breakTime->format('g:i a')} @ {$result['deviation']}");

                }
            }
        }
    }


    /**
     * @param int $idealIndex
     * @param int $duration
     * @param array $breakMatrix
     * @param bool $allowFallback
     * @return array|null
     */
    public function findBreakIndex(int $idealIndex, int $duration, array $breakMatrix, bool $allowFallback = false): array|null
    {
        $maxPeopleOnBreak = 1;
        $searcherIndex = $idealIndex;
        $segmentsSearched = 0;

        while (true) {
            $candidateIndex = $this->findNextAvailableSlot($searcherIndex, $duration, $breakMatrix, $maxPeopleOnBreak);

            if ($candidateIndex === null || abs($candidateIndex - $idealIndex) > 24) {
                return null;
            }

            if ($this->isSlotAvailable($candidateIndex, $duration, $breakMatrix, $maxPeopleOnBreak)) {
                return [
                    'index' => $candidateIndex,
                    'deviation' => abs($candidateIndex - $idealIndex),
                ];
            }

            $segmentsSearched += abs($candidateIndex - $searcherIndex);
            $searcherIndex = $candidateIndex + 1;

            if ($allowFallback && $segmentsSearched > 0 && $segmentsSearched % 6 === 0) {
                $fallbackStart = $idealIndex - 6;
                if ($fallbackStart >= 0 && $this->isSlotAvailable($fallbackStart, $duration, $breakMatrix, $maxPeopleOnBreak)) {
                    return [
                        'index' => $fallbackStart,
                        'deviation' => abs($fallbackStart - $idealIndex),
                    ];
                }
            }
        }
    }


    /**
     * @param int $startIndex
     * @param int $duration
     * @param array $matrix
     * @param int $maxBreaks
     * @return int|null
     */
    private function findNextAvailableSlot(int $startIndex, int $duration, array $matrix, int $maxBreaks = 1): ?int
    {
        $isLunch = $duration >= 6;

        while ($startIndex < count($matrix)) {
            if ($isLunch && !$this->isLunchAnchor($startIndex)) {
                $startIndex++;
                continue;
            }

            if ($this->isSlotAvailable($startIndex, $duration, $matrix, $maxBreaks)) {
                return $startIndex;
            }

            $startIndex++;
        }

        return null;
    }


    private function isSlotAvailable(int $startIndex, int $duration, array $matrix, int $maxBreaks = 1): bool
    {
        for ($i = 0; $i < $duration; $i++) {
            if (!isset($matrix[$startIndex + $i])) return false;

            $slot = $matrix[$startIndex + $i];

            // Lunch: exclusive
            if ($duration >= 6) {
                if ($slot['lunches'] > 0 || $slot['breaks'] > 0) return false;
            } // Breaks: allow up to maxBreaks
            else {
                if ($slot['lunches'] > 0 || $slot['breaks'] >= $maxBreaks) return false;
            }
        }
        return true;
    }


    /**
     * @param Collection $homebaseShifts
     * @return Collection
     */
    private function assignSundayMorningBreaks(Collection $homebaseShifts): Collection
    {
        Shift::insert(
            $homebaseShifts->filter(fn($shift) => Carbon::parse($shift->start_at)->lt(Carbon::createFromTime(12, 0)) &&
                Str::contains($shift->department, self::BACK_OF_HOUSE)
            )->map(function ($shift) {
                return [
                    'homebase_user_id' => $shift->user_id,
                    'start_time' => Carbon::parse($shift->start_at)->format('Y-m-d H:i:s'),
                    'end_time' => Carbon::parse($shift->end_at)->format('Y-m-d H:i:s'),
                    'next_first_break' => Carbon::createFromTime(10, 0),
                    'next_lunch_break' => null,
                    'next_second_break' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray());

        return $homebaseShifts
            ->filter(fn($shift) => Carbon::parse($shift->start_at)->gte(Carbon::createFromTime(12, 0)));

    }

    /**
     * @param Collection $yards
     * @param Collection $shifts
     */
    private function updateYardAssignments(Collection $yards, Collection $shifts): void
    {
        $lastYardHourIds = [];
        $lastYardHourId = null;

        $rotations = Rotation::query()->when(Carbon::now()->isSunday(), fn($q) => $q->where('is_sunday_hour', 1))->get();
        $names = Employee::all()->pluck('first_name', 'homebase_user_id'); // id => name

        EmployeeYardRotation::truncate();
        foreach ($rotations as $rotation) {
            $startOfHour = Carbon::today()->setTimeFromTimeString($rotation->start_time);
            $endOfHour = Carbon::today()->setTimeFromTimeString($rotation->end_time);
            Log::info("Assigning Hour: {$startOfHour->format('h:ia')}");

            // Filter employees who are working during this hour and not on lunch or a supervisor handoff
            $availableShifts = $shifts->filter(function ($shift) use ($startOfHour, $endOfHour, $rotation, $names) {
                $isWorking = Carbon::parse($shift->start_at)->lessThanOrEqualTo($startOfHour) &&
                    Carbon::parse($shift->end_at)->greaterThanOrEqualTo($endOfHour);

                $noBreaks = (!$shift->next_lunch_break ||
                    !$shift->next_lunch_break->between($startOfHour, $endOfHour, false) &&
                    !$shift->next_lunch_break->copy()->addMinutes(30)->between($startOfHour, $endOfHour, false));

                $notSuperHandoff = !Str::contains($shift->role, self::SUPERVISOR)
                    || $rotation->is_super_handoff == 0;

                return $isWorking && $noBreaks && $notSuperHandoff;
            });

            foreach ($yards as $yard) {
                $availableShifts = $availableShifts->sortBy(function ($shift) use ($yard) {
                    return [$shift->yardHoursWorked[$yard->id], $shift->start_at];
                });

                Log::info("Available employees: {$names->only($availableShifts->pluck('user_id')->all())->values()->implode(', ')}");

                while ($availableShifts->isNotEmpty()) {
                    $shift = $availableShifts->shift();

                    // Check if employee worked the same yard last hour
                    if ($yard->id != 999 && isset($lastYardHourIds[$shift->user_id][$yard->id]) &&
                        $lastYardHourId && $lastYardHourIds[$shift->user_id][$yard->id] == $lastYardHourId) {
                        Log::info("{$names[$shift->user_id]} worked this yard within the last hour, skipping");

                        if ($availableShifts->isNotEmpty()) {
                            $availableShifts->push($shift);
                            continue;
                        }
                    }

                    $hoursWorked = $shift->yardHoursWorked[$yard->id] ?? 0;
                    Log::info("{$names[$shift->user_id]} assigned, having {$hoursWorked} hours in {$yard->name}");

                    $this->assignEmployee($yard, $rotation, $shift, $shifts, $lastYardHourIds);
                    break;
                }

            }

            $lastYardHourId = $rotation->id;
        }
    }

    /**
     * @param Yard $yard
     * @param Rotation $hour
     * @param object $shift // Homebase Shift obj
     * @param mixed $shifts
     * @param array $lastYard
     */
    private function assignEmployee(Yard $yard, Rotation $hour, object $shift, mixed &$shifts, array &$lastYard): void
    {
        EmployeeYardRotation::updateOrInsert(
            ['yard_id' => $yard->id, 'rotation_id' => $hour->id],
            ['homebase_user_id' => $shift->user_id, 'updated_at' => now(), 'created_at' => now()]
        );
        $shift->yardHoursWorked[$yard->id]++;

        // Track last assignment
        $lastYard[$shift->user_id][$yard->id] = $hour->id;
    }


    /**
     * @param object $shift
     * @return float
     */
    private function getDurationInSegments(object $shift): float
    {
        return (floatval($shift->labor->scheduled_hours ?? 0) + floatval($shift->labor->scheduled_unpaid_breaks_hours ?? 0)) * 12;
    }

    /**
     * @param object $shift
     * @param array $breakMatrix
     * @return array|null
     */
    private function findLunchIndex(object $shift, array $breakMatrix): ?array
    {
        $duration = $this->getDurationInSegments($shift);
        $startIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at));

        $lunchTarget = $duration >= 8 * 12
            ? $startIndex + round($duration / 2)
            : $startIndex + round($duration * 2 / 3);

        $lunchIndex = max($lunchTarget, self::START_LUNCHES_AT_INDEX);

        return $this->findBreakIndex($lunchIndex, 6, $breakMatrix, true);
    }

    /**
     *
     * @param object $shift
     * @param array $breakMatrix
     * @return array|null
     */
    private function findFirstBreakIndex(object $shift, array $breakMatrix): ?array
    {
        $duration = $this->getDurationInSegments($shift);
        $startIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at));

        $firstBreakTarget = $startIndex + ($duration >= 8 * 12
                ? $duration / 4
                : ($duration >= 6.5 * 12 ? $duration / 3 : $duration / 2));

        return $this->findBreakIndex(round($firstBreakTarget), 4, $breakMatrix);
    }

    /**
     * @param object $shift
     * @param array $breakMatrix
     * @return array|null
     */
    private function findSecondBreakIndex(object $shift, array $breakMatrix): ?array
    {
        $duration = $this->getDurationInSegments($shift);
        $startIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at));
        $endIndex = $this->convertTimeToIndex(Carbon::parse($shift->end_at));

        $secondBreakTarget = $startIndex + round($duration * 3 / 4);
        $secondBreakTarget = min($secondBreakTarget, $endIndex - 4);

        $idealTime = $this->convertIndexToTime(round($secondBreakTarget))->format('g:i a');
        Log::info("Searching for second break at {$idealTime}");
        return $this->findBreakIndex(round($secondBreakTarget), 4, $breakMatrix);
    }

    /**
     * @param int $startIndex
     * @param int $duration
     * @param array $matrix
     * @return void
     */
    private function markSlots(int $startIndex, int $duration, array &$matrix): void
    {
        for ($i = 0; $i < $duration; $i++) {
            $matrix[$startIndex + $i][$duration >= 6 ? 'lunches' : 'breaks'] += 1;
        }
    }

    /**
     * @param int|null $index
     * @return Carbon|null
     */
    private function convertIndexToTime(?int $index): Carbon|null
    {
        if ($index == null) return null;
        return Carbon::parse((floor($index / 12) + self::START_HOUR_OF_DAY) . ':' . (($index % 12) * 5));
    }

    /**
     * @param Carbon $shiftTime
     * @return int
     */
    private function convertTimeToIndex(Carbon $shiftTime): int
    {
        return ($shiftTime->hour - self::START_HOUR_OF_DAY) * 12 + ($shiftTime->minute / 5);
    }

    private function isLunchAnchor(int $startIndex): bool
    {
        $minute = ($startIndex % 12) * 5;
        return $minute <= 30;
    }
}
