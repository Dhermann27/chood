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

    const HOMEBASE_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const TIMECARDS_URL_SUFFIX = '/timecards?start_date=TODAY&end_date=TODAY&date_filter=clock_in';
    const SHIFTS_URL_SUFFIX = '/shifts/';

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
        $url = self::HOMEBASE_URL_PREFIX . config('services.homebase.loc_id') .
            str_replace('TODAY', Carbon::today()->toDateString(), self::TIMECARDS_URL_SUFFIX);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.homebase.api_key'),
            ])->get($url);

            if (!$response->successful()) {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to fetch Homebase timecards.');
            }

            $timecards = json_decode($response->getBody()->getContents());
            $workingCards = collect($timecards)->filter(fn($tc) => $tc->clock_out === null)->values();

            foreach ($workingCards as $timecard) {
                $existingShift = Shift::where('homebase_user_id', $timecard->user_id)->first();

                if (!$existingShift && $timecard->shift_id) {

                    $shiftUrl = self::HOMEBASE_URL_PREFIX . config('services.homebase.loc_id') .
                        self::SHIFTS_URL_SUFFIX . $timecard->shift_id;

                    $shiftResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . config('services.homebase.api_key'),
                    ])->get($shiftUrl);

                    if ($shiftResponse->successful()) {
                        $shiftData = collect(json_decode($shiftResponse->getBody()->getContents()));

                        Shift::create([
                            'homebase_user_id' => $timecard->user_id,
                            'start_time' => Carbon::parse($shiftData['start_at']),
                            'end_time' => Carbon::parse($shiftData['end_at']),
                            'role' => $shiftData['role'] ?? 'Camp Counselor',
                            'is_working' => 1,
                        ]);
                    } else {
                        Log::warning("Failed to fetch shift {$timecard->shift_id} for user {$timecard->user_id}");
                    }
                } else {
                    $existingShift->update(['is_working' => 1]);
                }
            }

            Shift::whereNotIn('homebase_user_id', $workingCards->pluck('user_id'))
                ->where('is_working', 1)
                ->update(['is_working' => 0]);

        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }
}
