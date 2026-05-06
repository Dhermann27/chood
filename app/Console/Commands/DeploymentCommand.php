<?php

namespace App\Console\Commands;

use App\Jobs\GoFetchServiceListJob;
use App\Jobs\WIW\GoFetchEmployeesJob;
use App\Jobs\WIW\GoFetchShiftsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeploymentCommand extends Command
{
    protected $signature = 'app:chood-deploy {--rollback-db= : Rollback database name to restore persistent data from}';
    protected $description = 'Initial deployment run of daily() (i.e. midnight) jobs';

    public function handle(): void
    {
        GoFetchEmployeesJob::dispatchSync();

        if ($rollbackDb = $this->option('rollback-db')) {
            $this->restoreFromRollback($rollbackDb);
        }

        GoFetchServiceListJob::dispatchSync();
        GoFetchShiftsJob::dispatchSync();
    }

    private function restoreFromRollback(string $rollbackDb): void
    {
        $db = "`{$rollbackDb}`";

        $this->info("Restoring persistent data from {$rollbackDb}...");

        $this->tryStatement("
            INSERT INTO cleaning_status
                (cabin_id, wiw_user_id, cleaning_type, completed_at, created_by, updated_by, created_at, updated_at)
            SELECT cabin_id, wiw_user_id, cleaning_type, completed_at, created_by, updated_by, created_at, updated_at
            FROM {$db}.cleaning_status
        ", 'Cleaning_status restored');

        $this->tryStatement("
            INSERT INTO dogs (pet_id, cabin_id, rest_starts_at, break_type_id, yard_id)
            SELECT pet_id, cabin_id, rest_starts_at, break_type_id, yard_id
            FROM {$db}.dogs WHERE pet_id IS NOT NULL
        ", 'Dogs restored');

        $this->tryStatement("
            UPDATE shifts AS s
            JOIN {$db}.shifts AS sp ON s.wiw_user_id = sp.wiw_user_id
            SET s.next_first_break  = sp.next_first_break,
                s.next_lunch_break  = sp.next_lunch_break,
                s.next_second_break = sp.next_second_break,
                s.updated_at        = NOW()
        ", 'Breaks restored');

        $this->tryStatement("
            UPDATE employee_yard_rotations AS eyr
            JOIN {$db}.employee_yard_rotations AS eyrp
                ON eyr.wiw_user_id = eyrp.wiw_user_id AND eyr.rotation_id = eyrp.rotation_id
            SET eyr.yard_id = eyrp.yard_id
            WHERE eyrp.yard_id != 999
        ");

        $this->tryStatement("
            DELETE eyr FROM employee_yard_rotations AS eyr
            JOIN employee_yard_rotations AS eyrp
                ON eyr.wiw_user_id = eyrp.wiw_user_id AND eyr.rotation_id = eyrp.rotation_id
            WHERE eyr.yard_id = 999 AND eyrp.yard_id != 999
        ", 'Yard rotations restored');
    }

    private function tryStatement(string $sql, string $successMessage = ''): void
    {
        try {
            DB::statement($sql);
            if ($successMessage) $this->info($successMessage);
        } catch (Throwable $e) {
            $this->warn("SQL warning: {$e->getMessage()}");
        }
    }
}
