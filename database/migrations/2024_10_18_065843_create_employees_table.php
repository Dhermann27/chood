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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('homebase_id')->unique();
            $table->index('homebase_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('photo_file_name')->nullable();
            $table->date('birthday');
            $table->timestamps();
        });
        DB::update('ALTER TABLE employees AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
