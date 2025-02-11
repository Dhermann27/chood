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
                'employee_id' => null,
                'completed_at' => null,
            ]); // TODO: Make sure to delete rows after they are done on Sunday
        } else {
            $cabins = Cabin::whereHas('dogs')->with(['dogs' => function ($query) {
                $query->orderBy('checkout', 'asc');
            }])->get()->map(function ($cabin) use ($today) {
                $checkout = Carbon::parse($cabin->dogs->first()->checkout)->startOfDay();
                return [
                    'cabin_id' => $cabin->id,
                    'cleaning_type' => $checkout->lessThanOrEqualTo($today)
                        ? CleaningStatus::STATUS_DEEP : CleaningStatus::STATUS_DAILY
                ];
            });
            foreach ($cabins as $cabin) {
                CleaningStatus::updateOrCreate(
                    ['cabin_id' => $cabin['cabin_id']],
                    ['cleaning_type' => $cabin['cleaning_type'], 'employee_id' => null, 'completed_at' => null]
                );
            }
        }
    }
}
