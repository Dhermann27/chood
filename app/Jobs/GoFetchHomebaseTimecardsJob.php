<?php

namespace App\Jobs;

use App\Models\Employee;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoFetchHomebaseTimecardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const TIMECARDS_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const TIMECARDS_URL_SUFFIX = '/timecards?start_date=TODAY&end_date=TODAY&date_filter=clock_in';

    /**
     * Execute the job.
     *
     * @return void
     * @throws ConnectionException|Exception
     */
    public function handle(): void
    {
        $url = self::TIMECARDS_URL_PREFIX . config('services.homebase.loc_id') .
            str_replace('TODAY', Carbon::today()->toDateString(), self::TIMECARDS_URL_SUFFIX);


        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.homebase.api_key'),
//                'Accept'        => 'application/vnd.homebase-v1+json',
            ])->get($url);

            if ($response->successful()) {
                $timecards = json_decode($response->getBody()->getContents());
                $presentUserIds = [];

                foreach ($timecards as $timecard) {
                    if ($timecard->clock_out === null) {
                        $presentUserIds[] = $timecard->user_id;
                    }
                }

                Employee::whereIn('homebase_user_id', $presentUserIds)->where('is_working', '!=', 1)
                    ->update(['is_working' => 1]);

                Employee::whereNotIn('homebase_user_id', $presentUserIds)->where('is_working', '!=', 0)
                    ->update(['is_working' => 0]);

            } else {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to fetch Homebase timecards.');
            }
        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }
}
