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
            ['homebase_id' => 'A12345', 'first_name' => 'Dan', 'last_name' => 'Hermann', 'birthday' => '1978-08-27'],
            ['homebase_id' => 'B23456', 'first_name' => 'Brittany', 'last_name' => 'Huxen', 'birthday' => '1987-03-10'],
            ['homebase_id' => 'C34567', 'first_name' => 'Evey', 'last_name' => 'Danzo', 'birthday' => '1987-10-02'],
        ];

        DB::table('employees')->insert($employeeValues);
    }
}
