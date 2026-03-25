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
            $table->foreignId('pet_id')->constrained('dogs', 'pet_id')->cascadeOnDelete();
            $table->integer('type_id')->nullable();
            $table->string('type');
            $table->foreignId('timeslot_id')->nullable()->constrained('timeslots');
            $table->string('quantity')->nullable();
            $table->string('unit')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
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
