<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\CleaningStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkCabinsForCleaningJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $today = Carbon::today();
        if (Carbon::today()->isSunday()) {
            CleaningStatus::query()->update([
                'cleaning_type' => 'deep',
                'homebase_user_id' => null,
                'completed_at' => null,
                'updated_by' => 'MCFCJobIsSunday',
                'updated_at' => Carbon::now()
            ]);
        } else {
            $cabins = Cabin::whereHas('dogs')->with(['dogs', 'cleaningStatus'])->get()
                ->map(function ($cabin) use ($today) {
                    $checkout = Carbon::parse($cabin->dogs->first()->checkout)->startOfDay();
                    return [
                        'cabin_id' => $cabin->id,
                        'cleaning_type' => $checkout->lessThanOrEqualTo($today)
                            ? CleaningStatus::STATUS_DEEP : CleaningStatus::STATUS_DAILY,
                        'cleaning_status' => $cabin->cleaningStatus,
                    ];
                });

            foreach ($cabins as $cabin) {
                if ($cabin['cleaning_status']) {
                    $cabin['cleaning_status']->update([
                        'cleaning_type' => $cabin['cleaning_type'],
                        'homebase_user_id' => null,
                        'completed_at' => null,
                        'updated_by' => 'MCFCJobNotSunday',
                        'updated_at' => now(),
                    ]);
                } else {
                    CleaningStatus::create([
                        'cabin_id' => $cabin['cabin_id'],
                        'cleaning_type' => $cabin['cleaning_type'],
                        'created_by' => 'MCFCJobNotSunday',
                        'created_at' => now(),
                    ]);
                }
            }

        }
    }
}
