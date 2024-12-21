<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $services = [
            ['id' => 1000, 'name' => 'Boarding: Cabin', 'code' => 'BRDC'],
            ['id' => 1001, 'name' => 'Boarding: Luxury', 'code' => 'BRDL'],
            ['id' => 2000, 'name' => 'Day Care: Full Day', 'code' => 'DCFD'],
            ['id' => 2001, 'name' => 'Day Care: Half Day', 'code' => 'DCHD'],
            ['id' => 2002, 'name' => 'Interview', 'code' => 'INTV'],
            ['id' => 3000, 'name' => 'Bath: Bath Package', 'code' => 'BXPK'],
            ['id' => 3001, 'name' => 'Bath: Blow Out and Brush Out', 'code' => 'BXBR'],
            ['id' => 3002, 'name' => 'Bath: Blueberry Facial', 'code' => 'BXBF'],
            ['id' => 3003, 'name' => 'Bath: Extra Large', 'code' => 'BBXL'],
            ['id' => 3004, 'name' => 'Bath: Extra Small', 'code' => 'BBXS'],
            ['id' => 3005, 'name' => 'Bath: Large', 'code' => 'BBLG'],
            ['id' => 3006, 'name' => 'Bath: Medium', 'code' => 'BBMD'],
            ['id' => 3007, 'name' => 'Bath: Small', 'code' => 'BBSM'],
            ['id' => 3008, 'name' => 'Bath: UltiMutt: Extra Large', 'code' => 'BXUM'],
            ['id' => 3009, 'name' => 'Bath: UltiMutt: Extra Small', 'code' => 'BTUM'],
            ['id' => 3010, 'name' => 'Bath: UltiMutt: Large', 'code' => 'BLUM'],
            ['id' => 3011, 'name' => 'Bath: UltiMutt: Medium', 'code' => 'BMUM'],
            ['id' => 3012, 'name' => 'Bath: UltiMutt: Small', 'code' => 'BSUM'],
            ['id' => 4000, 'name' => 'Deshed: Furminator', 'code' => 'DSBS'],
            ['id' => 4001, 'name' => 'Full Service: Groom: Complex 1-15 lbs', 'code' => 'FCOF'],
            ['id' => 4002, 'name' => 'Full Service: Groom: Complex 101-115 lbs', 'code' => 'FCOO'],
            ['id' => 4003, 'name' => 'Full Service: Groom: Complex 116+', 'code' => 'FCOS'],
            ['id' => 4004, 'name' => 'Full Service: Groom: Complex 16-25 lbs', 'code' => 'FCOT'],
            ['id' => 4005, 'name' => 'Full Service: Groom: Complex 26-40 lbs', 'code' => 'FCFS'],
            ['id' => 4006, 'name' => 'Full Service: Groom: Complex 41-55 lbs', 'code' => 'FCFF'],
            ['id' => 4007, 'name' => 'Full Service: Groom: Complex 56-70 lbs', 'code' => 'FCSS'],
            ['id' => 4008, 'name' => 'Full Service: Groom: Complex 71-85 lbs', 'code' => 'FCES'],
            ['id' => 4009, 'name' => 'Full Service: Groom: Complex 86-100 lbs', 'code' => 'FCSO'],
            ['id' => 4010, 'name' => 'Full Service: Groom: Long 1-15 lbs', 'code' => 'FLOF'],
            ['id' => 4011, 'name' => 'Full Service: Groom: Long 101-115 lbs', 'code' => 'FLFF'],
            ['id' => 4012, 'name' => 'Full Service: Groom: Long 116+ lbs', 'code' => 'FOOS'],
            ['id' => 4013, 'name' => 'Full Service: Groom: Long 16-25 lbs', 'code' => 'FLSF'],
            ['id' => 4014, 'name' => 'Full Service: Groom: Long 26-40 lbs', 'code' => 'FLTF'],
            ['id' => 4015, 'name' => 'Full Service: Groom: Long 41-55 lbs', 'code' => 'FLFF'],
            ['id' => 4016, 'name' => 'Full Service: Groom: Long 56-70 lbs', 'code' => 'FLSS'],
            ['id' => 4017, 'name' => 'Full Service: Groom: Long 71-85 lbs', 'code' => 'FESE'],
            ['id' => 4018, 'name' => 'Full Service: Groom: Long 86-100 lbs', 'code' => 'FLEO'],
            ['id' => 5000, 'name' => 'Individual Enrichment: Enrichment Package', 'code' => '1EPK'],
            ['id' => 5001, 'name' => 'Individual Enrichment: Play Pals', 'code' => '1EPP'],
            ['id' => 5002, 'name' => 'Individual Enrichment: Sniff & Seek', 'code' => '1ESS'],
            ['id' => 5003, 'name' => 'Individual Enrichment: Snuggle Time', 'code' => '1EST'],
            ['id' => 5004, 'name' => 'Individual Enrichment: Treat', 'code' => '1ETR'],
            ['id' => 3013, 'name' => 'Misc Groom: Anal Glands', 'code' => 'GANL'],
            ['id' => 3014, 'name' => 'Misc Groom: Ear Cleaning', 'code' => 'GEAR'],
            ['id' => 3015, 'name' => 'Misc Groom: Teeth Brushing', 'code' => 'GTTH'],
            ['id' => 3016, 'name' => 'Nail Trim: Basic', 'code' => 'NLTB'],
            ['id' => 3017, 'name' => 'Nail Trim: Upgrade: Grind/Dremel', 'code' => 'NLAG'],
            ['id' => 6000, 'name' => 'Train: Group Class: Puppy Class', 'code' => 'TGCP'],
            ['id' => 6001, 'name' => 'Train: Group Class: Tricks', 'code' => 'TGCT'],
            ['id' => 6002, 'name' => 'Train: Private Training: Camp', 'code' => 'T1CP']
        ];

        DB::table('services')->insert($services);
    }
}
