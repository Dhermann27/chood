<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeslots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('display_order')->default(0);
        });
        DB::update('ALTER TABLE timeslots AUTO_INCREMENT = 1000');
    }

    public function down(): void
    {
        Schema::dropIfExists('timeslots');
    }
};
