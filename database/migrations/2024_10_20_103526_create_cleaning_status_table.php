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
        Schema::create('cleaning_status', function (Blueprint $table) {
            $table->foreignId('cabin_id')->unique()->constrained()->onDelete('cascade');
            $table->enum('cleaning_type', ['daily', 'deep']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cleaning_status');
    }
};
