<?php

namespace Database\Seeders;

use App\Models\Timeslot;
use Illuminate\Database\Seeder;

class TimeslotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['name' => 'AM',    'display_order' => 0],
            ['name' => 'Lunch', 'display_order' => 1],
            ['name' => 'PM',    'display_order' => 2],
            ['name' => 'PRN',   'display_order' => 3],
        ];

        collect($slots)->each(fn($slot) => Timeslot::create($slot));
    }
}
