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
        Schema::create('dog_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('dogs', 'pet_id')->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('completed_by')->nullable()->constrained('employees', 'homebase_user_id')->onDelete('cascade');
            $table->timestamps();

            $table->index(['service_id', 'pet_id']);
            $table->index(['service_id', 'scheduled_start']);
        });
        DB::update('ALTER TABLE dog_services AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dog_services');
    }
};
