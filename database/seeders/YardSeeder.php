<?php

namespace Database\Seeders;

use App\Models\Yard;
use Illuminate\Database\Seeder;

class YardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Yard::create(['id' => 999, 'name' => 'Floaters', 'display_order' => 99]);

        $yardsCount = config('services.dd.yards_to_open');
        $yardNames = [
            'Small' => 2,
            ($yardsCount == 2 ? 'Large' : 'Active Large') => 4,
            'Relaxed Large' => 5,
            'Medium' => 3,
        ];

        collect(array_slice($yardNames, 0, $yardsCount))
            ->each(function ($displayOrder, $name) {
                Yard::create(['name' => $name, 'display_order' => $displayOrder]);
            });

    }

}
