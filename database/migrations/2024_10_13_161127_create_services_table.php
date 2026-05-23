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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->integer('gingr_id')->unique()->nullable();
            $table->string('name')->nullable();
            $table->string('housing_code')->nullable();
            $table->string('report_category')->nullable();
            $table->unsignedSmallInteger('booking_category_id')->nullable();
            $table->unsignedSmallInteger('account_code_id')->nullable();
            $table->integer('duration')->default(45000); // Full day
            $table->tinyInteger('is_active')->default(true);
        });
        DB::update('ALTER TABLE services AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
