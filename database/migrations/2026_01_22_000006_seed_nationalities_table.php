<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $nationalities = [
            ['name' => 'Egyptian', 'code' => 'EGY', 'sort_order' => 1],
            ['name' => 'Saudi Arabian', 'code' => 'SAU', 'sort_order' => 2],
            ['name' => 'Emirati', 'code' => 'ARE', 'sort_order' => 3],
            ['name' => 'Kuwaiti', 'code' => 'KWT', 'sort_order' => 4],
            ['name' => 'Qatari', 'code' => 'QAT', 'sort_order' => 5],
            ['name' => 'Bahraini', 'code' => 'BHR', 'sort_order' => 6],
            ['name' => 'Omani', 'code' => 'OMN', 'sort_order' => 7],
            ['name' => 'Jordanian', 'code' => 'JOR', 'sort_order' => 8],
            ['name' => 'Lebanese', 'code' => 'LBN', 'sort_order' => 9],
            ['name' => 'Syrian', 'code' => 'SYR', 'sort_order' => 10],
            ['name' => 'Iraqi', 'code' => 'IRQ', 'sort_order' => 11],
            ['name' => 'Yemeni', 'code' => 'YEM', 'sort_order' => 12],
            ['name' => 'Palestinian', 'code' => 'PSE', 'sort_order' => 13],
            ['name' => 'Sudanese', 'code' => 'SDN', 'sort_order' => 14],
            ['name' => 'Libyan', 'code' => 'LBY', 'sort_order' => 15],
            ['name' => 'Tunisian', 'code' => 'TUN', 'sort_order' => 16],
            ['name' => 'Algerian', 'code' => 'DZA', 'sort_order' => 17],
            ['name' => 'Moroccan', 'code' => 'MAR', 'sort_order' => 18],
            ['name' => 'Mauritanian', 'code' => 'MRT', 'sort_order' => 19],
            ['name' => 'Somali', 'code' => 'SOM', 'sort_order' => 20],
            ['name' => 'Djiboutian', 'code' => 'DJI', 'sort_order' => 21],
            ['name' => 'Comorian', 'code' => 'COM', 'sort_order' => 22],
            ['name' => 'American', 'code' => 'USA', 'sort_order' => 23],
            ['name' => 'British', 'code' => 'GBR', 'sort_order' => 24],
            ['name' => 'Canadian', 'code' => 'CAN', 'sort_order' => 25],
            ['name' => 'Australian', 'code' => 'AUS', 'sort_order' => 26],
            ['name' => 'French', 'code' => 'FRA', 'sort_order' => 27],
            ['name' => 'German', 'code' => 'DEU', 'sort_order' => 28],
            ['name' => 'Italian', 'code' => 'ITA', 'sort_order' => 29],
            ['name' => 'Spanish', 'code' => 'ESP', 'sort_order' => 30],
            ['name' => 'Turkish', 'code' => 'TUR', 'sort_order' => 31],
            ['name' => 'Iranian', 'code' => 'IRN', 'sort_order' => 32],
            ['name' => 'Pakistani', 'code' => 'PAK', 'sort_order' => 33],
            ['name' => 'Indian', 'code' => 'IND', 'sort_order' => 34],
            ['name' => 'Bangladeshi', 'code' => 'BGD', 'sort_order' => 35],
            ['name' => 'Sri Lankan', 'code' => 'LKA', 'sort_order' => 36],
            ['name' => 'Nepalese', 'code' => 'NPL', 'sort_order' => 37],
            ['name' => 'Afghan', 'code' => 'AFG', 'sort_order' => 38],
            ['name' => 'Chinese', 'code' => 'CHN', 'sort_order' => 39],
            ['name' => 'Japanese', 'code' => 'JPN', 'sort_order' => 40],
            ['name' => 'South Korean', 'code' => 'KOR', 'sort_order' => 41],
            ['name' => 'Filipino', 'code' => 'PHL', 'sort_order' => 42],
            ['name' => 'Indonesian', 'code' => 'IDN', 'sort_order' => 43],
            ['name' => 'Malaysian', 'code' => 'MYS', 'sort_order' => 44],
            ['name' => 'Singaporean', 'code' => 'SGP', 'sort_order' => 45],
            ['name' => 'Thai', 'code' => 'THA', 'sort_order' => 46],
            ['name' => 'Vietnamese', 'code' => 'VNM', 'sort_order' => 47],
            ['name' => 'Nigerian', 'code' => 'NGA', 'sort_order' => 48],
            ['name' => 'South African', 'code' => 'ZAF', 'sort_order' => 49],
            ['name' => 'Kenyan', 'code' => 'KEN', 'sort_order' => 50],
            ['name' => 'Ethiopian', 'code' => 'ETH', 'sort_order' => 51],
            ['name' => 'Ghanaian', 'code' => 'GHA', 'sort_order' => 52],
            ['name' => 'Brazilian', 'code' => 'BRA', 'sort_order' => 53],
            ['name' => 'Mexican', 'code' => 'MEX', 'sort_order' => 54],
            ['name' => 'Argentinian', 'code' => 'ARG', 'sort_order' => 55],
            ['name' => 'Chilean', 'code' => 'CHL', 'sort_order' => 56],
            ['name' => 'Colombian', 'code' => 'COL', 'sort_order' => 57],
            ['name' => 'Peruvian', 'code' => 'PER', 'sort_order' => 58],
            ['name' => 'Venezuelan', 'code' => 'VEN', 'sort_order' => 59],
            ['name' => 'Russian', 'code' => 'RUS', 'sort_order' => 60],
            ['name' => 'Ukrainian', 'code' => 'UKR', 'sort_order' => 61],
            ['name' => 'Polish', 'code' => 'POL', 'sort_order' => 62],
            ['name' => 'Romanian', 'code' => 'ROU', 'sort_order' => 63],
            ['name' => 'Greek', 'code' => 'GRC', 'sort_order' => 64],
            ['name' => 'Portuguese', 'code' => 'PRT', 'sort_order' => 65],
            ['name' => 'Dutch', 'code' => 'NLD', 'sort_order' => 66],
            ['name' => 'Belgian', 'code' => 'BEL', 'sort_order' => 67],
            ['name' => 'Swiss', 'code' => 'CHE', 'sort_order' => 68],
            ['name' => 'Austrian', 'code' => 'AUT', 'sort_order' => 69],
            ['name' => 'Swedish', 'code' => 'SWE', 'sort_order' => 70],
            ['name' => 'Norwegian', 'code' => 'NOR', 'sort_order' => 71],
            ['name' => 'Danish', 'code' => 'DNK', 'sort_order' => 72],
            ['name' => 'Finnish', 'code' => 'FIN', 'sort_order' => 73],
            ['name' => 'Other', 'code' => null, 'sort_order' => 999],
        ];

        foreach ($nationalities as $nationality) {
            DB::table('nationalities')->insert([
                'name' => $nationality['name'],
                'code' => $nationality['code'],
                'sort_order' => $nationality['sort_order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nationalities')->truncate();
    }
};

