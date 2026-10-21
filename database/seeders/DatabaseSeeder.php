<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CabinSeeder::class);
        $this->call(RotationSeeder::class);
        $this->call(YardSeeder::class);
        $this->call(TimeslotSeeder::class);
        $this->call(BreakTypeSeeder::class);
        $this->call(BreakReasonSeeder::class);
    }
}
