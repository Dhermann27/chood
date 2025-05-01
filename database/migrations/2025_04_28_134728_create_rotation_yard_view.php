<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE VIEW rotation_yard_view AS
            SELECT
                rotations.id AS rotation_id,
                yards.id AS yard_id,
                yards.name AS yard_name,
                yards.display_order,
                employee_yard_rotations.homebase_user_id
            FROM rotations
            CROSS JOIN yards
            LEFT JOIN employee_yard_rotations
                ON employee_yard_rotations.rotation_id = rotations.id
                AND employee_yard_rotations.yard_id = yards.id
            ORDER BY rotations.start_time, yards.display_order
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS rotation_yard_view");
    }

};
