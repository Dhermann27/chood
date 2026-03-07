<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $smallYardId = 1000;
        $largeYardId = 1001;

        $trgInsert = 'dogs_size_yard_bi';
        $trgUpdate = 'dogs_size_yard_bu';

        DB::unprepared("DROP TRIGGER IF EXISTS {$trgInsert};");
        DB::unprepared("DROP TRIGGER IF EXISTS {$trgUpdate};");

        $sizeCase = "
            CASE
                WHEN NEW.weight >= 30 AND LOWER(NEW.nickname) LIKE '%large%' THEN 'L'
                WHEN NEW.weight >= 10 AND LOWER(NEW.nickname) LIKE '%small%' THEN 'S'
                WHEN NEW.weight <= 15 AND LOWER(NEW.nickname) LIKE '%teacup%' THEN 'T'
                WHEN NEW.weight >= 40 THEN 'L'
                WHEN NEW.weight >= 30 THEN 'LS'
                WHEN NEW.weight >= 15 THEN 'S'
                WHEN NEW.weight >= 10 THEN 'ST'
                ELSE 'T'
            END
        ";

        $yardCase = "
            CASE
                WHEN {$sizeCase} IN ('L','LS') THEN {$largeYardId}
                ELSE {$smallYardId}
            END
        ";

        DB::unprepared("
            CREATE TRIGGER {$trgInsert}
            BEFORE INSERT ON dogs
            FOR EACH ROW
            BEGIN
                SET NEW.size_letter = {$sizeCase};

                IF NEW.yard_id IS NULL THEN
                    SET NEW.yard_id = {$yardCase};
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER {$trgUpdate}
            BEFORE UPDATE ON dogs
            FOR EACH ROW
            BEGIN
                IF (NEW.nickname <> OLD.nickname OR NEW.weight <> OLD.weight) THEN
                    SET NEW.size_letter = {$sizeCase};

                    IF (NEW.yard_id <=> OLD.yard_id) OR NEW.yard_id IS NULL THEN
                        SET NEW.yard_id = {$yardCase};
                    END IF;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS dogs_size_yard_bi;");
        DB::unprepared("DROP TRIGGER IF EXISTS dogs_size_yard_bu;");
    }
};
