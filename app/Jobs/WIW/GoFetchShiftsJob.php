<?php

namespace App\Jobs\WIW;

use App\Enums\YardIds;
use App\Models\Dog;
use App\Models\Employee;
use App\Models\EmployeeYardRotation;
use App\Models\Rotation;
use App\Models\Shift;
use App\Models\Yard;
use App\Services\RotationSettings;
use App\Services\WiwService;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use stdClass;

class GoFetchShiftsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const string SUPERVISOR = 'Supervisor';
    const int    START_HOUR_OF_DAY = 6;
    const int    HOURS_IN_DAY = 13;
    const int    START_LUNCHES_AT_INDEX = 2 * 12 + 6; // 10:30am
    const int    PM_SHIFT_START_INDEX = 7 * 12;       // 1:00pm: shifts starting here or later get afternoon floor
    const int    AFTERNOON_BREAK_FLOOR = 10 * 12 + 6;  // 4:30pm: earliest break for PM crew

    const array SKIPPED_POSITIONS = ['Event Staff'];
    const array QUALIFIED_POSITIONS = ['Camp Counselor', 'Camp Counselor In Training', 'Shift Supervisor'];
    const array SUPERVISOR_POSITIONS = ['Shift Supervisor'];

    public function __construct(public bool $recalculateRotation = true)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(WiwService $wiw): void
    {
        $day = Carbon::today();
        $smallMediumOnly = collect(config('services.wiw.small_medium_only'));

        try {
            $data = $wiw->getV2('shifts', [
                'start' => $day->toDateString(),
                'end' => $day->copy()->addDay()->toDateString(),
            ]);

            $positionMap = collect($data['positions'] ?? [])->keyBy('id')
                ->map(fn($p) => $p['name']);

            $userMap = collect($data['users'] ?? [])->keyBy('id')
                ->map(fn($u) => $u['first_name']);

            $savedBreaks = $this->recalculateRotation ? collect() : Shift::whereNotNull('next_first_break')
                ->orWhereNotNull('next_lunch_break')->orWhereNotNull('next_second_break')
                ->get(['wiw_user_id', 'next_first_break', 'next_lunch_break', 'next_second_break'])
                ->keyBy('wiw_user_id');

            Shift::query()->delete();
            $yards = Yard::orderBy('display_order')->get();

            $wiwShifts = collect($data['shifts'] ?? []);

            $qualifiedShifts = $wiwShifts
                ->filter(fn($s) => in_array($positionMap->get($s['position_id']), self::QUALIFIED_POSITIONS))
                ->sortBy(['start_time', 'position_id'])
                ->map(function ($s) use ($positionMap, $userMap, $yards, $smallMediumOnly) {
                    return $this->buildShift($s, $positionMap, $userMap, $yards, $smallMediumOnly);
                })
                ->values();

            $supervisors = $wiwShifts
                ->filter(fn($s) => in_array($positionMap->get($s['position_id']), self::SUPERVISOR_POSITIONS))
                ->map(function ($s) use ($userMap) {
                    $startAt = Carbon::parse($s['start_time']);
                    $endAt = Carbon::parse($s['end_time']);
                    $startFmt = $startAt->minute === 0 ? $startAt->format('ga') : $startAt->format('g:ia');
                    $endFmt = $endAt->minute === 0 ? $endAt->format('ga') : $endAt->format('g:ia');
                    return "{$userMap->get($s['user_id'])} ({$startFmt}-{$endFmt})";
                });

            if ($supervisors->isNotEmpty()) {
                Cache::put('foh_staff', $supervisors->implode(', '), Carbon::tomorrow());
            }

            if ($this->recalculateRotation) {
                if ($day->isSunday()) {
                    $remainingShifts = $this->assignSundayMorningBreaks($qualifiedShifts);
                } else {
                    $remainingShifts = $qualifiedShifts;
                }

                $breakMatrix = array_fill(0, self::HOURS_IN_DAY * 12, ['breaks' => 0, 'lunches' => 0]);
                $this->assignLunches($remainingShifts, $breakMatrix);
                $this->assignBreaks($remainingShifts, $breakMatrix);
            } else {
                $remainingShifts = $qualifiedShifts;
            }

            $shiftInsertData = $remainingShifts->map(fn($shift) => [
                'wiw_user_id' => $shift->user_id,
                'role' => $shift->role,
                'start_time' => Carbon::parse($shift->start_at)->format('Y-m-d H:i:s'),
                'end_time' => Carbon::parse($shift->end_at)->format('Y-m-d H:i:s'),
                'next_first_break' => isset($shift->next_first_break) ? $shift->next_first_break->format('Y-m-d H:i:s') : null,
                'next_lunch_break' => isset($shift->next_lunch_break) ? $shift->next_lunch_break->format('Y-m-d H:i:s') : null,
                'next_second_break' => isset($shift->next_second_break) ? $shift->next_second_break->format('Y-m-d H:i:s') : null,
                'fairness_score' => $shift->fairness_score ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            Shift::insert($shiftInsertData);

            if ($savedBreaks->isNotEmpty()) {
                foreach ($savedBreaks as $userId => $breaks) {
                    Shift::where('wiw_user_id', $userId)->update([
                        'next_first_break' => $breaks->next_first_break,
                        'next_lunch_break' => $breaks->next_lunch_break,
                        'next_second_break' => $breaks->next_second_break,
                    ]);
                }
            }

            $excludedShiftData = $wiwShifts
                ->filter(fn($s) => !in_array($positionMap->get($s['position_id']), self::QUALIFIED_POSITIONS)
                    && !in_array($positionMap->get($s['position_id']), self::SKIPPED_POSITIONS))
                ->map(fn($s) => [
                    'wiw_user_id' => $s['user_id'],
                    'role' => $positionMap->get($s['position_id'], 'Unknown'),
                    'start_time' => Carbon::parse($s['start_time'])->format('Y-m-d H:i:s'),
                    'end_time' => Carbon::parse($s['end_time'])->format('Y-m-d H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all();

            if (!empty($excludedShiftData)) {
                Shift::insert($excludedShiftData);
            }

            if ($this->recalculateRotation) {
                $this->updateYardAssignments($yards, $qualifiedShifts, $smallMediumOnly);
            }

        } catch (ConnectionException $e) {
            Log::error('Connection to WIW API failed.', ['error' => $e->getMessage()]);
        }
    }

    private function buildShift(array $s, Collection $positionMap, Collection $userMap, Collection $yards, Collection $smallMediumOnly): object
    {
        $shift = new stdClass();
        $shift->user_id = $s['user_id'];
        $shift->start_at = $s['start_time'];
        $shift->end_at = $s['end_time'];
        $shift->first_name = $userMap->get($s['user_id'], 'Unknown');
        $positionName = $positionMap->get($s['position_id'], '');
        $shift->role = in_array($positionName, self::SUPERVISOR_POSITIONS) ? self::SUPERVISOR : $positionName;
        $isSyo = $smallMediumOnly->contains($shift->first_name);
        $shift->yardHoursWorked = $yards->mapWithKeys(fn($yard) => [
            $yard->id => ($isSyo && !$yard->is_large && $yard->id !== YardIds::FLOAT->value) ? -1 : 0,
        ])->toArray();
        $shift->next_lunch_break = null;
        $shift->fairness_score = 0;
        return $shift;
    }

    private function assignSundayMorningBreaks(Collection $qualifiedShifts): Collection
    {
        Shift::insert(
            $qualifiedShifts->filter(
                fn($shift) => Carbon::parse($shift->start_at)->lt(Carbon::createFromTime(12, 0))
            )->map(fn($shift) => [
                'wiw_user_id' => $shift->user_id,
                'start_time' => Carbon::parse($shift->start_at)->format('Y-m-d H:i:s'),
                'end_time' => Carbon::parse($shift->end_at)->format('Y-m-d H:i:s'),
                'next_first_break' => Carbon::createFromTime(10, 0),
                'next_lunch_break' => null,
                'next_second_break' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray()
        );

        return $qualifiedShifts->filter(
            fn($shift) => Carbon::parse($shift->start_at)->gte(Carbon::createFromTime(12, 0))
        )->values();
    }

    private function assignLunches(Collection &$qualifiedShifts, array &$breakMatrix): void
    {
        $shifts = $qualifiedShifts->filter(fn($shift) => $this->getDurationInSegments($shift) >= 6.5 * 12);

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

    private function assignBreaks(Collection &$qualifiedShifts, array &$breakMatrix): void
    {
        foreach ($qualifiedShifts as $shift) {
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
                    Log::info("Assigned second break for {$shift->first_name} at {$breakTime->format('g:i a')} @ {$result['deviation']}");
                }
            }
        }
    }

    private function findBreakIndex(int $idealIndex, int $duration, array $breakMatrix, bool $allowFallback = false): array|null
    {
        $searcherIndex = $idealIndex;
        $segmentsSearched = 0;

        while (true) {
            $candidateIndex = $this->findNextAvailableSlot($searcherIndex, $duration, $breakMatrix);

            if ($candidateIndex === null || abs($candidateIndex - $idealIndex) > 24) {
                return null;
            }

            if ($this->isSlotAvailable($candidateIndex, $duration, $breakMatrix)) {
                return [
                    'index' => $candidateIndex,
                    'deviation' => abs($candidateIndex - $idealIndex),
                ];
            }

            $segmentsSearched += abs($candidateIndex - $searcherIndex);
            $searcherIndex = $candidateIndex + 1;

            if ($allowFallback && $segmentsSearched > 0 && $segmentsSearched % $duration === 0) {
                $fallbackStart = $idealIndex - $duration;
                if ($fallbackStart >= 0 && $this->isSlotAvailable($fallbackStart, $duration, $breakMatrix)) {
                    return [
                        'index' => $fallbackStart,
                        'deviation' => abs($fallbackStart - $idealIndex),
                    ];
                }
            }
        }
    }

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
            if ($duration >= 6) {
                if ($slot['lunches'] > 0 || $slot['breaks'] > 0) return false;
            } else {
                if ($slot['lunches'] > 0 || $slot['breaks'] >= $maxBreaks) return false;
            }
        }
        return true;
    }

    private function updateYardAssignments(Collection $yards, Collection $shifts, Collection $smallMediumOnly): void
    {
        $lastYardHourIds = [];
        $lastYardHourId = null;

        $openYardCodes = RotationSettings::get();
        $allowed = $openYardCodes->allowedYards(false);

        $allDogs = Dog::with('icons')->orderBy('id')->get();
        $largeDogs = $allDogs->filter(fn($d) => str_contains($d->size_letter, 'L'))->pluck('id');

        if (in_array(YardIds::ACTIVE->value, $allowed, true)) {
            $half = intdiv($largeDogs->count(), 2);
            Dog::whereIn('id', $largeDogs->slice(0, $half))->update(['yard_id' => YardIds::LARGE->value]);
            Dog::whereIn('id', $largeDogs->slice($half))->update(['yard_id' => YardIds::ACTIVE->value]);
        } else {
            Dog::whereIn('id', $largeDogs)->update(['yard_id' => YardIds::LARGE->value]);
        }

        $smallDogs = $allDogs->filter(fn($d) => str_contains($d->size_letter, 'S') || str_contains($d->size_letter, 'T'))->pluck('id');

        if (in_array(YardIds::MEDIUM->value, $allowed, true)) {
            $half = intdiv($smallDogs->count(), 2);
            Dog::whereIn('id', $smallDogs->slice(0, $half))->update(['yard_id' => YardIds::SMALL->value]);
            Dog::whereIn('id', $smallDogs->slice($half))->update(['yard_id' => YardIds::MEDIUM->value]);
        } else {
            Dog::whereIn('id', $smallDogs)->update(['yard_id' => YardIds::SMALL->value]);
        }

        $rotations = Rotation::query()->when(Carbon::now()->isSunday(), fn($q) => $q->where('is_sunday_hour', 1))->get();
        $names = Employee::all()->pluck('first_name', 'wiw_user_id');

        EmployeeYardRotation::truncate();

        foreach ($rotations as $rotation) {
            $startOfHour = Carbon::today()->setTimeFromTimeString($rotation->start_time);
            $endOfHour = Carbon::today()->setTimeFromTimeString($rotation->end_time);
            Log::info("Assigning Hour: {$startOfHour->format('h:ia')}");

            $availableShifts = $shifts->filter(function ($shift) use ($startOfHour, $endOfHour) {
                $isWorking = Carbon::parse($shift->start_at)->lessThanOrEqualTo($startOfHour) &&
                    Carbon::parse($shift->end_at)->greaterThanOrEqualTo($endOfHour);

                $noBreaks = (!$shift->next_lunch_break ||
                    !$shift->next_lunch_break->between($startOfHour, $endOfHour, false) &&
                    !$shift->next_lunch_break->copy()->addMinutes(30)->between($startOfHour, $endOfHour, false));

                $notIncomingSuper = !Str::contains($shift->role, self::SUPERVISOR)
                    || Carbon::parse($shift->start_at)->lt($startOfHour);

                return $isWorking && $noBreaks && $notIncomingSuper;
            })->values();

            $openYardIds = $openYardCodes->allowedYards((bool)$rotation->is_midday);
            $openYardIds[] = YardIds::FLOAT->value;
            $openYards = $yards->whereIn('id', $openYardIds)->values();

            foreach ($openYards as $yard) {
                $pool = $availableShifts
                    ->when($yard->is_large,
                        fn($c) => $c->reject(fn($s) => $smallMediumOnly->contains($s->first_name) ||
                            (Str::contains($s->role, self::SUPERVISOR) && $startOfHour->hour === 8)
                        ))
                    ->sortBy(fn($s) => [$s->yardHoursWorked[$yard->id], $smallMediumOnly->contains($s->first_name) ? 0 : 1, $s->start_at])
                    ->values();

                Log::info("Available employees: {$names->only($pool->pluck('user_id')->all())->values()->implode(', ')}");

                $chosen = $pool->first(fn($s) => $yard->id === YardIds::FLOAT->value ||
                    $smallMediumOnly->contains($s->first_name) ||
                    !isset($lastYardHourIds[$s->user_id][$yard->id]) ||
                    !$lastYardHourId ||
                    $lastYardHourIds[$s->user_id][$yard->id] !== $lastYardHourId
                ) ?? $pool->first();

                if (!$chosen) continue;

                $hoursWorked = $chosen->yardHoursWorked[$yard->id] ?? 0;
                Log::info("{$names[$chosen->user_id]} assigned, having {$hoursWorked} hours in {$yard->name}");

                $this->assignEmployee($yard, $rotation, $chosen, $shifts, $lastYardHourIds);
                $availableShifts = $availableShifts->reject(fn($s) => $s->user_id === $chosen->user_id)->values();
            }

            $lastYardHourId = $rotation->id;
        }
    }

    private function assignEmployee(Yard $yard, Rotation $hour, object $shift, mixed &$shifts, array &$lastYard): void
    {
        EmployeeYardRotation::updateOrInsert(
            ['yard_id' => $yard->id, 'rotation_id' => $hour->id],
            ['wiw_user_id' => $shift->user_id, 'updated_at' => now(), 'created_at' => now()]
        );
        $shift->yardHoursWorked[$yard->id]++;
        $lastYard[$shift->user_id][$yard->id] = $hour->id;
    }

    private function getDurationInSegments(object $shift): float
    {
        return Carbon::parse($shift->start_at)->diffInMinutes(Carbon::parse($shift->end_at)) / 5;
    }

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

    private function findFirstBreakIndex(object $shift, array $breakMatrix): ?array
    {
        $duration = $this->getDurationInSegments($shift);
        $startIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at));
        $firstBreakTarget = $startIndex + ($duration >= 8 * 12
                ? $duration / 4
                : ($duration >= 6.5 * 12 ? $duration / 3 : $duration / 2));
        if ($startIndex >= self::PM_SHIFT_START_INDEX) {
            $firstBreakTarget = max($firstBreakTarget, self::AFTERNOON_BREAK_FLOOR);
        }
        Log::info("Searching for first break at {$this->convertIndexToTime(round($firstBreakTarget))->format('g:i a')}");
        return $this->findBreakIndex(round($firstBreakTarget), 3, $breakMatrix);
    }

    private function findSecondBreakIndex(object $shift, array $breakMatrix): ?array
    {
        $duration = $this->getDurationInSegments($shift);
        $startIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at));
        $endIndex = $this->convertTimeToIndex(Carbon::parse($shift->end_at));
        $secondBreakTarget = min($startIndex + round($duration * 3 / 4), $endIndex - 4);
        Log::info("Searching for second break at {$this->convertIndexToTime(round($secondBreakTarget))->format('g:i a')}");
        return $this->findBreakIndex(round($secondBreakTarget), 3, $breakMatrix);
    }

    private function markSlots(int $startIndex, int $duration, array &$matrix): void
    {
        for ($i = 0; $i < $duration; $i++) {
            $matrix[$startIndex + $i][$duration >= 6 ? 'lunches' : 'breaks'] += 1;
        }
    }

    private function convertIndexToTime(?int $index): Carbon|null
    {
        if ($index === null) return null;
        return Carbon::parse((floor($index / 12) + self::START_HOUR_OF_DAY) . ':' . (($index % 12) * 5));
    }

    private function convertTimeToIndex(Carbon $shiftTime): int
    {
        $local = $shiftTime->copy()->setTimezone(config('app.timezone'));
        return ($local->hour - self::START_HOUR_OF_DAY) * 12 + ($local->minute / 5);
    }

    private function isLunchAnchor(int $startIndex): bool
    {
        return (($startIndex % 12) * 5) <= 30;
    }
}
