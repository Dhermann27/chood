<?php

namespace Database\Seeders;

use App\Models\BreakReason;
use Illuminate\Database\Seeder;

class BreakReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['label' => 'Overstim', 'icon' => 'bolt', 'display_order' => 1],
            ['label' => 'Altercation', 'icon' => 'burst', 'display_order' => 2],
            ['label' => 'Facebarking', 'icon' => 'bullhorn', 'display_order' => 3],
            ['label' => 'Mounting', 'icon' => 'leaf-maple', 'display_order' => 4],
            ['label' => '3rd Wheeling', 'icon' => 'user-group', 'display_order' => 5],
            ['label' => 'Resource Guarding', 'icon' => 'shield', 'display_order' => 6],
            ['label' => 'Medical', 'icon' => 'kit-medical', 'display_order' => 7],
            ['label' => 'Naptime', 'icon' => 'bed', 'display_order' => 8],
        ];

        collect($reasons)->each(fn($r) => BreakReason::create($r));
    }
}
