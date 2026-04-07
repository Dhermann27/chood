<?php

namespace Database\Seeders;

use App\Models\Timeslot;
use Illuminate\Database\Seeder;

class TimeslotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['name' => 'AM',    'gingr_label' => 'AM',        'display_order' => 0],
            ['name' => 'Lunch', 'gingr_label' => 'Lunch',     'display_order' => 1],
            ['name' => 'PM',    'gingr_label' => 'PM',        'display_order' => 2],
            ['name' => 'PRN',   'gingr_label' => 'As Needed', 'display_order' => 3],
        ];

        collect($slots)->each(fn($slot) => Timeslot::create($slot));
    }
}
