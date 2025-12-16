<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\ACCMaterial;
use App\Models\CodeBatch;
use App\Models\TrainingCenter;
use App\Models\TrainingCenterPurchase;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TrainingCenterPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = TrainingCenter::all();
        $accs = ACC::where('status', 'active')->get();

        foreach ($trainingCenters as $center) {
            // Material purchases
            foreach ($accs as $acc) {
                $materials = ACCMaterial::where('acc_id', $acc->id)->limit(3)->get();

                foreach ($materials as $material) {
                    $transaction = Transaction::create([
                        'transaction_type' => 'material_purchase',
                        'payer_type' => 'training_center',
                        'payer_id' => $center->id,
                        'payee_type' => 'acc',
                        'payee_id' => $acc->id,
                        'amount' => $material->price,
                        'currency' => 'USD',
                        'payment_method' => ['credit_card', 'wallet'][rand(0, 1)],
                        'payment_gateway_transaction_id' => 'stripe_' . strtolower(uniqid()),
                        'status' => 'completed',
                        'description' => 'Material Purchase: ' . $material->name,
                        'reference_id' => $material->id,
                        'reference_type' => 'acc_material',
                        'completed_at' => now()->subDays(rand(1, 60)),
                    ]);

                    TrainingCenterPurchase::create([
                        'training_center_id' => $center->id,
                        'acc_id' => $acc->id,
                        'purchase_type' => 'material',
                        'item_id' => $material->id,
                        'amount' => $material->price,
                        'group_commission_percentage' => 10.00,
                        'group_commission_amount' => $material->price * 0.10,
                        'transaction_id' => $transaction->id,
                        'purchased_at' => $transaction->completed_at,
                    ]);
                }
            }

            // Code batch purchases
            $batches = CodeBatch::where('training_center_id', $center->id)->get();
            foreach ($batches as $batch) {
                $transaction = Transaction::where('reference_type', 'code_batch')
                    ->where('payer_id', $center->id)
                    ->first();

                if ($transaction) {
                    TrainingCenterPurchase::create([
                        'training_center_id' => $center->id,
                        'acc_id' => $batch->acc_id,
                        'purchase_type' => 'code_batch',
                        'item_id' => $batch->id,
                        'amount' => $batch->total_amount,
                        'group_commission_percentage' => 10.00,
                        'group_commission_amount' => $batch->total_amount * 0.10,
                        'transaction_id' => $transaction->id,
                        'purchased_at' => $batch->purchase_date,
                    ]);
                }
            }
        }
    }
}

