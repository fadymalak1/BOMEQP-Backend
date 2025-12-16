<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassModelSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::all();
        $admin = User::where('role', 'group_admin')->first();

        foreach ($courses as $course) {
            for ($i = 1; $i <= 3; $i++) {
                ClassModel::create([
                    'course_id' => $course->id,
                    'name' => $course->name . ' - Class ' . $i,
                    'created_by' => $admin->id,
                    'status' => 'active',
                ]);
            }
        }
    }
}

