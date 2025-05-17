<?php

namespace App\Jobs\Homebase;

use App\Models\Shift;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoFetchTimecardsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const TIMECARDS_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const TIMECARDS_URL_SUFFIX = '/timecards?start_date=TODAY&end_date=TODAY&date_filter=clock_in';

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
                $presentUserIds = collect($timecards)->filter(fn($tc) => $tc->clock_out === null)
                    ->pluck('user_id')->all();

                foreach ($presentUserIds as $homebaseId) {
                    Shift::updateOrCreate(
                        ['homebase_user_id' => $homebaseId],
                        ['is_working' => 1]
                    );
                }

                Shift::whereNotIn('homebase_user_id', $presentUserIds)
                    ->where('is_working', 1)
                    ->update(['is_working' => 0]);

            } else {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to fetch Homebase timecards.');
            }
        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }
}
