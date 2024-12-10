<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    const SELECT = "SELECT d.id, IFNULL(d.name,ca.description) as name, d.gender, d.size, d.photoUri, IFNULL(d.cabin_id,ca.cabin_id) as cabin_id, d.checkout, ca.service_ids, IFNULL(d.created_at, ca.created_at) as created_at, IFNULL(d.updated_at, ca.updated_at) as updated_at";

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("CREATE VIEW inhouse_dogs AS " . self::SELECT . " FROM dogs d
		    LEFT JOIN cabin_assignments ca ON ca.dog_id = d.id
        UNION " . self::SELECT . " FROM dogs d
		    RIGHT JOIN cabin_assignments ca ON ca.dog_id = d.id;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS inhouse_dogs;');
    }
};
