<?php

namespace Database\Seeders;

use App\Models\Rotation;
use Illuminate\Database\Seeder;

class RotationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        foreach (range(8, 17) as $hour) {
            $start = sprintf('%02d:00:00', $hour);
            $end = sprintf('%02d:00:00', $hour + 1);

            Rotation::create([
                'start_time' => $start,
                'end_time' => $end,
                'label' => date('ga', strtotime($start)) . '-' . date('ga', strtotime($end)),
            ]);
        }
    }

}
