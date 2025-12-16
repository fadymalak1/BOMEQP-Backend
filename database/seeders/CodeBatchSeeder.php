<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\CodeBatch;
use App\Models\TrainingCenter;
use Illuminate\Database\Seeder;

class CodeBatchSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = TrainingCenter::all();
        $accs = ACC::where('status', 'active')->get();

        foreach ($trainingCenters as $center) {
            foreach ($accs->random(rand(1, 2)) as $acc) {
                CodeBatch::create([
                    'training_center_id' => $center->id,
                    'acc_id' => $acc->id,
                    'quantity' => rand(10, 100),
                    'total_amount' => rand(500, 5000),
                    'payment_method' => ['credit_card', 'wallet'][rand(0, 1)],
                    'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                    'purchase_date' => now()->subDays(rand(1, 90)),
                ]);
            }
        }
    }
}

