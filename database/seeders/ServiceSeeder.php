<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $services = [
            ['id' => 1000, 'name' => 'Daycare Half Day', 'code' => 'DCH'],
            ['id' => 1001, 'name' => 'Daycare Full Day', 'code' => 'DCFD'],
            ['id' => 1002, 'name' => 'Daycare Pawsitive Start', 'code' => 'DCPJ'],
            ['id' => 1003, 'name' => 'Boarding Luxury', 'code' => 'BRDL'],
            ['id' => 1004, 'name' => 'Boarding Cabin', 'code' => 'BRDC'],
            ['id' => 1005, 'name' => 'Daycare Orientation', 'code' => 'INTV'],
            ['id' => 1006, 'name' => 'Enrichment Play Pals', 'code' => '1EPP'],
            ['id' => 1007, 'name' => 'Enrichment Sniff Seek', 'code' => '1ESS'],
            ['id' => 1008, 'name' => 'Enrichment Snuggle Time', 'code' => '1EST'],
            ['id' => 1009, 'name' => 'Grooming Bath', 'code' => 'Bath'],
            ['id' => 1010, 'name' => 'Grooming Brush', 'code' => 'Brush'],
            ['id' => 1011, 'name' => 'Grooming Nail Trim', 'code' => 'Trim']
        ];

        DB::table('services')->insert($services);
    }
}
