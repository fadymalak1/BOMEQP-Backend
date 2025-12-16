<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainingCenterAccAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = TrainingCenter::all();
        $accs = ACC::where('status', 'active')->get();
        $admin = User::where('role', 'group_admin')->first();

        foreach ($trainingCenters as $center) {
            foreach ($accs->random(rand(1, 3)) as $acc) {
                TrainingCenterAccAuthorization::create([
                    'training_center_id' => $center->id,
                    'acc_id' => $acc->id,
                    'request_date' => now()->subDays(rand(30, 180)),
                    'status' => ['approved', 'pending', 'rejected'][rand(0, 2)],
                    'group_commission_percentage' => rand(5, 15),
                    'rejection_reason' => null,
                    'return_comment' => null,
                    'reviewed_by' => rand(0, 1) ? $admin->id : null,
                    'reviewed_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
                    'documents_json' => [
                        'license' => 'https://example.com/documents/license.pdf',
                        'certificate' => 'https://example.com/documents/certificate.pdf',
                    ],
                ]);
            }
        }
    }
}

