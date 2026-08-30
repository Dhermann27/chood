<?php

namespace App\Console\Commands;

use App\Jobs\GoFetchServiceListJob;
use App\Jobs\WIW\GoFetchEmployeesJob;
use App\Jobs\WIW\GoFetchShiftsJob;
use Carbon\Carbon;
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
        GoFetchServiceListJob::dispatchSync();

        if ($rollbackDb = $this->option('rollback-db')) {
            $this->restoreFromRollback($rollbackDb);
        } else {
            GoFetchShiftsJob::dispatchSync();
        }

        $this->dropOldRollbackDbs();
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
            INSERT INTO dogs (pet_id, cabin_id, rest_starts_at, break_type_id, rest_minutes, yard_id)
            SELECT pet_id, cabin_id, rest_starts_at, break_type_id, rest_minutes, yard_id
            FROM {$db}.dogs WHERE pet_id IS NOT NULL
        ", 'Dogs restored');

        $this->tryStatement("
            INSERT INTO employees (wiw_user_id, first_name, last_name, created_at, updated_at)
            SELECT wiw_user_id, first_name, last_name, created_at, updated_at
            FROM {$db}.employees
            ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), updated_at = VALUES(updated_at)
        ", 'Employees restored');

        $this->tryStatement("
            INSERT INTO shifts (wiw_user_id, role, start_time, end_time, next_first_break, next_lunch_break, next_second_break, fairness_score, created_at, updated_at)
            SELECT wiw_user_id, role, start_time, end_time, next_first_break, next_lunch_break, next_second_break, fairness_score, created_at, updated_at
            FROM {$db}.shifts
            ON DUPLICATE KEY UPDATE role = VALUES(role), start_time = VALUES(start_time), end_time = VALUES(end_time),
                next_first_break = VALUES(next_first_break), next_lunch_break = VALUES(next_lunch_break),
                next_second_break = VALUES(next_second_break), fairness_score = VALUES(fairness_score), updated_at = VALUES(updated_at)
        ", 'Shifts restored');

        $this->tryStatement("
            INSERT INTO cache (`key`, `value`, `expiration`)
            SELECT `key`, `value`, `expiration`
            FROM {$db}.cache
            WHERE `key` LIKE '%yard_preset:%'
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `expiration` = VALUES(`expiration`)
        ", 'Yard preset cache restored');

        $this->tryStatement("DELETE FROM employee_yard_rotations");

        $this->tryStatement("
            INSERT INTO employee_yard_rotations (wiw_user_id, yard_id, rotation_id, created_at, updated_at)
            SELECT wiw_user_id, yard_id, rotation_id, created_at, updated_at
            FROM {$db}.employee_yard_rotations
        ", 'Yard rotations restored');
    }

    private function dropOldRollbackDbs(): void
    {
        try {
            $rows = DB::select("SELECT SCHEMA_NAME AS db_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'laravel_rollback_%'");
            $cutoff = now()->subWeeks(2);

            foreach ($rows as $row) {
                $name = $row->db_name;
                if (!preg_match('/^laravel_rollback_(\d{8}_\d{6})$/', $name, $m)) continue;

                try {
                    $date = Carbon::createFromFormat('Ymd_His', $m[1]);
                } catch (Throwable) {
                    continue;
                }

                if ($date->lt($cutoff)) {
                    $this->tryStatement("DROP DATABASE `{$name}`", "Dropped old rollback DB: {$name}");
                }
            }
        } catch (Throwable $e) {
            $this->warn("Could not clean up old rollback databases: {$e->getMessage()}");
        }
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
