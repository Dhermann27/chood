<?php

use App\Enums\HousingServiceCodes;
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
        Schema::create('dogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pet_id')->nullable()->unique();
            $table->string('account_id')->nullable();
            $table->string('firstname')->default('Dog');
            $table->string('lastname')->default('Smith');
            $table->string('display_name')->default('Dog');
            $table->string('gender')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->integer('weight')->nullable();
            $table->foreignId('yard_id')->nullable()->constrained()->nullOnDelete()->index();
            $table->string('photoUri')->nullable();
            $table->foreignId('cabin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('housing_code')->default(HousingServiceCodes::BRDC->value);
            $table->dateTime('checkin')->nullable();
            $table->dateTime('checkout')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->timestamp('rest_starts_at')->nullable();
            $table->foreignId('break_type_id')->nullable()->constrained('break_types')->nullOnDelete();
            $table->string('food_type')->nullable();
            $table->string('feeding_method')->nullable();
            $table->text('feeding_notes')->nullable();
            $table->string('services_string')->nullable();
            $table->timestamps();
        });
        DB::update('ALTER TABLE dogs AUTO_INCREMENT = 1000');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dogs');
    }
};
