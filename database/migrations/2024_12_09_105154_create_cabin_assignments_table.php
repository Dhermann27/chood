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
        Schema::create('cabin_assignments', function (Blueprint $table) {
            $table->string('description')->nullable();
            $table->foreignId('dog_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('cabin_id')->unique()->constrained()->onDelete('cascade');
            $table->string('service_ids')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabin_assignments');
    }
};
