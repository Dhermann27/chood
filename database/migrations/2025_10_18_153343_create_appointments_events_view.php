<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Testing: add '1ESS'
return new class extends Migration {
    public function up(): void
    {
        $quotedCats = collect(config('services.dd.special_service_cats') ?? [])
            ->map(fn($v) => "'" . str_replace("'", "''", (string)$v) . "'")->implode(',');

        if ($quotedCats === '') {
            $quotedCats = "'__NO_MATCH__'";
        }

        $sql = <<<SQL
CREATE OR REPLACE VIEW `appointments__events` AS
SELECT
    CASE
    WHEN s.code IN ('1ETR','1EPK')
        THEN CONCAT('treat|', DATE_FORMAT(a.scheduled_start, '%Y-%m-%d %H:%i:%s'))
    WHEN s.category IN ({$quotedCats})
        THEN CONCAT('groom|', a.pet_id, '|', DATE_FORMAT(a.scheduled_start, '%Y-%m-%d %H:%i:%s'))
    ELSE CONCAT('single|', a.id)
END AS group_key


    JSON_ARRAYAGG(a.id)             AS ids_json,
    JSON_ARRAYAGG(a.appointment_id) AS appointment_ids_json,
    JSON_ARRAYAGG(a.order_id)       AS order_ids_json,
    JSON_ARRAYAGG(a.booking_id)     AS booking_ids_json,
    JSON_ARRAYAGG(a.pet_id)         AS pet_ids_json,
    JSON_ARRAYAGG(a.pet_name)       AS dog_names_json,
    JSON_ARRAYAGG(a.service_id)     AS service_ids_json,
    JSON_ARRAYAGG(s.name)           AS service_names_json,

    MAX(CASE WHEN a.pet_id IS NOT NULL THEN 1 ELSE 0 END) AS has_any_pet_id,

    MIN(s.category)                 AS service_category,
    MIN(a.google_event_id)          AS google_event_id,
    MIN(a.scheduled_start)          AS scheduled_start,
    MAX(a.scheduled_end)            AS scheduled_end

FROM appointments a
JOIN services s ON s.id = a.service_id
WHERE a.scheduled_start IS NOT NULL
  AND a.scheduled_end IS NOT NULL
GROUP BY group_key
HAVING has_any_pet_id = 1;
SQL;

        DB::statement($sql);
    }


    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `appointments__events`');
    }
};
