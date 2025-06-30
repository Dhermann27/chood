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
        $yards = [
            ['id' => 999, 'name' => 'Floater', 'display_order' => 99, 'is_active' => 1],
            ['name' => 'Small', 'display_order' => 2, 'is_active' => 1],
            ['name' => 'Large', 'display_order' => 4, 'is_active' => 1],
            ['name' => 'Relaxed Large', 'display_order' => 5],
            ['name' => 'Medium', 'display_order' => 3],
            ['name' => 'Event', 'display_order' => 6],
        ];

        collect($yards)->each(fn($yard) => Yard::create($yard));

    }

}
