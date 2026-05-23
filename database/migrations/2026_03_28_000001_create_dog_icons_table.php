<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dog_icons', function (Blueprint $table) {
            $table->unsignedBigInteger('pet_id');
            $table->unsignedInteger('icon_id');
            $table->primary(['pet_id', 'icon_id']);
            $table->foreign('pet_id')->references('pet_id')->on('dogs')->cascadeOnDelete();
            $table->foreign('icon_id')->references('id')->on('icons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dog_icons');
    }
};
