<?php

namespace Database\Seeders;

use App\Models\ClassCompletion;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassCompletionSeeder extends Seeder
{
    public function run(): void
    {
        $trainingClasses = TrainingClass::where('status', 'completed')->get();
        $admin = User::where('role', 'group_admin')->first();

        foreach ($trainingClasses as $trainingClass) {
            ClassCompletion::create([
                'training_class_id' => $trainingClass->id,
                'completed_date' => $trainingClass->end_date,
                'completion_rate_percentage' => rand(80, 100),
                'certificates_generated_count' => rand(5, $trainingClass->enrolled_count),
                'marked_by' => $admin->id,
                'notes' => 'Class completed successfully. All participants met the requirements.',
            ]);
        }
    }
}

