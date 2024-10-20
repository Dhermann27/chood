<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\CleaningStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkCabinsForCleaning implements ShouldQueue
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
        $cleaningServices = Cabin::whereHas('dogs')->with(['dogs' => function($query) {
            $query->orderBy('checkout', 'asc');
        }])->get()->map(function ($cabin) use ($today) {
            $checkout = Carbon::parse($cabin->dogs->first()->checkout)->startOfDay();
            return [
                'cabin_id' => $cabin->id,
                'cleaning_type' => $checkout->lessThanOrEqualTo($today)
                    ? CleaningStatus::STATUS_DEEP : CleaningStatus::STATUS_DAILY
            ];
        });
        foreach ($cleaningServices as $cleaningService) {
            CleaningStatus::updateOrCreate(
                ['cabin_id' => $cleaningService['cabin_id']],
                ['cleaning_type' => $cleaningService['cleaning_type']]
            );
        }
    }
}
