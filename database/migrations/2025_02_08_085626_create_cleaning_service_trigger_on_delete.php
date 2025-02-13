<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE TRIGGER update_cleaning_status_after_dog_delete
            AFTER DELETE ON dogs
            FOR EACH ROW
            BEGIN
                -- Check if the dog had an associated cabin and update cleaning_status
                IF OLD.cabin_id IS NOT NULL THEN
                    IF NOT EXISTS (
                        SELECT 1 FROM cleaning_status
                            WHERE cabin_id = OLD.cabin_id AND cleaning_type = 'deep' AND completed_at IS NULL
                    ) THEN
                        -- If no such row exists, insert/update the cleaning_status
                        INSERT INTO cleaning_status (cabin_id, cleaning_type, created_by, created_at)
                        VALUES (OLD.cabin_id, 'daily', 'TriggerInsert', NOW())
                        ON DUPLICATE KEY UPDATE
                            cleaning_type = 'daily',
                            employee_id = NULL,
                            completed_at = NULL,
                            updated_by = 'TriggerUpdate',
                            updated_at = NOW();
                    END IF;
                END IF;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_cleaning_status_after_dog_delete');
    }
};
