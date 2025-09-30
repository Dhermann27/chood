<?php

use App\Enums\ServiceSyncStatus;
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
            $table->unsignedBigInteger('booking_id')->nullable();

            $table->unsignedBigInteger('pet_id')->nullable();
            $table->foreign('pet_id')->references('pet_id')->on('dogs')
                ->onDelete('set null');
            $table->foreignId('service_id')->constrained('services');

            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();

            $table->string('google_event_id')->nullable();
            $table->string('google_color')->nullable();
            $table->string('sync_status')->default(ServiceSyncStatus::Pending->value);

            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->foreign('completed_by')->references('homebase_user_id')->on('employees')->cascadeOnDelete();

            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->string('last_error_code', 8)->nullable()->index();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'pet_id']);
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
