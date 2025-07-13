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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('homebase_user_id');
            $table->foreign('homebase_user_id')->references('homebase_user_id')->on('employees')
                ->onDelete('cascade');
//            $table->date('shift_date'); // For identifying the specific day
            $table->tinyInteger('is_working')->default('0');
            $table->string('role')->default('Camp Counselor');
            $table->time('start_time');
            $table->time('end_time');
            $table->time('next_first_break')->nullable();
            $table->time('next_lunch_break')->nullable();
            $table->time('next_second_break')->nullable();
            $table->integer('fairness_score')->default(0);
            $table->timestamps();
            $table->unique(['homebase_user_id', 'start_time', 'end_time']);
        });
        DB::update('ALTER TABLE shifts AUTO_INCREMENT = 1000');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
