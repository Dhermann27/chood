<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medication_id')->nullable()->index();
            $table->unsignedBigInteger('pet_id')->nullable()->index();
            $table->integer('type_id')->nullable();
            $table->string('type');
            $table->text('description')->nullable();
            $table->dateTime('modified_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();
        });
        DB::update('ALTER TABLE medications AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
