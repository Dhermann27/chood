<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CabinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cabinValues = [
            ['id' => 2000, 'cabinName' => 'Luxury Suite 1', 'row' => 1, 'column' => 7, 'rowspan' => 2],
            ['id' => 2001, 'cabinName' => 'Luxury Suite 2', 'row' => 1, 'column' => 10, 'rowspan' => 2],
            ['id' => 2002, 'cabinName' => 'Luxury Suite 3', 'row' => 1, 'column' => 13, 'rowspan' => 2],
            ['id' => 3000, 'cabinName' => 'Teacup Condo 1', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3001, 'cabinName' => 'Teacup Condo 2', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3002, 'cabinName' => 'Teacup Condo 3', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3003, 'cabinName' => 'Teacup Condo 4', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3004, 'cabinName' => 'Teacup Condo 5', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3005, 'cabinName' => 'Teacup Condo 6', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3006, 'cabinName' => 'Teacup Condo 7', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 3007, 'cabinName' => 'Teacup Condo 8', 'row' => 0, 'column' => 0, 'rowspan' => 1],
            ['id' => 1000, 'cabinName' => '4x4 - Cabin 1', 'row' => 9, 'column' => 1, 'rowspan' => 1],
            ['id' => 1001, 'cabinName' => '4x4 - Cabin 2', 'row' => 10, 'column' => 1, 'rowspan' => 1],
            ['id' => 1002, 'cabinName' => '4x4 - Cabin 3', 'row' => 9, 'column' => 3, 'rowspan' => 1],
            ['id' => 1003, 'cabinName' => '4x4 - Cabin 4', 'row' => 10, 'column' => 3, 'rowspan' => 1],
            ['id' => 1004, 'cabinName' => '4x8 - Cabin 5', 'row' => 9, 'column' => 4, 'rowspan' => 2],
            ['id' => 1005, 'cabinName' => '4x4 - Cabin 6', 'row' => 9, 'column' => 6, 'rowspan' => 1],
            ['id' => 1006, 'cabinName' => '4x4 - Cabin 7', 'row' => 10, 'column' => 6, 'rowspan' => 1],
            ['id' => 1007, 'cabinName' => '4x4 - Cabin 8', 'row' => 9, 'column' => 7, 'rowspan' => 1],
            ['id' => 1008, 'cabinName' => '4x4 - Cabin 9', 'row' => 10, 'column' => 7, 'rowspan' => 1],
            ['id' => 1009, 'cabinName' => '4x8 - Cabin 10', 'row' => 9, 'column' => 9, 'rowspan' => 2],
            ['id' => 1010, 'cabinName' => '4x8 - Cabin 11', 'row' => 9, 'column' => 10, 'rowspan' => 2],
            ['id' => 1011, 'cabinName' => '4x6 - Cabin 12', 'row' => 6, 'column' => 12, 'rowspan' => 1],
            ['id' => 1012, 'cabinName' => '4x6 - Cabin 13', 'row' => 7, 'column' => 12, 'rowspan' => 1],
            ['id' => 1013, 'cabinName' => '4x6 - Cabin 14', 'row' => 8, 'column' => 12, 'rowspan' => 1],
            ['id' => 1014, 'cabinName' => '6x8 - Cabin 15', 'row' => 9, 'column' => 12, 'rowspan' => 2],
            ['id' => 1015, 'cabinName' => '4x6 - Cabin 16', 'row' => 6, 'column' => 13, 'rowspan' => 1],
            ['id' => 1016, 'cabinName' => '4x6 - Cabin 17', 'row' => 7, 'column' => 13, 'rowspan' => 1],
            ['id' => 1017, 'cabinName' => '4x6 - Cabin 18', 'row' => 8, 'column' => 13, 'rowspan' => 1],
            ['id' => 1018, 'cabinName' => '6x8 - Cabin 19', 'row' => 9, 'column' => 13, 'rowspan' => 2],
            ['id' => 1019, 'cabinName' => '4x6 - Cabin 20', 'row' => 1, 'column' => 15, 'rowspan' => 1],
            ['id' => 1020, 'cabinName' => '4x6 - Cabin 21', 'row' => 2, 'column' => 15, 'rowspan' => 1],
            ['id' => 1021, 'cabinName' => '4x6 - Cabin 22', 'row' => 3, 'column' => 15, 'rowspan' => 1],
            ['id' => 1022, 'cabinName' => '4x6 - Cabin 23', 'row' => 4, 'column' => 15, 'rowspan' => 1],
            ['id' => 1023, 'cabinName' => '4x6 - Cabin 24', 'row' => 6, 'column' => 15, 'rowspan' => 1],
            ['id' => 1024, 'cabinName' => '4x6 - Cabin 25', 'row' => 7, 'column' => 15, 'rowspan' => 1],
            ['id' => 1025, 'cabinName' => '4x6 - Cabin 26', 'row' => 8, 'column' => 15, 'rowspan' => 1],
            ['id' => 1026, 'cabinName' => '4x6 - Cabin 27', 'row' => 9, 'column' => 15, 'rowspan' => 1],
            ['id' => 1027, 'cabinName' => '4x6 - Cabin 28', 'row' => 10, 'column' => 15, 'rowspan' => 1],
            ['id' => 1028, 'cabinName' => '4x6 - Cabin 29', 'row' => 1, 'column' => 16, 'rowspan' => 1],
            ['id' => 1029, 'cabinName' => '4x6 - Cabin 30', 'row' => 2, 'column' => 16, 'rowspan' => 1],
            ['id' => 1030, 'cabinName' => '4x6 - Cabin 31', 'row' => 3, 'column' => 16, 'rowspan' => 1],
            ['id' => 1031, 'cabinName' => '4x6 - Cabin 32', 'row' => 4, 'column' => 16, 'rowspan' => 1],
            ['id' => 1032, 'cabinName' => '4x6 - Cabin 33', 'row' => 6, 'column' => 16, 'rowspan' => 1],
            ['id' => 1033, 'cabinName' => '4x6 - Cabin 34', 'row' => 7, 'column' => 16, 'rowspan' => 1],
            ['id' => 1034, 'cabinName' => '4x6 - Cabin 35', 'row' => 8, 'column' => 16, 'rowspan' => 1],
            ['id' => 1035, 'cabinName' => '4x6 - Cabin 36', 'row' => 9, 'column' => 16, 'rowspan' => 1],
            ['id' => 1036, 'cabinName' => '4x6 - Cabin 37', 'row' => 10, 'column' => 16, 'rowspan' => 1],
            ['id' => 1037, 'cabinName' => '4x6 - Cabin 38', 'row' => 1, 'column' => 18, 'rowspan' => 1],
            ['id' => 1038, 'cabinName' => '4x6 - Cabin 39', 'row' => 2, 'column' => 18, 'rowspan' => 1],
            ['id' => 1039, 'cabinName' => '4x6 - Cabin 40', 'row' => 3, 'column' => 18, 'rowspan' => 1],
            ['id' => 1040, 'cabinName' => '4x6 - Cabin 41', 'row' => 4, 'column' => 18, 'rowspan' => 1],
            ['id' => 1041, 'cabinName' => '4x6 - Cabin 42', 'row' => 6, 'column' => 18, 'rowspan' => 1],
            ['id' => 1042, 'cabinName' => '4x6 - Cabin 43', 'row' => 7, 'column' => 18, 'rowspan' => 1],
            ['id' => 1043, 'cabinName' => '4x6 - Cabin 44', 'row' => 8, 'column' => 18, 'rowspan' => 1],
            ['id' => 1044, 'cabinName' => '4x6 - Cabin 45', 'row' => 9, 'column' => 18, 'rowspan' => 1],
            ['id' => 1045, 'cabinName' => '4x6 - Cabin 46', 'row' => 10, 'column' => 18, 'rowspan' => 1],
            ['id' => 1046, 'cabinName' => '4x6 - Cabin 47', 'row' => 1, 'column' => 19, 'rowspan' => 1],
            ['id' => 1047, 'cabinName' => '4x6 - Cabin 48', 'row' => 2, 'column' => 19, 'rowspan' => 1],
            ['id' => 1048, 'cabinName' => '4x6 - Cabin 49', 'row' => 3, 'column' => 19, 'rowspan' => 1],
            ['id' => 1049, 'cabinName' => '4x6 - Cabin 50', 'row' => 4, 'column' => 19, 'rowspan' => 1],
            ['id' => 1050, 'cabinName' => '4x6 - Cabin 51', 'row' => 6, 'column' => 19, 'rowspan' => 1],
            ['id' => 1051, 'cabinName' => '4x6 - Cabin 52', 'row' => 7, 'column' => 19, 'rowspan' => 1],
            ['id' => 1052, 'cabinName' => '4x6 - Cabin 53', 'row' => 8, 'column' => 19, 'rowspan' => 1],
            ['id' => 1053, 'cabinName' => '6x8 - Cabin 54', 'row' => 9, 'column' => 19, 'rowspan' => 2],
            ['id' => 1054, 'cabinName' => '4x6 - Cabin 55', 'row' => 1, 'column' => 21, 'rowspan' => 1],
            ['id' => 1055, 'cabinName' => '4x6 - Cabin 56', 'row' => 2, 'column' => 21, 'rowspan' => 1],
            ['id' => 1056, 'cabinName' => '4x6 - Cabin 57', 'row' => 3, 'column' => 21, 'rowspan' => 1],
            ['id' => 1057, 'cabinName' => '4x6 - Cabin 58', 'row' => 4, 'column' => 21, 'rowspan' => 1],
            ['id' => 1058, 'cabinName' => '4x6 - Cabin 59', 'row' => 6, 'column' => 21, 'rowspan' => 1],
            ['id' => 1059, 'cabinName' => '4x6 - Cabin 60', 'row' => 7, 'column' => 21, 'rowspan' => 1],
            ['id' => 1060, 'cabinName' => '4x6 - Cabin 61', 'row' => 8, 'column' => 21, 'rowspan' => 1],
            ['id' => 1061, 'cabinName' => '4x6 - Cabin 62', 'row' => 9, 'column' => 21, 'rowspan' => 1],
            ['id' => 1062, 'cabinName' => '4x6 - Cabin 63', 'row' => 10, 'column' => 21, 'rowspan' => 1],
            ['id' => 1063, 'cabinName' => '4x6 - Cabin 64', 'row' => 1, 'column' => 22, 'rowspan' => 1],
            ['id' => 1064, 'cabinName' => '4x6 - Cabin 65', 'row' => 2, 'column' => 22, 'rowspan' => 1],
            ['id' => 1065, 'cabinName' => '4x6 - Cabin 66', 'row' => 3, 'column' => 22, 'rowspan' => 1],
            ['id' => 1066, 'cabinName' => '4x6 - Cabin 67', 'row' => 4, 'column' => 22, 'rowspan' => 1],
            ['id' => 1067, 'cabinName' => '4x6 - Cabin 68', 'row' => 6, 'column' => 22, 'rowspan' => 1],
            ['id' => 1068, 'cabinName' => '4x6 - Cabin 69', 'row' => 7, 'column' => 22, 'rowspan' => 1],
            ['id' => 1069, 'cabinName' => '4x6 - Cabin 70', 'row' => 8, 'column' => 22, 'rowspan' => 1],
            ['id' => 1070, 'cabinName' => '4x6 - Cabin 71', 'row' => 9, 'column' => 22, 'rowspan' => 1],
            ['id' => 1071, 'cabinName' => '4x6 - Cabin 72', 'row' => 10, 'column' => 22, 'rowspan' => 1],
            ['id' => 1072, 'cabinName' => '4x6 - Cabin 73', 'row' => 1, 'column' => 24, 'rowspan' => 1],
            ['id' => 1073, 'cabinName' => '4x6 - Cabin 74', 'row' => 2, 'column' => 24, 'rowspan' => 1],
            ['id' => 1074, 'cabinName' => '4x6 - Cabin 75', 'row' => 3, 'column' => 24, 'rowspan' => 1],
            ['id' => 1075, 'cabinName' => '4x6 - Cabin 76', 'row' => 4, 'column' => 24, 'rowspan' => 1],
            ['id' => 1076, 'cabinName' => '4x6 - Cabin 77', 'row' => 6, 'column' => 24, 'rowspan' => 1],
            ['id' => 1077, 'cabinName' => '4x6 - Cabin 78', 'row' => 7, 'column' => 24, 'rowspan' => 1],
            ['id' => 1078, 'cabinName' => '4x6 - Cabin 79', 'row' => 8, 'column' => 24, 'rowspan' => 1],
            ['id' => 1079, 'cabinName' => '4x6 - Cabin 80', 'row' => 9, 'column' => 24, 'rowspan' => 1],
            ['id' => 1080, 'cabinName' => '4x6 - Cabin 81', 'row' => 10, 'column' => 24, 'rowspan' => 1],
            ['id' => 1081, 'cabinName' => '4x6 - Cabin 82', 'row' => 1, 'column' => 25, 'rowspan' => 1],
            ['id' => 1082, 'cabinName' => '4x6 - Cabin 83', 'row' => 2, 'column' => 25, 'rowspan' => 1],
            ['id' => 1083, 'cabinName' => '4x6 - Cabin 84', 'row' => 3, 'column' => 25, 'rowspan' => 1],
            ['id' => 1084, 'cabinName' => '4x6 - Cabin 85', 'row' => 4, 'column' => 25, 'rowspan' => 1],
            ['id' => 1085, 'cabinName' => '4x6 - Cabin 86', 'row' => 6, 'column' => 25, 'rowspan' => 1],
            ['id' => 1086, 'cabinName' => '4x6 - Cabin 87', 'row' => 7, 'column' => 25, 'rowspan' => 1],
            ['id' => 1087, 'cabinName' => '4x6 - Cabin 88', 'row' => 8, 'column' => 25, 'rowspan' => 1],
            ['id' => 1088, 'cabinName' => '4x6 - Cabin 89', 'row' => 9, 'column' => 25, 'rowspan' => 1],
            ['id' => 1089, 'cabinName' => '4x6 - Cabin 90', 'row' => 10, 'column' => 25, 'rowspan' => 1],
            ['id' => 1090, 'cabinName' => '4x6 - Cabin 91', 'row' => 1, 'column' => 27, 'rowspan' => 1],
            ['id' => 1091, 'cabinName' => '4x6 - Cabin 92', 'row' => 2, 'column' => 27, 'rowspan' => 1],
            ['id' => 1092, 'cabinName' => '4x6 - Cabin 93', 'row' => 3, 'column' => 27, 'rowspan' => 1],
            ['id' => 1093, 'cabinName' => '4x6 - Cabin 94', 'row' => 4, 'column' => 27, 'rowspan' => 1],
            ['id' => 1094, 'cabinName' => '4x6 - Cabin 95', 'row' => 6, 'column' => 27, 'rowspan' => 1],
            ['id' => 1095, 'cabinName' => '4x6 - Cabin 96', 'row' => 7, 'column' => 27, 'rowspan' => 1],
            ['id' => 1096, 'cabinName' => '4x6 - Cabin 97', 'row' => 8, 'column' => 27, 'rowspan' => 1],
            ['id' => 1097, 'cabinName' => '4x6 - Cabin 98', 'row' => 9, 'column' => 27, 'rowspan' => 1],
            ['id' => 1098, 'cabinName' => '4x6 - Cabin 99', 'row' => 10, 'column' => 27, 'rowspan' => 1]

        ];

        DB::table('cabins')->insert($cabinValues);
    }
}
