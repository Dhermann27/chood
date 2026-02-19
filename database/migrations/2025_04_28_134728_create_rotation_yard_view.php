<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS rotation_yard_view");

        DB::statement("
            CREATE VIEW rotation_yard_view AS
            SELECT
                r.id AS rotation_id,
                y.id AS yard_id,
                y.name AS yard_name,
                y.display_order,
                eyr.homebase_user_id,
                e.first_name
            FROM rotations r
            JOIN employee_yard_rotations eyr
                ON eyr.rotation_id = r.id
            JOIN yards y
                ON y.id = eyr.yard_id
            LEFT JOIN employees e
                ON e.homebase_user_id = eyr.homebase_user_id
            WHERE
                DAYOFWEEK(CURDATE()) != 1
                OR r.is_sunday_hour = 1
            ORDER BY r.start_time, y.display_order;
            ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS rotation_yard_view");
    }

};
