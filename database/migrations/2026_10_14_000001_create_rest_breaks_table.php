<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rest_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pet_id');
            $table->string('dog_name');
            $table->foreignId('break_type_id')->nullable()->constrained('break_types')->nullOnDelete();
            $table->unsignedSmallInteger('rest_minutes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['pet_id', 'ended_at']);
        });
        DB::update('ALTER TABLE rest_breaks AUTO_INCREMENT = 1000');
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_breaks');
    }
};
