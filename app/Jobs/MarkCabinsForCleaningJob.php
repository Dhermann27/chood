<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\CleaningStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        Log::info('Starting MarkCabinsForCleaningJob');
//        if (Carbon::today()->isSunday()) {
//            $withoutCleaningStatus = Cabin::whereHas('dogs')->whereDoesntHave('cleaningStatus')->with(['dogs'])->get();
//            if ($withoutCleaningStatus->isNotEmpty()) {
//                Log::info(count($withoutCleaningStatus) . ' cabins created with Deep Clean ');
//                CleaningStatus::insert($withoutCleaningStatus->map(function ($cabin) {
//                    return [
//                        'cabin_id' => $cabin->id,
//                        'cleaning_type' => CleaningStatus::STATUS_DEEP,
//                        'created_by' => 'MCFCJobIsSunday',
//                        'created_at' => now(),
//                    ];
//                })->toArray());
//            }
//
//            $cs = CleaningStatus::query()->update([
//                'cleaning_type' => 'deep',
//                'homebase_user_id' => null,
//                'completed_at' => null,
//                'updated_by' => 'MCFCJobIsSunday',
//                'updated_at' => Carbon::now()
//            ]);
//            Log::info('Sunday rows updated: ' . $cs);
//        } else {

        $cabins = Cabin::whereHas('dogs')->with(['dogs', 'cleaningStatus'])->get();

        $cabinsWithCheckoutDue = $cabins->filter(function ($cabin) {
            return Carbon::parse($cabin->dogs->first()->checkout)->startOfDay()->lessThanOrEqualTo(Carbon::today());
        });

        $withCleaningStatus = $cabins->filter(fn($cabin) => $cabin->cleaningStatus);
        $withoutCleaningStatus = $cabins->filter(fn($cabin) => !$cabin->cleaningStatus);

        if ($withCleaningStatus->isNotEmpty()) {
            $cs = CleaningStatus::whereIn('cabin_id', $withCleaningStatus->pluck('id'))
                ->update([
                    'cleaning_type' => CleaningStatus::STATUS_DAILY,
                    'homebase_user_id' => null,
                    'completed_at' => null,
                    'updated_by' => 'MCFCJobNotSundayDaily',
                    'updated_at' => now(),
                ]);
            Log::info($cs . ' cabins updated with Daily Clean ');
        }

        if ($withoutCleaningStatus->isNotEmpty()) {
            CleaningStatus::insert($withoutCleaningStatus->map(function ($cabin) {
                return [
                    'cabin_id' => $cabin->id,
                    'cleaning_type' => CleaningStatus::STATUS_DAILY,
                    'created_by' => 'MCFCJobNotSundayDaily',
                    'created_at' => now(),
                ];
            })->toArray());
            Log::info(count($withoutCleaningStatus) . ' cabins inserted with Daily Clean ');
        }

        if ($cabinsWithCheckoutDue->isNotEmpty()) {
            CleaningStatus::whereIn('cabin_id', $cabinsWithCheckoutDue->pluck('id'))
                ->update([
                    'cleaning_type' => CleaningStatus::STATUS_DEEP,
                    'updated_by' => 'MCFCJobNotSundayDeep',
                    'updated_at' => now(),
                ]);
        }
    }
//    }
}
