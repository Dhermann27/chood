<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('homebase_user_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->tinyInteger('is_working')->default('0');
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
        Schema::dropIfExists('employees');
    }
};
