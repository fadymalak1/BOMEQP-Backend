<?php

namespace Database\Seeders;

use App\Models\CommissionLedger;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class CommissionLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = Transaction::where('transaction_type', 'code_purchase')->get();

        foreach ($transactions as $transaction) {
            $amount = $transaction->amount;
            $groupCommission = $amount * 0.10; // 10%
            $accCommission = $amount * 0.15; // 15%

            CommissionLedger::create([
                'transaction_id' => $transaction->id,
                'acc_id' => $transaction->payee_id,
                'training_center_id' => $transaction->payer_id,
                'instructor_id' => null,
                'group_commission_amount' => $groupCommission,
                'group_commission_percentage' => 10.00,
                'acc_commission_amount' => $accCommission,
                'acc_commission_percentage' => 15.00,
                'settlement_status' => ['pending', 'paid'][rand(0, 1)],
                'settlement_date' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
            ]);
        }
    }
}

