<?php

namespace App\Jobs\WIW;

use App\Models\Employee;
use App\Services\WiwService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GoFetchEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @throws ConnectionException|Exception
     */
    public function handle(WiwService $wiw): void
    {
        try {
            $data = $wiw->getV2('users', ['include_deleted' => false]);
            $users = collect($data['users'] ?? []);

            $wiwIds = $users->pluck('id');

            foreach ($users as $user) {
                Employee::updateOrCreate(
                    ['wiw_user_id' => $user['id']],
                    ['first_name' => $user['first_name'], 'last_name' => $user['last_name']]
                );
            }

            Employee::whereNotIn('wiw_user_id', $wiwIds)->delete();

        } catch (ConnectionException $e) {
            Log::error('Connection to WIW API failed.', ['error' => $e->getMessage()]);
        }
    }
}
