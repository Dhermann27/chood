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
        Schema::create('feedings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feeding_id')->nullable()->index();
            $table->unsignedBigInteger('pet_id')->nullable()->index();
            $table->string('type');
            $table->text('description')->nullable();
            $table->dateTime('modified_at')->default(DB::raw('CURRENT_TIMESTAMP'));
;
            $table->timestamps();
        });
        DB::update('ALTER TABLE feedings AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedings');
    }
};
