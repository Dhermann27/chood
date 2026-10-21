<?php

namespace Database\Seeders;

use App\Models\BreakType;
use Illuminate\Database\Seeder;

class BreakTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['label' => '15', 'short_label' => null, 'duration_minutes' => 15, 'behavior' => 'countdown', 'is_reported' => true, 'display_order' => 0],
            ['label' => '30', 'short_label' => null, 'duration_minutes' => 30, 'behavior' => 'countdown', 'is_reported' => true, 'display_order' => 1],
            ['label' => '60', 'short_label' => null, 'duration_minutes' => 60, 'behavior' => 'countdown', 'is_reported' => true, 'display_order' => 2],
            ['label' => 'Timer', 'short_label' => null, 'duration_minutes' => null, 'behavior' => 'countdown', 'is_reported' => true, 'display_order' => 3],
            ['label' => 'Lunch', 'short_label' => null, 'duration_minutes' => null, 'behavior' => 'lunch', 'is_reported' => false, 'display_order' => 4],
            ['label' => 'Groom', 'short_label' => 'GRM', 'duration_minutes' => null, 'behavior' => 'unlimited', 'is_reported' => false, 'display_order' => 5],
            ['label' => 'Train', 'short_label' => 'TRN', 'duration_minutes' => 120, 'behavior' => 'walks_only', 'is_reported' => false, 'display_order' => 6],
            ['label' => 'Enrichment', 'short_label' => 'ENR', 'duration_minutes' => 30, 'behavior' => 'countdown', 'is_reported' => false, 'display_order' => 7],
            ['label' => 'EOD', 'short_label' => 'EOD', 'duration_minutes' => 120, 'behavior' => 'walks_only', 'is_reported' => true, 'display_order' => 8],
        ];

        collect($types)->each(fn($type) => BreakType::create($type));
    }
}
