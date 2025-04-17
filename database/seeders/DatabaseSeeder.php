<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CabinSeeder::class);
//        $this->call(EmployeeSeeder::class);
//        $this->call(ServiceSeeder::class);
        $this->call(YardAssignmentSeeder::class);
    }
}
