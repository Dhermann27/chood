<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->string('gingr_breed_id')->unique();
            $table->string('name');
            $table->timestamps();
        });
        DB::update('ALTER TABLE breeds AUTO_INCREMENT = 1000');
    }

    public function down(): void
    {
        Schema::dropIfExists('breeds');
    }
};
