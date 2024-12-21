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
            $table->string('name');
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
        Schema::dropIfExists('dogs');
    }
};
