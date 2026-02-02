<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\TrainingCenter;
use App\Models\TrainingClass;
use Illuminate\Database\Seeder;

class TrainingClassSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = TrainingCenter::all();

        foreach ($trainingCenters as $center) {
            $courses = Course::all()->random(5);
            $instructors = Instructor::where('training_center_id', $center->id)->get();

            foreach ($courses as $course) {
                $classes = ClassModel::where('course_id', $course->id)->get();
                
                if ($classes->isEmpty()) continue;

                $class = $classes->first();
                $instructor = $instructors->random();

                TrainingClass::create([
                    'training_center_id' => $center->id,
                    'course_id' => $course->id,
                    'class_id' => $class->id,
                    'instructor_id' => $instructor->id,
                    'start_date' => now()->addDays(rand(1, 30)),
                    'end_date' => now()->addDays(rand(31, 60)),
                    'schedule_json' => [
                        'days' => ['Monday', 'Wednesday', 'Friday'],
                        'time' => '09:00 - 17:00',
                        'duration' => '8 hours',
                    ],
                    'max_capacity' => rand(20, 50),
                    'enrolled_count' => rand(5, 25),
                    'status' => ['scheduled', 'in_progress', 'completed', 'cancelled'][rand(0, 3)],
                    'location' => ['physical', 'online'][rand(0, 1)],
                    'location_details' => $center->address,
                ]);
            }
        }
    }
}

