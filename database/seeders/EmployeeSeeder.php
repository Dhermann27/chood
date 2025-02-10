<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employeeValues = [
            ['first_name' => 'Dan', 'last_name' => 'Hermann', 'birthday' => '1978-08-27'],
            ['first_name' => 'Brittany', 'last_name' => 'Huxen', 'birthday' => '1987-03-10'],
            ['first_name' => 'Sarah', 'last_name' => 'Kershman', 'birthday' => '1997-11-24'],
        ];

        DB::table('employees')->insert($employeeValues);
    }
}
