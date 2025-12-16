<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\TrainingCenter;
use App\Models\User;
use Illuminate\Database\Seeder;

class InstructorAccAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = Instructor::all();
        $accs = ACC::where('status', 'active')->get();
        $admin = User::where('role', 'group_admin')->first();

        foreach ($instructors as $instructor) {
            $trainingCenter = TrainingCenter::find($instructor->training_center_id);
            
            foreach ($accs->random(rand(1, 2)) as $acc) {
                InstructorAccAuthorization::create([
                    'instructor_id' => $instructor->id,
                    'acc_id' => $acc->id,
                    'training_center_id' => $instructor->training_center_id,
                    'request_date' => now()->subDays(rand(30, 120)),
                    'status' => ['approved', 'pending'][rand(0, 1)],
                    'commission_percentage' => rand(5, 10),
                    'rejection_reason' => null,
                    'return_comment' => null,
                    'reviewed_by' => rand(0, 1) ? $admin->id : null,
                    'reviewed_at' => rand(0, 1) ? now()->subDays(rand(1, 20)) : null,
                    'documents_json' => [
                        'cv' => $instructor->cv_url,
                        'certificates' => $instructor->certificates_json,
                    ],
                ]);
            }
        }
    }
}

