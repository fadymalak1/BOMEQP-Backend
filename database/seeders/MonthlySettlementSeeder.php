<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\MonthlySettlement;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class MonthlySettlementSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();

        foreach ($accs as $acc) {
            for ($i = 1; $i <= 6; $i++) {
                $month = now()->subMonths($i)->format('Y-m');
                $totalRevenue = rand(10000, 50000);
                $groupCommission = $totalRevenue * 0.10;

                $transaction = Transaction::create([
                    'transaction_type' => 'settlement',
                    'payer_type' => 'acc',
                    'payer_id' => $acc->id,
                    'payee_type' => 'group',
                    'payee_id' => 1,
                    'amount' => $groupCommission,
                    'currency' => 'USD',
                    'payment_method' => 'bank_transfer',
                    'payment_gateway_transaction_id' => 'stripe_' . strtolower(uniqid()),
                    'status' => ['pending', 'completed'][rand(0, 1)],
                    'description' => 'Monthly Settlement for ' . $month,
                    'reference_id' => null,
                    'reference_type' => 'monthly_settlement',
                    'completed_at' => rand(0, 1) ? now()->subMonths($i)->addDays(5) : null,
                ]);

                MonthlySettlement::create([
                    'settlement_month' => $month,
                    'acc_id' => $acc->id,
                    'total_revenue' => $totalRevenue,
                    'group_commission_amount' => $groupCommission,
                    'status' => ['pending', 'paid'][rand(0, 1)],
                    'request_date' => now()->subMonths($i)->addDays(1),
                    'payment_date' => rand(0, 1) ? now()->subMonths($i)->addDays(5) : null,
                    'payment_method' => 'bank_transfer',
                    'transaction_id' => $transaction->id,
                ]);
            }
        }
    }
}

