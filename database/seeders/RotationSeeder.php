<?php

namespace Database\Seeders;

use App\Models\Rotation;
use Illuminate\Database\Seeder;

class RotationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rotations = collect(range(8, 17))->map(function ($hour) {
            $start = sprintf('%02d:00:00', $hour);
            $end = sprintf('%02d:00:00', $hour + 1);
            return [
                'start_time' => $start,
                'end_time' => $end,
                'label' => date('ga', strtotime($start)) . '-' . date('ga', strtotime($end)),
                'is_sunday_hour' => ($hour >= 10 && $hour <= 14) ? 0 : 1,
                'is_super_handoff' => ($hour == 8 || $hour == 12 || $hour == 17) ? 1 : 0,
                'is_midday' => ($hour == 11 || $hour == 12) ? 1 : 0,
            ];
        });

        $rotations->each(fn($rotation) => Rotation::create($rotation));
    }

}
