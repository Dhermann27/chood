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
            ['homebase_id' => 'G3PE2FS7F2VH8V5V', 'first_name' => 'Abigail', 'last_name' => 'Bertich', 'birthday' => '1992-12-01'],
            ['homebase_id' => 'G32FR13YK9VNCC1T', 'first_name' => 'Avery', 'last_name' => 'Hermann', 'birthday' => '2008-08-09'],
            ['homebase_id' => 'G333NSK736JJ5YSA', 'first_name' => 'Brittany', 'last_name' => 'Huxen', 'birthday' => '1987-03-10'],
            ['homebase_id' => 'G3J588QADSFMSXN6', 'first_name' => 'Cheyenne', 'last_name' => 'Martin', 'birthday' => '2003-04-27'],
            ['homebase_id' => 'G37A2G8YDAR5FXWC', 'first_name' => 'Chris', 'last_name' => 'Gibbs', 'birthday' => '1968-03-01'],
            ['homebase_id' => 'G3GQMCA21H0PGC4F', 'first_name' => 'Clare', 'last_name' => 'Geraghty', 'birthday' => '1996-10-22'],
            ['homebase_id' => 'G3EAWYV1421H1032', 'first_name' => 'Dan', 'last_name' => 'Hermann', 'birthday' => '1978-08-27'],
            ['homebase_id' => 'G30KYQ91TH2PM791', 'first_name' => 'Evey', 'last_name' => 'Danzo', 'birthday' => '1987-10-02'],
            ['homebase_id' => 'G3T1TKBZJMJY1V5M', 'first_name' => 'Hannah', 'last_name' => 'Burke', 'birthday' => '2006-01-08'],
            ['homebase_id' => 'G37A2G8YDAR5GW6A', 'first_name' => 'Jacob', 'last_name' => 'Raisch', 'birthday' => '2001-05-11'],
            ['homebase_id' => 'G3J7FRJB0HGYZMPX', 'first_name' => 'Jacquelyn', 'last_name' => 'Scheffler', 'birthday' => '1981-10-11'],
            ['homebase_id' => 'G3640AKZ9GD450R6', 'first_name' => 'Jesse', 'last_name' => 'Ragsdale', 'birthday' => '1983-08-29'],
            ['homebase_id' => 'G3PE2FS7F2VHJTSD', 'first_name' => 'Jessie', 'last_name' => 'Huettenmeyer', 'birthday' => '2008-03-20'],
            ['homebase_id' => 'G3PE2FS7F2VH8VW8', 'first_name' => 'Julia', 'last_name' => 'Schwarz', 'birthday' => '2008-05-29'],
            ['homebase_id' => 'G37A2G8YDAR5DZME', 'first_name' => 'Karley', 'last_name' => 'Bruce', 'birthday' => '2007-10-14'],
            ['homebase_id' => 'G30F39G9878D6XYP', 'first_name' => 'Lilia', 'last_name' => 'Clegg', 'birthday' => '2005-12-09'],
            ['homebase_id' => 'G3YP5AB29QKXX1TK', 'first_name' => 'Lucianna', 'last_name' => 'Burke', 'birthday' => '2003-02-13'],
            ['homebase_id' => 'G33MT87YYP5VMDRB', 'first_name' => 'Natasha', 'last_name' => 'Kratohvil', 'birthday' => '1982-10-30'],
            ['homebase_id' => 'G3X3XVF6RSRTSTHZ', 'first_name' => 'Patricia', 'last_name' => 'Paul', 'birthday' => '1994-03-11'],
            ['homebase_id' => 'G3HYQGTV3X299HAA', 'first_name' => 'Sarah', 'last_name' => 'Kershman', 'birthday' => '1997-11-24'],
            ['homebase_id' => 'G37A2G8YDAR5T4EB', 'first_name' => 'Savannah', 'last_name' => 'Marshall', 'birthday' => '2006-12-03'],
            ['homebase_id' => 'G37A2G8YDAR5X8SF', 'first_name' => 'Timothy', 'last_name' => 'Arrandale', 'birthday' => '2002-12-13']
        ];

        DB::table('employees')->insert($employeeValues);

    }
}
