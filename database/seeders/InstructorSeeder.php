<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\TrainingCenter;
use Illuminate\Database\Seeder;

class InstructorSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = TrainingCenter::all();

        foreach ($trainingCenters as $center) {
            $instructors = [
                [
                    'training_center_id' => $center->id,
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'john.smith@' . strtolower(str_replace(' ', '', $center->name)) . '.com',
                    'phone' => '+1-555-' . rand(1000, 9999),
                    'id_number' => 'ID-' . rand(100000, 999999),
                    'cv_url' => 'https://example.com/cvs/john-smith.pdf',
                    'certificates_json' => [
                        ['name' => 'Safety Certification', 'issued_by' => 'Safety Board', 'year' => 2020],
                        ['name' => 'Advanced Training', 'issued_by' => 'Training Institute', 'year' => 2022],
                    ],
                    'specializations' => ['Safety', 'First Aid', 'Emergency Response'],
                    'status' => 'active',
                ],
                [
                    'training_center_id' => $center->id,
                    'first_name' => 'Sarah',
                    'last_name' => 'Johnson',
                    'email' => 'sarah.johnson@' . strtolower(str_replace(' ', '', $center->name)) . '.com',
                    'phone' => '+1-555-' . rand(1000, 9999),
                    'id_number' => 'ID-' . rand(100000, 999999),
                    'cv_url' => 'https://example.com/cvs/sarah-johnson.pdf',
                    'certificates_json' => [
                        ['name' => 'Technical Training', 'issued_by' => 'Tech Academy', 'year' => 2019],
                        ['name' => 'Quality Management', 'issued_by' => 'Quality Board', 'year' => 2021],
                    ],
                    'specializations' => ['Technical Skills', 'Quality Assurance'],
                    'status' => 'active',
                ],
            ];

            foreach ($instructors as $instructor) {
                Instructor::create($instructor);
            }
        }
    }
}

