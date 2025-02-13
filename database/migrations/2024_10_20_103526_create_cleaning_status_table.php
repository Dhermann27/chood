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
            $table->string('homebase_id')->nullable();
            $table->foreign('homebase_id')->references('homebase_id')->on('employees')
                ->onDelete('set null');
            $table->enum('cleaning_type', ['daily', 'deep']);
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
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
