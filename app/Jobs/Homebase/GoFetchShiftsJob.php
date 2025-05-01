<?php

namespace App\Jobs\Homebase;

use App\Models\Employee;
use App\Models\EmployeeYardRotation;
use App\Models\Rotation;
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

class GoFetchShiftsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const SHIFTS_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const SHIFTS_URL_SUFFIX = '/shifts?start_date=TODAY&end_date=TODAY&open=false&with_note=false&date_filter=start_at';
    const BACK_OF_HOUSE = 'BOH';
    const SUPERVISOR = 'Supervisor';
    const TRAINING = 'Training';
    const EVENT = 'Event';
    const FLOATER_YARD_ID = 999;
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
//                'Accept'        => 'application/vnd.homebase-v1+json',
            ])->get($url);

            if ($response->successful()) {
                $yards = Yard::whereNot('id', self::FLOATER_YARD_ID)->orderBy('display_order')->get();
                $rotations = Rotation::orderBy('start_time')->get();

                Employee::query()->update([
                    'next_first_break' => null,
                    'next_lunch_break' => null,
                    'next_second_break' => null
                ]);
                $shifts = collect(json_decode($response->getBody()->getContents()))
                    ->sortBy('start_at')
                    ->map(function ($shift) use ($yards) {
                        $shift->yardHoursWorked = $yards->pluck('id')->mapWithKeys(fn($id) => [$id => 0])->toArray();
                        return $shift;
                    });

                if ($day->isSunday()) {
                    $remainingShifts = $this->assignSundayMorningBreaks($shifts);
                } else {
                    $remainingShifts = $shifts;
                }
                // Boolean matrix for 5-minute segments of workday
                $breakMatrix = array_fill(0, self::HOURS_IN_DAY * 12, 0);

                $fohStaff = [];
                foreach ($remainingShifts as $shift) {
                    if (isset($shift->labor->scheduled_hours) && str_contains($shift->department, self::BACK_OF_HOUSE)) {
//                        $firstHourOfDay = Carbon::parse($rotations->first()->start_time)->hour;
                        $breakMatrix = $this->assignBreaks($shift, $breakMatrix, self::START_HOUR_OF_DAY);
                    }
                    if (str_contains($shift->role, 'FOH') || str_contains($shift->role, 'Supervisor')) {
                        $startAt = Carbon::parse($shift->start_at);
                        $endAt = Carbon::parse($shift->end_at);

                        $startTime = $startAt->minute == 0 ? $startAt->format('ga') : $startAt->format('g:ia');
                        $endTime = $endAt->minute == 0 ? $endAt->format('ga') : $endAt->format('g:ia');

                        $shortName = str_contains($shift->role, 'FOH') ? 'FOH' : 'Lead';
                        $fohStaff[] = "{$shift->first_name} ({$shortName}) {$startTime}-{$endTime}";
                    }
                }
                if (count($fohStaff) > 0) {
                    Cache::put('foh_staff', implode(', ', $fohStaff), Carbon::today()->addDay());
                }

                $this->updateYardAssignments($rotations, $yards, $shifts);
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
     * @param int $firstHourOfDay
     * @return array
     * @throws Exception
     */
    public function assignBreaks(mixed &$shift, array $breakMatrix, int $firstHourOfDay): array
    {
        $employee = Employee::where('homebase_user_id', $shift->user_id)->first();

        $shiftStartIndex = $this->convertTimeToIndex(Carbon::parse($shift->start_at), $firstHourOfDay);
        $shiftEndIndex = $this->convertTimeToIndex(Carbon::parse($shift->end_at)->subHours(1), $firstHourOfDay);
        $shiftDuration = (floatval($shift->labor->scheduled_hours)
                + floatval($shift->labor->scheduled_unpaid_breaks_hours)) * 12;
        $shiftSegmentLength = $shiftDuration / ($shiftDuration >= (8 * 12) ? 4 : ($shiftDuration >= (6.5 * 12) ? 3 : 2));

        $firstBreakIndex = $shiftStartIndex + $shiftSegmentLength - 3;
        $lunchBreakIndex = max($firstBreakIndex + $shiftSegmentLength, self::START_LUNCHES_AT_INDEX);
        $secondBreakIndex = min($lunchBreakIndex + $shiftSegmentLength, $shiftEndIndex);

        if ($shiftDuration >= (7.5 * 12) || ($shiftDuration >= (4 * 12) && !is_null($employee->next_first_break))) {
            // If second shift, don't overwrite first break
            $logTime = $this->convertIndexToTime($secondBreakIndex, $firstHourOfDay);
            Log::info("Looking for a second break time for {$employee->first_name} at {$logTime}");
            $secondBreakIndex = $this->findBreakIndex($secondBreakIndex, 4, $breakMatrix, $firstHourOfDay);
            $this->markSlots($secondBreakIndex, 4, $breakMatrix);
        } else {
            $secondBreakIndex = null;
        }

        if ($shiftDuration >= (6.5 * 12)) {
            $logTime = $this->convertIndexToTime($lunchBreakIndex, $firstHourOfDay);
            Log::info("Looking for a lunch time for {$employee->first_name} at {$logTime}");
            $lunchBreakIndex = $this->findBreakIndex($lunchBreakIndex, 7, $breakMatrix, $firstHourOfDay);
            $this->markSlots($lunchBreakIndex, 7, $breakMatrix);
        } else {
            $lunchBreakIndex = null;
        }
        $shift->lunch_break = $this->convertIndexToTime($lunchBreakIndex, $firstHourOfDay); // To be referenced by Assign Yard logic

        if (is_null($employee->next_first_break)) { // Second shift, don't overwrite first break
            if ($shiftDuration >= (4 * 12)) {
                $logTime = $this->convertIndexToTime($firstBreakIndex, $firstHourOfDay);
                Log::info("Looking for a first break time for {$employee->first_name} at {$logTime}");
                $firstBreakIndex = $this->findBreakIndex($firstBreakIndex, 4, $breakMatrix, $firstHourOfDay);
                $this->markSlots($firstBreakIndex, 4, $breakMatrix);
                $shift->first_break = $this->convertIndexToTime($firstBreakIndex, $firstHourOfDay); // To be referenced by Assign Yard logic
            } else {
                $firstBreakIndex = null;
                $shift->first_break = null;
            }
        }

        $employee->update([
            'next_first_break' => $employee->next_first_break ?? $this->convertIndexToTime($firstBreakIndex, $firstHourOfDay),
            'next_lunch_break' => $this->convertIndexToTime($lunchBreakIndex, $firstHourOfDay),
            'next_second_break' => $this->convertIndexToTime($secondBreakIndex, $firstHourOfDay)
        ]);
        return $breakMatrix;
    }

    /**
     * @param int $breakIndex
     * @param int $duration
     * @param array $breakMatrix
     * @param int $firstHourOfDay
     * @return int
     * @throws Exception
     */
    public function findBreakIndex(int $breakIndex, int $duration, array $breakMatrix, int $firstHourOfDay): int
    {
        $maxPeopleOnBreak = 0;
        $searcherIndex = $breakIndex;
        while (true) {
            $searcherIndex = $this->findNextAvailableSlot($searcherIndex, $duration, $maxPeopleOnBreak++, $breakMatrix, $firstHourOfDay);
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
     * @param int $firstHourOfDay
     * @return int|null
     * @throws Exception
     */
    function findNextAvailableSlot(int $startIndex, int $duration, int $maximum, array $breakMatrix, int $firstHourOfDay): int|null
    {
        for ($i = 0; $i < $duration; $i++) {
            if (!isset($breakMatrix[$startIndex + $i])) {
                // No available slots if we pass 7:00pm
                Log::warning('Failed to find shift break time before 7pm.');
                return null;
            }
            $logTime = $this->convertIndexToTime($startIndex + $i, $firstHourOfDay);
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
     * @param Collection $rotations
     * @param Collection $yards
     * @param Collection $shifts
     */
    public function updateYardAssignments(Collection $rotations, Collection $yards, Collection $shifts): void
    {
        $logMessages = [];
        $lastYardHourIds = [];
        $lastYardHourId = null;

        foreach ($rotations as $rotation) {
            $startOfHour = Carbon::today()->setTimeFromTimeString($rotation->start_time);
            $endOfHour = Carbon::today()->setTimeFromTimeString($rotation->end_time);
            $logMessages[] = 'Assigning Hour: ' . $startOfHour->format('h:ia');
            Log::info(end($logMessages));

            // Filter employees who are working during this hour and not on break
            $availableEmployees = $shifts->filter(function ($shift) use ($startOfHour, $endOfHour) {
                $isWorking = isset($shift->labor->scheduled_hours) &&
                    str_contains($shift->department, self::BACK_OF_HOUSE) &&
                    !str_contains($shift->role, self::TRAINING) &&
                    !str_contains($shift->role, self::EVENT) &&
                    Carbon::parse($shift->start_at)->lessThanOrEqualTo($startOfHour) &&
                    Carbon::parse($shift->end_at)->greaterThanOrEqualTo($endOfHour);

//                $noBreaks = (!isset($shift->first_break) ||
//                        !(Carbon::parse($shift->first_break)->between($startOfHour, $endOfHour, false) ||
//                            Carbon::parse($shift->first_break)->addMinutes(15)->between($startOfHour, $endOfHour, false))) &&
                $noBreaks = (!isset($shift->lunch_break) ||
                    !(Carbon::parse($shift->lunch_break)->between($startOfHour, $endOfHour, false) ||
                        Carbon::parse($shift->lunch_break)->addMinutes(30)->between($startOfHour, $endOfHour, false)));

                $notSuperHandoff = !str_contains($shift->role, self::SUPERVISOR) ||
                    !($startOfHour->between(Carbon::parse($shift->start_at), Carbon::parse($shift->start_at)->addMinutes(59)) ||
                        $endOfHour->between(Carbon::parse($shift->end_at)->subMinutes(59), Carbon::parse($shift->end_at)));

                return $isWorking && $noBreaks && $notSuperHandoff;
            });

            foreach ($yards as $yard) {
                $availableEmployees = $availableEmployees->sortBy(function ($employee) use ($yard) {
                    return [$employee->yardHoursWorked[$yard->id], $employee->start_at];
                });

                $logMessages[] = 'Available employees: ' . $availableEmployees->pluck('first_name')->implode(', ');
                Log::info(end($logMessages));

                $assigned = false;
                // Not an Employee object, it's a Homebase shift
                while ($availableEmployees->isNotEmpty()) {
                    $employee = $availableEmployees->shift();

                    // Check if employee worked the same yard last hour
                    if (isset($lastYardHourIds[$employee->user_id][$yard->id]) && $lastYardHourId
                        && $lastYardHourIds[$employee->user_id][$yard->id] == $lastYardHourId) {
                        $logMessages[] = $employee->first_name . ' worked this yard within the last hour, skipping';
                        Log::info(end($logMessages));

                        if ($availableEmployees->isNotEmpty()) {
                            $availableEmployees->push($employee);
                            continue;
                        }
                    }


                    $logMessages[] = $employee->first_name . ' assigned, having ' .
                        $employee->yardHoursWorked[$yard->id] . ' hours in ' . $yard->name;
                    Log::info(end($logMessages));
                    $this->assignEmployee($yard, $rotation, $employee, $shifts, $lastYardHourIds);
                    $assigned = true;
                    break;
                }

                // If no employee was assigned due to all of them working the same yard last hour, allow back-to-back assignment
                if (!$assigned) {
                    if ($availableEmployees->isNotEmpty()) {
                        $employee = $availableEmployees->first();
                        $logMessages[] = 'No other employees available, assigning ' . $employee->first_name . ' to ' . $yard;
                        Log::info(end($logMessages));
                        $this->assignEmployee($yard, $rotation, $employee, $shifts, $lastYardHourIds);
                    } else {
                        // Nobody wants to work these days
                        EmployeeYardRotation::where('yard_id', $yard->id)->where('rotation_id', $rotation->id)->delete();
                    }
                }
            }

            EmployeeYardRotation::where('yard_id', self::FLOATER_YARD_ID)->where('rotation_id', $rotation->id)
                ->whereNotIn('homebase_user_id', $availableEmployees->pluck('user_id'))->delete();
            EmployeeYardRotation::insertOrIgnore($availableEmployees->map(fn($employee) => [
                'homebase_user_id' => $employee->user_id,
                'yard_id' => self::FLOATER_YARD_ID,
                'rotation_id' => $rotation->id,
                'created_at' => Carbon::now(),
            ])->all());

            $lastYardHourId = $rotation->id;
        }
    }

    /**
     * @param Yard $yard
     * @param Rotation $hour
     * @param $employee // Homebase Shift stdClass
     * @param mixed $shifts
     * @param array $lastYard
     */
    public function assignEmployee(Yard $yard, Rotation $hour, $employee, mixed &$shifts, array &$lastYard): void
    {
        EmployeeYardRotation::updateOrInsert(['yard_id' => $yard->id, 'rotation_id' => $hour->id],
            ['homebase_user_id' => $employee?->user_id, 'updated_at' => now(), 'created_at' => now(),]
        );

        $shifts = $shifts->map(function ($e) use ($employee, $yard) {
            if ($e->user_id === $employee->user_id) {
                $e->yardHoursWorked[$yard->id]++;
            }
            return $e;
        });

        $lastYard[$employee->user_id][$yard->id] = $hour->id;
    }

    /**
     * @param int|null $index
     * @param int $firstHourOfDay
     * @return Carbon|null
     */
    public function convertIndexToTime(?int $index, int $firstHourOfDay): Carbon|null
    {
        if ($index == null) return null;
        return Carbon::parse((floor($index / 12) + $firstHourOfDay) . ':' . (($index % 12) * 5));
    }

    /**
     * @param Carbon $shiftTime
     * @param int $firstHourOfDay
     * @return int
     */
    public function convertTimeToIndex(Carbon $shiftTime, int $firstHourOfDay): int
    {
        return ($shiftTime->hour - $firstHourOfDay) * 12 + ($shiftTime->minute / 5);
    }
}
