<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\CertificateCode;
use App\Models\CertificateTemplate;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\TrainingCenter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::all();
        $trainingCenters = TrainingCenter::all();

        foreach ($courses as $course) {
            $acc = $course->acc;
            $classes = ClassModel::where('course_id', $course->id)->get();
            $template = CertificateTemplate::where('acc_id', $acc->id)->first();
            $instructors = Instructor::where('training_center_id', $trainingCenters->random()->id)->get();
            $trainingCenter = $trainingCenters->random();
            $availableCodes = CertificateCode::where('course_id', $course->id)
                ->where('status', 'available')
                ->limit(5)
                ->get();

            foreach ($availableCodes as $index => $code) {
                if ($index >= 3) break; // Create max 3 certificates per course

                $class = $classes->random();
                $instructor = $instructors->random();

                Certificate::create([
                    'certificate_number' => 'CERT-' . strtoupper(uniqid()),
                    'course_id' => $course->id,
                    'class_id' => $class->id,
                    'training_center_id' => $trainingCenter->id,
                    'instructor_id' => $instructor->id,
                    'trainee_name' => 'Trainee ' . ($index + 1) . ' ' . Str::random(8),
                    'trainee_id_number' => 'ID-' . rand(100000, 999999),
                    'issue_date' => now()->subDays(rand(1, 30)),
                    'expiry_date' => now()->addYears(2),
                    'template_id' => $template ? $template->id : null,
                    'certificate_pdf_url' => 'https://example.com/certificates/' . uniqid() . '.pdf',
                    'verification_code' => strtoupper(uniqid('VERIFY-')),
                    'status' => 'valid',
                    'code_used_id' => $code->id,
                ]);

                // Mark code as used
                $code->update([
                    'status' => 'used',
                    'used_at' => now(),
                ]);
            }
        }
    }
}

