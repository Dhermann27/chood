<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pet_id')->nullable()->index();
            $table->string('accountId')->nullable();
            $table->string('firstname')->default('Dog');
            $table->fullText('firstname');
            $table->string('lastname')->default('Smith');
            $table->fullText('lastname');
            $table->string('gender')->nullable();
            $table->integer('weight')->nullable();
            $table->string('photoUri')->nullable();
            $table->string('nickname')->nullable();
            $table->foreignId('cabin_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('is_inhouse')->default(1);
            $table->dateTime('checkin')->nullable();
            $table->dateTime('checkout')->nullable();
            $table->timestamp('rest_starts_at')->nullable();
            $table->integer('rest_duration_minutes')->nullable();
            $table->timestamps();
        });
        DB::update('ALTER TABLE dogs AUTO_INCREMENT = 1000');
        DB::statement(<<<SQL
                ALTER TABLE dogs
                ADD COLUMN size_letter VARCHAR(2)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN weight >= 40 THEN 'L'
                        WHEN weight >= 30 AND LOWER(nickname) LIKE '%large%' THEN 'L'
                        WHEN weight >= 30 AND LOWER(nickname) LIKE '%small%' THEN 'S'
                        WHEN weight >= 30 THEN 'LS'
                        WHEN weight >= 15 THEN 'S'
                        WHEN weight >= 10 AND LOWER(nickname) LIKE '%teacup%' THEN 'T'
                        WHEN weight >= 10 AND LOWER(nickname) LIKE '%small%' THEN 'S'
                        WHEN weight >= 10 THEN 'ST'
                        ELSE 'T'
                    END
                ) VIRTUAL;
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dogs', function (Blueprint $table) {
            $indexExists = DB::select("SHOW INDEXES FROM dogs WHERE Key_name = 'dogs_firstname_fulltext'");
            if ($indexExists) {
                $table->dropFullText('dogs_firstname_fulltext');
            }

            $indexExists = DB::select("SHOW INDEXES FROM dogs WHERE Key_name = 'dogs_lastname_fulltext'");
            if ($indexExists) {
                $table->dropFullText('dogs_lastname_fulltext');
            }
        });
        Schema::dropIfExists('dogs');

    }
};
