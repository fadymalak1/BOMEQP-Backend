<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\CertificatePricing;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CertificatePricingSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();

        foreach ($accs as $acc) {
            $courses = Course::where('acc_id', $acc->id)->get();

            foreach ($courses as $course) {
                CertificatePricing::create([
                    'acc_id' => $acc->id,
                    'course_id' => $course->id,
                    'base_price' => rand(50, 500),
                    'currency' => 'USD',
                    'group_commission_percentage' => 10.00,
                    'training_center_commission_percentage' => 15.00,
                    'instructor_commission_percentage' => 5.00,
                    'effective_from' => now()->subMonths(6),
                    'effective_to' => now()->addMonths(6),
                ]);
            }
        }
    }
}

