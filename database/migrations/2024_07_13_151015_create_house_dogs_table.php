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
            $table->string('firstname')->default('Dog');
            $table->fullText('firstname');
            $table->string('lastname')->default('Smith');
            $table->fullText('lastname');
            $table->string('gender')->nullable();
            $table->string('size')->nullable();
            $table->string('photoUri')->nullable();
            $table->foreignId('cabin_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('is_inhouse')->default(1);
            $table->dateTime('checkout')->nullable();
            $table->timestamps();
        });
        DB::update('ALTER TABLE dogs AUTO_INCREMENT = 1000');


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
