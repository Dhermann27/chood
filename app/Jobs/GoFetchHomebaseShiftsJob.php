<?php

namespace App\Jobs;

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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoFetchHomebaseShiftsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const SHIFTS_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const SHIFTS_URL_SUFFIX = '/shifts?start_date=TODAY&end_date=TODAY&open=false&with_note=false&date_filter=start_at';
    const BACK_OF_HOUSE = 'BOH';
    const YARD_WORKER = 'Camp Counselor';


    /**
     * Execute the job.
     *
     * @return void
     * @throws ConnectionException|Exception
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
                $shifts = collect(json_decode($response->getBody()->getContents()))->sortBy('start_at');

                if ($day->isSunday()) $shifts = $this->assignSundayMorningBreaks($shifts);

                // Boolean matrix for workday, each hour in 5-minute intervals (12 intervals per hour)
                $breakMatrix = array_fill(0, 24, array_fill(0, 12, false));
                $startBreaksAt = $day->copy()->setTime(8, 15);
                $startLunchesAt = $day->copy()->setTime(10, 30);

                foreach ($shifts as $shift) {
                    if (isset($shift->labor->scheduled_hours) && str_contains($shift->department, self::BACK_OF_HOUSE)) {
                        $breakMatrix = $this->assignBreaks($shift, $startBreaksAt, $startLunchesAt, $breakMatrix);
                    }
                    $shift->yardHoursWorked = 0;

                }

                $startHourOfDay = config('services.yardAssignments.startHourOfDay');
                $endHourOfDay = $startHourOfDay + config('services.yardAssignments.numberOfHours');
                for ($i = $startHourOfDay; $i < $endHourOfDay; $i++) {
                    $startHour = Carbon::today()->setHour($i);
                    $endHour = Carbon::today()->setHour($i + 1);

                    // Filter employees who are working during this hour and not on break
                    $availableEmployees = $shifts->filter(function ($shift) use ($startHour, $endHour) {
                        // Ensure they are working during this hour
                        $isWorking = isset($shift->labor->scheduled_hours) &&
                            str_contains($shift->role, self::YARD_WORKER) &&
                            Carbon::parse($shift->start_at)->lessThanOrEqualTo($startHour) &&
                            Carbon::parse($shift->end_at)->greaterThanOrEqualTo($endHour);

                        // Ensure they are not on a lunch break during this hour
                        $noLunchBreak = !isset($shift->lunch_break) ||
                            !$shift->lunch_break->between($startHour, $endHour);

                        return $isWorking && $noLunchBreak;
                    })->sortBy('yardHoursWorked');

                    // Assign employees to yards
                    for ($j = 1; $j < config('services.yardAssignments.numberOfYards') + 1; $j++) {
                        $employee = $availableEmployees->shift(); // Get employee with the least yard assignments

                        if ($employee) {
                            // Create the YardAssignment row for this hour and this yard
                            YardAssignment::where('yard_number', $j)
                                ->where('start_time', $startHour)
                                ->update(['homebase_user_id' => $employee->user_id]);

                            // Update the employee's yard hours in the Collection
                            $shifts = $shifts->map(function ($e) use ($employee) {
                                if ($e->user_id === $employee->user_id) {
                                    $e->yardHoursWorked++;
                                }
                                return $e;
                            });
                        }
                    }
                }

            } else {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to fetch Homebase shifts.');
            }
        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }


    /**
     * @throws Exception
     */
    function isSlotAvailable($startTime, $duration, $breakMatrix): bool
    {

        Log::info("Looking for {$startTime}");
        $hourSlot = $startTime->hour;
        $minuteSlot = floor($startTime->minute / 5);
        $slotsNeeded = ceil($duration / 5); // Number of 5-minute slots needed for the break

        for ($i = 0; $i < $slotsNeeded; $i++) {
            if ($breakMatrix[$hourSlot][$minuteSlot]) {
                $loggerMin = $minuteSlot * 5;
                Log::info("{$hourSlot}:{$loggerMin} taken");
                return false; // Slot is occupied
            }
            $loggerMin = $minuteSlot * 5;
            Log::info("{$hourSlot}:{$loggerMin} free, trying next");
            $minuteSlot++;
            if ($minuteSlot == 12) { // If we move past the current hour, increment hour and reset minute slot
                $minuteSlot = 0;
                $hourSlot++;
                if ($hourSlot >= 19) {
                    // No available slots if we pass 7:00pm
                    throw new Exception('Failed to find shift break time before 7pm.');
                }
            }
        }
        return true;
    }

    function markSlots($startTime, $duration, &$breakMatrix): void
    {
        $hourSlot = $startTime->hour;
        $minuteSlot = floor($startTime->minute / 5);
        $slotsNeeded = ceil($duration / 5);

        for ($i = 0; $i < $slotsNeeded; $i++) {
            $breakMatrix[$hourSlot][$minuteSlot] = true; // Mark slot as occupied
            $minuteSlot++;
            if ($minuteSlot == 12) {
                $minuteSlot = 0;
                $hourSlot++;
            }
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
     * @param mixed $shift
     * @param Carbon $startBreaksAt
     * @param Carbon $startLunchesAt
     * @param array $breakMatrix
     * @return array
     * @throws Exception
     */
    public function assignBreaks(mixed &$shift, Carbon $startBreaksAt, Carbon $startLunchesAt, array $breakMatrix): array
    {
        $employee = Employee::where('homebase_user_id', $shift->user_id)->first();

        $shiftStart = Carbon::parse($shift->start_at);
        $shiftDuration = floatval($shift->labor->scheduled_hours);
        $shiftSegmentLength = $shiftDuration * 60 / ($shiftDuration >= 8 ? 4 : ($shiftDuration >= 6.5 ? 3 : 2));

        $firstBreak = $shiftStart->copy()->addMinutes($shiftSegmentLength)
            ->floorMinutes(10)->max($startBreaksAt);
        $lunchBreak = $firstBreak->copy()->addMinutes($shiftSegmentLength)
            ->floorMinutes(10)->max($startLunchesAt);
        $secondBreak = $lunchBreak->copy()->addMinutes($shiftSegmentLength)
            ->floorMinutes(10)->min(Carbon::parse($shift->end_at)->subHours(2));


        if ($shiftDuration >= 8 || ($shiftDuration >= 4 && !is_null($employee->next_first_break))) {
            // If second shift, don't overwrite first break
            while (!$this->isSlotAvailable($secondBreak, 20, $breakMatrix)) {
                $secondBreak->addMinutes(5);
            }
            $this->markSlots($secondBreak, 20, $breakMatrix);
        } else {
            $secondBreak = null;
        }


        if ($shiftDuration >= 6.5) {
            while (!$this->isSlotAvailable($lunchBreak, 35, $breakMatrix)) {
                $lunchBreak->addMinutes(5);
            }
            $this->markSlots($lunchBreak, 35, $breakMatrix);
        } else {
            $lunchBreak = null;
        }
        $shift->lunch_break = $lunchBreak; // To be referenced by YardAssignment logic

        if (is_null($employee->next_first_break)) { // Second shift, don't overwrite first break
            if ($shiftDuration >= 4) {
                while (!$this->isSlotAvailable($firstBreak, 20, $breakMatrix)) {
                    $firstBreak->addMinutes(5);
                }
                $this->markSlots($firstBreak, 20, $breakMatrix);
            } else {
                $firstBreak = null;
            }
        }

        $employee->update([
            'next_first_break' => $employee->next_first_break ?? $firstBreak,
            'next_lunch_break' => $lunchBreak,
            'next_second_break' => $secondBreak
        ]);
        return $breakMatrix;
    }
}
