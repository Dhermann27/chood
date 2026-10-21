<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rest_break_reasons', function (Blueprint $table) {
            $table->foreignId('rest_break_id')->constrained('rest_breaks')->cascadeOnDelete();
            $table->foreignId('break_reason_id')->constrained('break_reasons')->cascadeOnDelete();
            $table->primary(['rest_break_id', 'break_reason_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_break_reasons');
    }
};
