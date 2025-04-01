<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class YardAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startTime = Carbon::createFromTime(config('services.yardAssignments.startHourOfDay'), 0);

        for ($i = 1; $i <= config('services.yardAssignments.numberOfYards'); $i++) {
            for ($j = 0; $j < config('services.yardAssignments.numberOfHours'); $j++) {
                DB::table('yard_assignments')->insert([
                    'yard_number' => $i,
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $startTime->addHour()->subSecond()->format('H:i:s'),
                    'created_at' => Carbon::now()
                ]);
                $startTime->addSecond();
            }

            // Reset for the next yard
            $startTime = Carbon::createFromTime(config('services.yardAssignments.startHourOfDay'), 0);

        }

        for ($j = 0; $j < config('services.yardAssignments.numberOfHours'); $j++) {
            DB::table('yard_assignments')->insert([
                'yard_number' => 99,
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $startTime->addHour()->subMinute()->format('H:i:s'),
                'created_at' => Carbon::now()
            ]);
            $startTime->addMinute();
        }
    }
}
