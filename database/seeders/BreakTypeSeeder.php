<?php

namespace Database\Seeders;

use App\Models\BreakType;
use Illuminate\Database\Seeder;

class BreakTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['label' => '15', 'short_label' => null, 'duration_minutes' => 15, 'behavior' => 'countdown', 'display_order' => 0],
            ['label' => '30', 'short_label' => null, 'duration_minutes' => 30, 'behavior' => 'countdown', 'display_order' => 1],
            ['label' => '45', 'short_label' => null, 'duration_minutes' => 45, 'behavior' => 'countdown', 'display_order' => 2],
            ['label' => '60', 'short_label' => null, 'duration_minutes' => 60, 'behavior' => 'countdown', 'display_order' => 3],
            ['label' => '120', 'short_label' => null, 'duration_minutes' => 120, 'behavior' => 'countdown', 'display_order' => 4],
            ['label' => 'Lunch', 'short_label' => null, 'duration_minutes' => null, 'behavior' => 'lunch', 'display_order' => 5],
            ['label' => 'Grooming', 'short_label' => 'GRM', 'duration_minutes' => null, 'behavior' => 'unlimited', 'display_order' => 6],
            ['label' => 'Training', 'short_label' => 'TRN', 'duration_minutes' => null, 'behavior' => 'unlimited', 'display_order' => 7],
            ['label' => 'EOD', 'short_label' => 'EOD', 'duration_minutes' => 120, 'behavior' => 'walks_only', 'display_order' => 8],
        ];

        collect($types)->each(fn($type) => BreakType::create($type));
    }
}
