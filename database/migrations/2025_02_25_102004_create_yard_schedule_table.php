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
        Schema::create('yard_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('yard_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('homebase_user_id')->nullable();
            $table->timestamps();
        });
        DB::update('ALTER TABLE yard_assignments AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_assignments');
    }
};
