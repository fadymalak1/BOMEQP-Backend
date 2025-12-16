<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\CertificateCode;
use App\Models\CodeBatch;
use App\Models\Course;
use App\Models\DiscountCode;
use App\Models\TrainingCenter;
use Illuminate\Database\Seeder;

class CertificateCodeSeeder extends Seeder
{
    public function run(): void
    {
        $batches = CodeBatch::all();

        foreach ($batches as $batch) {
            $courses = Course::where('acc_id', $batch->acc_id)->get();
            $discountCodes = DiscountCode::where('acc_id', $batch->acc_id)->get();

            for ($i = 0; $i < $batch->quantity; $i++) {
                $course = $courses->random();
                $pricing = $course->certificatePricing()->first();
                $basePrice = $pricing ? $pricing->base_price : rand(50, 500);
                
                $discountCode = $discountCodes->random();
                $discountApplied = rand(0, 1);
                $finalPrice = $discountApplied ? $basePrice * (1 - $discountCode->discount_percentage / 100) : $basePrice;

                CertificateCode::create([
                    'code' => strtoupper(uniqid('CERT-')),
                    'batch_id' => $batch->id,
                    'training_center_id' => $batch->training_center_id,
                    'acc_id' => $batch->acc_id,
                    'course_id' => $course->id,
                    'purchased_price' => $finalPrice,
                    'discount_applied' => $discountApplied,
                    'discount_code_id' => $discountApplied ? $discountCode->id : null,
                    'status' => ['available', 'used'][rand(0, 1)],
                    'used_at' => null,
                    'used_for_certificate_id' => null,
                    'purchased_at' => $batch->purchase_date,
                ]);
            }
        }
    }
}

