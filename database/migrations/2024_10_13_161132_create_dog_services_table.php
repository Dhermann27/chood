<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable()->unique();
            $table->unsignedBigInteger('order_id')->nullable();

            $table->unsignedBigInteger('pet_id')->nullable();
            $table->foreign('pet_id')->references('pet_id')->on('dogs')
                ->onDelete('set null');
            $table->string('pet_name')->nullable();
            $table->foreignId('service_id')->constrained('services');

            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();

            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->foreign('completed_by')->references('wiw_user_id')->on('employees')->cascadeOnDelete();

            $table->timestamps();
        });
        DB::update('ALTER TABLE appointments AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
