<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('break_types', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('short_label')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->enum('behavior', ['countdown', 'lunch', 'unlimited', 'walks_only']);
            $table->unsignedTinyInteger('display_order')->default(0);
        });
        DB::update('ALTER TABLE break_types AUTO_INCREMENT = 1000');
    }

    public function down(): void
    {
        Schema::dropIfExists('break_types');
    }
};
