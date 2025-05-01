<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_yard_rotations', function (Blueprint $table) {
            $table->unsignedBigInteger('homebase_user_id');
            $table->foreign('homebase_user_id')->references('homebase_user_id')->on('employees')
                ->onDelete('cascade');
            $table->foreignId('yard_id')->constrained()->onDelete('cascade');
            $table->foreignId('rotation_id')->constrained('rotations')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['homebase_user_id', 'yard_id', 'rotation_id'], 'employee_yard_rotation_unique_index');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_yard_rotations');
    }
};
