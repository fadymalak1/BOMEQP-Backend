<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\Course;
use App\Models\DiscountCode;
use Illuminate\Database\Seeder;

class DiscountCodeSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();

        foreach ($accs as $acc) {
            $courses = Course::where('acc_id', $acc->id)->pluck('id')->toArray();

            $discountCodes = [
                [
                    'acc_id' => $acc->id,
                    'code' => 'SAVE10-' . $acc->id,
                    'discount_type' => 'time_limited',
                    'discount_percentage' => 10.00,
                    'applicable_course_ids' => array_slice($courses, 0, 3),
                    'start_date' => now()->subDays(30),
                    'end_date' => now()->addDays(60),
                    'total_quantity' => 100,
                    'used_quantity' => rand(10, 50),
                    'status' => 'active',
                ],
                [
                    'acc_id' => $acc->id,
                    'code' => 'WELCOME20-' . $acc->id,
                    'discount_type' => 'time_limited',
                    'discount_percentage' => 20.00,
                    'applicable_course_ids' => $courses,
                    'start_date' => now()->subDays(60),
                    'end_date' => now()->addDays(30),
                    'total_quantity' => 50,
                    'used_quantity' => rand(5, 30),
                    'status' => 'active',
                ],
            ];

            foreach ($discountCodes as $code) {
                DiscountCode::create($code);
            }
        }
    }
}

