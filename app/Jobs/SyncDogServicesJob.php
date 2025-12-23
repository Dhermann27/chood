<?php

namespace App\Jobs;

use App\Enums\ServiceSyncStatus;
use App\Models\AppointmentsEvents;
use App\Services\GoogleCalendarService;
use Google\Service\Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncDogServicesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('high');
    }
    
    public function shouldDispatch(): bool
    {
        return !app()->isDownForMaintenance();
    }

    /**
     * @throws Exception
     */
    public function handle(GoogleCalendarService $calendar): void
    {
        $lastRunToken = DB::table('appointments')
            ->whereNotNull('sync_token')
            ->max('sync_token');  // may be null on first run
        $newRunToken = now('UTC')->format('YmdHis');

        $eligibleIdsJsonSub = DB::query()->from('appointments')
            ->selectRaw('COALESCE(JSON_ARRAYAGG(id), JSON_ARRAY())')->where(function ($q) {
                $q->where('sync_status', ServiceSyncStatus::Ready)
                    ->orWhere(function ($q2) {
                        $q2->where('sync_status', ServiceSyncStatus::Error)->where('retry_count', '<', 5);
                    });
            })->where(function ($q) use ($lastRunToken) {
                $q->whereNull('sync_token')->orWhere('sync_token', '=', $lastRunToken);
            })->whereNotNull('scheduled_start')->whereNotNull('scheduled_end');


        $groups = AppointmentsEvents::query()
            ->whereRaw('JSON_OVERLAPS(ids_json, (' . $eligibleIdsJsonSub->toSql() . '))', $eligibleIdsJsonSub->getBindings())
            ->get();   // <- 1 result set: each row = 1 calendar event

        $rows = $calendar->buildRowsFromAppointments($groups, $newRunToken);
        $calendar->upsertAndReconcileGroup($rows, $lastRunToken);

        $updates = [];

    }

}
