<?php

namespace App\Jobs\Homebase;

use App\Models\Employee;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoFetchEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const EMPLOYEES_URL_PREFIX = 'https://app.joinhomebase.com/api/public/locations/';
    const EMPLOYEES_URL_SUFFIX = '/employees?with_archived=false';

    /**
     * Execute the job.
     *
     * @return void
     * @throws ConnectionException|Exception
     */
    public function handle(): void
    {
        $url = self::EMPLOYEES_URL_PREFIX . config('services.homebase.loc_id') . self::EMPLOYEES_URL_SUFFIX;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.homebase.api_key'),
//                'Accept'        => 'application/vnd.homebase-v1+json',
            ])->get($url);

            if ($response->successful()) {
                $employees = collect(json_decode($response->getBody()->getContents()));
                $homebaseIds = $employees->pluck('id');

                foreach ($employees as $employee) {
                    Employee::updateOrCreate(['homebase_user_id' => $employee->id],
                        ['first_name' => $employee->first_name, 'last_name' => $employee->last_name]);
                }

                Employee::whereNotIn('homebase_user_id', $homebaseIds)->delete();

            } else {
                Log::error('Failed to fetch data from Homebase API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Failed to fetch Homebase employees.');
            }
        } catch (ConnectionException $e) {
            Log::error('Connection to Homebase API failed.', ['error' => $e->getMessage()]);
        }
    }
}
