<?php

namespace Database\Seeders;

use App\Models\Timeslot;
use Illuminate\Database\Seeder;

class TimeslotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = ['AM', 'Lunch', 'PM', 'As Needed'];

        foreach ($slots as $display_order => $name) {
            Timeslot::firstOrCreate(['name' => $name], ['display_order' => $display_order]);
        }
    }
}
