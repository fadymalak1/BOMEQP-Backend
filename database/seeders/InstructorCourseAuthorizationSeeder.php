<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\InstructorCourseAuthorization;
use App\Models\User;
use Illuminate\Database\Seeder;

class InstructorCourseAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = Instructor::all();
        $admin = User::where('role', 'group_admin')->first();

        foreach ($instructors as $instructor) {
            $authorizations = InstructorAccAuthorization::where('instructor_id', $instructor->id)
                ->where('status', 'approved')
                ->get();

            foreach ($authorizations as $auth) {
                $acc = ACC::find($auth->acc_id);
                $courses = Course::where('acc_id', $acc->id)->limit(3)->get();

                foreach ($courses as $course) {
                    InstructorCourseAuthorization::create([
                        'instructor_id' => $instructor->id,
                        'course_id' => $course->id,
                        'acc_id' => $acc->id,
                        'authorized_at' => now()->subDays(rand(1, 60)),
                        'authorized_by' => $admin->id,
                        'status' => 'active',
                    ]);
                }
            }
        }
    }
}

