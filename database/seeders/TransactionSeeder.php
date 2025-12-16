<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\Transaction;
use App\Models\TrainingCenter;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();
        $trainingCenters = TrainingCenter::all();

        // Subscription transactions
        foreach ($accs as $acc) {
            Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1, // Assuming group ID is 1
                'amount' => 10000.00,
                'currency' => 'USD',
                'payment_method' => 'credit_card',
                'payment_gateway_transaction_id' => 'stripe_' . strtolower(uniqid()),
                'status' => 'completed',
                'description' => 'ACC Subscription Payment',
                'reference_id' => $acc->id,
                'reference_type' => 'acc_subscription',
                'completed_at' => now()->subMonths(3),
            ]);
        }

        // Code purchase transactions
        foreach ($trainingCenters as $center) {
            for ($i = 0; $i < 3; $i++) {
                Transaction::create([
                    'transaction_type' => 'code_purchase',
                    'payer_type' => 'training_center',
                    'payer_id' => $center->id,
                    'payee_type' => 'acc',
                    'payee_id' => $accs->random()->id,
                    'amount' => rand(500, 5000),
                    'currency' => 'USD',
                    'payment_method' => ['credit_card', 'wallet'][rand(0, 1)],
                    'payment_gateway_transaction_id' => 'stripe_' . strtolower(uniqid()),
                    'status' => 'completed',
                    'description' => 'Certificate Code Purchase',
                    'reference_id' => null,
                    'reference_type' => 'code_batch',
                    'completed_at' => now()->subDays(rand(1, 90)),
                ]);
            }
        }
    }
}

