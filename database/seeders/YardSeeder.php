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
            ['id' => 1002, 'name' => 'Active', 'is_large' => true, 'display_order' => 1],
            ['id' => 1001, 'name' => 'Large', 'is_large' => true, 'display_order' => 2],
            ['id' => 1003, 'name' => 'Medium', 'is_large' => false, 'display_order' => 3],
            ['id' => 1000, 'name' => 'Small', 'is_large' => false, 'display_order' => 4],
            ['id' => 999, 'name' => 'Floater', 'is_large' => false, 'display_order' => 5],
        ];

        collect($yards)->each(fn($yard) => Yard::create($yard));

    }

}
