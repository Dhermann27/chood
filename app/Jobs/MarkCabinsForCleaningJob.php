<?php

namespace App\Jobs;

use App\Models\Cabin;
use App\Models\CleaningStatus;
use App\Models\Dog;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MarkCabinsForCleaningJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info('Starting MarkCabinsForCleaningJob');

        $deleted = Dog::whereNotNull('checked_out_at')->where('checked_out_at', '<', Carbon::today())->delete();
        Log::info("Deleted {$deleted} checked-out dog records from previous day(s)");

        // Only remove feeding blocks whose dog has already checked out; boarding dogs keep theirs.
        $activeAccountIds = Dog::whereNotNull('pet_id')->whereNull('checked_out_at')
            ->pluck('account_id')->filter()->unique();
        Dog::whereNull('pet_id')->whereNotIn('account_id', $activeAccountIds)->delete();

        $cabins = Cabin::whereHas('dogs')->with(['dogs', 'cleaningStatus'])->get();

        $cabinsWithCheckoutDue = $cabins->filter(function ($cabin) {
            $dog = $cabin->dogs->first();
            return in_array($dog->housing_code, ['BRDC', 'BRDL'])
                && Carbon::parse($dog->checkout)->startOfDay()->lessThanOrEqualTo(Carbon::today());
        });


        $withCleaningStatus = $cabins->filter(fn($cabin) => $cabin->cleaningStatus);
        $withoutCleaningStatus = $cabins->filter(fn($cabin) => !$cabin->cleaningStatus);

        if ($withCleaningStatus->isNotEmpty()) {
            $cs = CleaningStatus::whereIn('cabin_id', $withCleaningStatus->pluck('id'))
                ->update([
                    'cleaning_type' => CleaningStatus::STATUS_DAILY,
                    'wiw_user_id' => null,
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
}
