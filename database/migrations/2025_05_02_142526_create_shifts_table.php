<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('homebase_user_id');
            $table->foreign('homebase_user_id')->references('homebase_user_id')->on('employees')
                ->onDelete('cascade');
//            $table->date('shift_date'); // For identifying the specific day
            $table->tinyInteger('is_working')->default('0');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('next_first_break')->nullable();
            $table->time('next_lunch_break')->nullable();
            $table->time('next_second_break')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
