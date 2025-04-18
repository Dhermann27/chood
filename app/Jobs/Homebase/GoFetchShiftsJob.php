<?php

namespace App\Jobs\Homebase;

use App\Models\Employee;
use App\Models\YardAssignment;
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

class GoFetchShiftsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const SHIFTS_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const SHIFTS_URL_SUFFIX = '/shifts?start_date=TODAY&end_date=TODAY&open=false&with_note=false&date_filter=start_at';
    const BACK_OF_HOUSE = 'BOH';
    const SUPERVISOR = 'Supervisor';
    const TRAINING = 'Training';
    const EVENT = 'Event';
    const START_BREAKS_AT_INDEX = 3;
    const START_LUNCHES_AT_INDEX = 2 * 12 + 6;


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
//                'Accept'        => 'application/vnd.homebase-v1+json',
            ])->get($url);

            if ($response->successful()) {
                Employee::query()->update([
                    'next_first_break' => null,
                    'next_lunch_break' => null,
                    'next_second_break' => null
                ]);
                $shifts = collect(json_decode($response->getBody()->getContents()))
                    ->sortBy('start_at')
                    ->map(function ($shift) {
                        $shift->yardHoursWorked = array_fill(0, config('services.yardAssignments.numberOfYards'), 0);
                        return $shift;
                    });

                if ($day->isSunday()) {
                    $remainingShifts = $this->assignSundayMorningBreaks($shifts);
                } else {
                    $remainingShifts = $shifts;
                }

                $numberOfHours = config('services.yardAssignments.numberOfHours');
                // Boolean matrix for 5-minute segments of workday
                $breakMatrix = array_fill(0, $numberOfHours * 12, 0);

                $fohStaff = [];
                foreach ($remainingShifts as $shift) {
                    if (isset($shift->labor->scheduled_hours) && str_contains($shift->department, self::BACK_OF_HOUSE)) {
                        $breakMatrix = $this->assignBreaks($shift, $breakMatrix);
                    } else if (!str_contains($shift->role, 'Top Dog') && !str_contains($shift->role, 'Scout')) { // Slacker
                        $startAt = Carbon::parse($shift->start_at);
                        $endAt = Carbon::parse($shift->end_at);

                        $startTime = $startAt->minute == 0 ? $startAt->format('ga') : $startAt->format('g:ia');
                        $endTime = $endAt->minute == 0 ? $endAt->format('ga') : $endAt->format('g:ia');

                        $fohStaff[] = "{$shift->first_name} ({$shift->role}) {$startTime}-{$endTime}";
                    }
                }
                if(count($fohStaff) > 0) {
                    Cache::put('foh_staff', implode(', ', $fohStaff), Carbon::today()->addDay());
                }

                $this->updateYardAssignments($shifts);
            } else {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to fetch Homebase shifts.');
            }
        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }


    /**
     * @param mixed $shift
     * @param array $breakMatrix
     * @return array
     * @throws Exception
     */
    public function assignBreaks(mixed &$shift, array $breakMatrix): array
    {
        $employee = Employee::where('homebase_user_id', $shift->user_id)->first();

        $shiftStartIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at));
        $shiftEndIndex = $this->convertTimeToIndex(Carbon::parse($shift->end_at)->subHours(2));
        $shiftDuration = floatval($shift->labor->scheduled_hours) * 12;
        $shiftSegmentLength = $shiftDuration / ($shiftDuration >= (7.5 * 12) ? 4 : ($shiftDuration >= (6.5 * 12) ? 3 : 2));

        $firstBreakIndex = max($shiftStartIndex + $shiftSegmentLength - 3, self::START_BREAKS_AT_INDEX);
        $lunchBreakIndex = max($firstBreakIndex + $shiftSegmentLength - 3, self::START_LUNCHES_AT_INDEX);
        $secondBreakIndex = min($lunchBreakIndex + $shiftSegmentLength - 3, $shiftEndIndex);

        if ($shiftDuration >= (7.5 * 12) || ($shiftDuration >= (4 * 12) && !is_null($employee->next_first_break))) {
            // If second shift, don't overwrite first break
            $logTime = $this->convertIndexToTime($secondBreakIndex);
            Log::info("Looking for a second break time for {$employee->first_name} at {$logTime}");
            $secondBreakIndex = $this->findBreakIndex($secondBreakIndex, 4, $breakMatrix);
            $this->markSlots($secondBreakIndex, 4, $breakMatrix);
        } else {
            $secondBreakIndex = null;
        }

        if ($shiftDuration >= (6.5 * 12)) {
            $logTime = $this->convertIndexToTime($lunchBreakIndex);
            Log::info("Looking for a lunch time for {$employee->first_name} at {$logTime}");
            $lunchBreakIndex = $this->findBreakIndex($lunchBreakIndex, 7, $breakMatrix);
            $this->markSlots($lunchBreakIndex, 7, $breakMatrix);
        } else {
            $lunchBreakIndex = null;
        }
        $shift->lunch_break = $this->convertIndexToTime($lunchBreakIndex); // To be referenced by YardAssignment logic

        if (is_null($employee->next_first_break)) { // Second shift, don't overwrite first break
            if ($shiftDuration >= (4 * 12)) {
                $logTime = $this->convertIndexToTime($firstBreakIndex);
                Log::info("Looking for a first break time for {$employee->first_name} at {$logTime}");
                $firstBreakIndex = $this->findBreakIndex($firstBreakIndex, 4, $breakMatrix);
                $this->markSlots($firstBreakIndex, 4, $breakMatrix);
                $shift->first_break = $this->convertIndexToTime($firstBreakIndex); // To be referenced by YardAssignment logic
            } else {
                $firstBreakIndex = null;
                $shift->first_break = null;
            }
        }

        $employee->update([
            'next_first_break' => $employee->next_first_break ?? $this->convertIndexToTime($firstBreakIndex),
            'next_lunch_break' => $this->convertIndexToTime($lunchBreakIndex),
            'next_second_break' => $this->convertIndexToTime($secondBreakIndex)
        ]);
        return $breakMatrix;
    }

    /**
     * @param int $breakIndex
     * @param int $duration
     * @param array $breakMatrix
     * @return int
     * @throws Exception
     */
    public function findBreakIndex(int $breakIndex, int $duration, array $breakMatrix): int
    {
        $maxPeopleOnBreak = 0;
        $searcherIndex = $breakIndex;
        while (true) {
            $searcherIndex = $this->findNextAvailableSlot($searcherIndex, $duration, $maxPeopleOnBreak++, $breakMatrix);
            if (!$searcherIndex || $searcherIndex - $breakIndex > 24) {
                $searcherIndex = $breakIndex;
            } else {
                $breakIndex = $searcherIndex;
                break;
            }
        }
        return $breakIndex;
    }


    /**
     * @param int $startIndex
     * @param int $duration
     * @param int $maximum
     * @param array $breakMatrix
     * @return int|null
     * @throws Exception
     */
    function findNextAvailableSlot(int $startIndex, int $duration, int $maximum, array $breakMatrix): int|null
    {
        for ($i = 0; $i < $duration; $i++) {
            if (!isset($breakMatrix[$startIndex + $i])) {
                // No available slots if we pass 7:00pm
                Log::warning('Failed to find shift break time before 7pm.');
                return null;
            }
            $logTime = $this->convertIndexToTime($startIndex + $i);
            if ($breakMatrix[$startIndex + $i] > $maximum) {
                Log::info("{$logTime} taken by {$breakMatrix[$startIndex + $i]} people");
                $startIndex += $i + 1;
                $i = 0; // Slot is occupied, restart search
            } else {
                Log::info("{$logTime} free, only {$breakMatrix[$startIndex + $i]} people");
            }
        }
        return $startIndex;
    }


    /**
     * @param int $startIndex
     * @param int $duration
     * @param array $breakMatrix
     * @return void
     */
    function markSlots(int $startIndex, int $duration, array &$breakMatrix): void
    {
        for ($i = 0; $i < $duration; $i++) {
            $breakMatrix[$startIndex + $i] += 1; // Mark slot as occupied
        }
    }


    /**
     * @param Collection $shifts
     * @return Collection
     */
    public function assignSundayMorningBreaks(Collection $shifts): Collection
    {
        $shiftsBeforeNoon = $shifts->filter(function ($shift) {
            $startAt = Carbon::parse($shift->start_at);
            return $startAt->lt(Carbon::createFromTime(12, 0, 0));
        });
        $shiftIdsBeforeNoon = $shiftsBeforeNoon->pluck('id');
        $shiftUserIdsBeforeNoon = $shiftsBeforeNoon->pluck('user_id');
        $tenAm = Carbon::createFromTime(10, 0, 0);
        Employee::whereIn('homebase_user_id', $shiftUserIdsBeforeNoon)
            ->update(['next_first_break' => $tenAm,
                'next_lunch_break' => null,
                'next_second_break' => null
            ]);

        return $shifts->reject(function ($shift) use ($shiftIdsBeforeNoon) {
            return in_array($shift->id, $shiftIdsBeforeNoon->toArray());
        });
    }

    /**
     * @param Collection $shifts
     */
    public function updateYardAssignments(Collection $shifts): void
    {
        $startHourOfDay = config('services.yardAssignments.startHourOfDay');
        $endHourOfDay = $startHourOfDay + config('services.yardAssignments.numberOfHours');
        $lastYard = [];

        for ($i = $startHourOfDay; $i < $endHourOfDay; $i++) {
            $startOfHour = Carbon::today()->setHour($i);
            $endOfHour = Carbon::today()->setHour($i + 1);
            Log::info('Hour ' . $startOfHour . ' - ' . $endOfHour);

            // Filter employees who are working during this hour and not on break
            $availableEmployees = $shifts->filter(function ($shift) use ($startOfHour, $endOfHour) {
                $isWorking = isset($shift->labor->scheduled_hours) &&
                    str_contains($shift->department, self::BACK_OF_HOUSE) &&
                    !str_contains($shift->role, self::TRAINING) &&
                    !str_contains($shift->role, self::EVENT) &&
                    Carbon::parse($shift->start_at)->lessThanOrEqualTo($startOfHour) &&
                    Carbon::parse($shift->end_at)->greaterThanOrEqualTo($endOfHour);

                $noBreaks = (!isset($shift->first_break) ||
                    !(Carbon::parse($shift->first_break)->between($startOfHour, $endOfHour, false) ||
                        Carbon::parse($shift->first_break)->addMinutes(15)->between($startOfHour, $endOfHour, false))) &&
                    (!isset($shift->lunch_break) ||
                        !(Carbon::parse($shift->lunch_break)->between($startOfHour, $endOfHour, false) ||
                            Carbon::parse($shift->lunch_break)->addMinutes(30)->between($startOfHour, $endOfHour, false)));

                $notSuperHandoff = !str_contains($shift->role, self::SUPERVISOR) ||
                    !($startOfHour->between(Carbon::parse($shift->start_at), Carbon::parse($shift->start_at)->addMinutes(59)) ||
                        $endOfHour->between(Carbon::parse($shift->end_at)->subMinutes(59), Carbon::parse($shift->end_at)));


                return $isWorking && $noBreaks && $notSuperHandoff;
            });

            for ($j = 0; $j < config('services.yardAssignments.numberOfYards'); $j++) {
                $availableEmployees = $availableEmployees->sortBy(function($employee) use ($j) {
                    return [$employee->yardHoursWorked[$j], $employee->start_at];
                });

                // Log $availableEmployees
                $loggedEmployees = $availableEmployees->map(function ($employee) {
                    $hoursWorked = collect($employee->yardHoursWorked)->map(function ($value, $index) {
                        return "$index:$value";
                    })->implode(', ');

                    return $employee->first_name . ', ' . $hoursWorked;
                })->implode(' / ');

                Log::info('Available employees: ' . $loggedEmployees);




                $assigned = false;
                // Not an Employee object, it's a Homebase shift
                while ($availableEmployees->isNotEmpty()) {
                    $employee = $availableEmployees->shift();

                    // Check if employee worked the same yard last hour
                    if (isset($lastYard[$employee->user_id][$j])
                        && $lastYard[$employee->user_id][$j] >= $startOfHour->copy()->subHour()) {
                        Log::info($employee->first_name . ' worked this yard within the last hour, skipping');

                        if ($availableEmployees->isNotEmpty()) {
                            $availableEmployees->push($employee);
                            continue;
                        }
                    }

                    Log::info($employee->first_name . ' assigned, having ' .
                        $employee->yardHoursWorked[$j] . ' hours in Yard ' . $j);
                    $this->assignEmployee($j, $startOfHour, $employee, $shifts, $lastYard);
                    $assigned = true;
                    break;
                }

                // If no employee was assigned due to all of them working the same yard last hour, allow back-to-back assignment
                if (!$assigned) {
                    if ($availableEmployees->isNotEmpty()) {
                        $employee = $availableEmployees->first();
                        Log::info('No other employees available, assigning ' . $employee->first_name . ' to Yard ' . $j);
                        $this->assignEmployee($j, $startOfHour, $employee, $shifts, $lastYard);
                    } else {
                        // Nobody wants to work these days
                        YardAssignment::where('yard_number', $j)
                            ->where('start_time', $startOfHour)
                            ->update(['homebase_user_id' => null]);
                    }
                }
            }

            YardAssignment::where('yard_number', '99')
                ->where('start_time', $startOfHour)
                ->update(['description' => $availableEmployees->isNotEmpty()
                    ? $availableEmployees->sortBy('first_name')->pluck('first_name')->implode(', ')
                    : null]);
        }
    }

    /**
     * @param int $j
     * @param Carbon $startHour
     * @param $employee
     * @param mixed $shifts
     * @param array $lastYard
     */
    public function assignEmployee(int $j, Carbon $startHour, $employee, mixed &$shifts, array &$lastYard): void
    {
        YardAssignment::where('yard_number', $j)
            ->where('start_time', $startHour->format('H:i:s'))
            ->update(['homebase_user_id' => $employee->user_id]);

        $shifts = $shifts->map(function ($e) use ($employee, $j) {
            if ($e->user_id === $employee->user_id) {
                $e->yardHoursWorked[$j]++;
            }
            return $e;
        });

        $lastYard[$employee->user_id][$j] = $startHour;
    }

    /**
     * @param int|null $index
     * @return Carbon|null
     */
    public function convertIndexToTime(?int $index): Carbon|null
    {
        if ($index == null) return null;
        return Carbon::parse((floor($index / 12) + config('services.yardAssignments.startHourOfDay')) .
            ':' . (($index % 12) * 5));
    }

    /**
     * @param Carbon $shiftTime
     * @return int
     */
    public function convertTimeToIndex(Carbon $shiftTime): int
    {
        return ($shiftTime->hour - config('services.yardAssignments.startHourOfDay')) * 12 +
            ($shiftTime->minute / 5);
    }
}
