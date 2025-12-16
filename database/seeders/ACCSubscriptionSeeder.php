<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\ACCSubscription;
use Illuminate\Database\Seeder;

class ACCSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();

        foreach ($accs as $acc) {
            // Current subscription
            ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => now()->subMonths(3),
                'subscription_end_date' => now()->addMonths(9),
                'renewal_date' => now()->addMonths(9),
                'amount' => 10000.00,
                'payment_status' => 'paid',
                'payment_date' => now()->subMonths(3),
                'payment_method' => 'credit_card',
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'auto_renew' => true,
            ]);

            // Past subscription
            ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => now()->subMonths(15),
                'subscription_end_date' => now()->subMonths(3),
                'renewal_date' => now()->subMonths(3),
                'amount' => 10000.00,
                'payment_status' => 'paid',
                'payment_date' => now()->subMonths(15),
                'payment_method' => 'credit_card',
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'auto_renew' => false,
            ]);
        }
    }
}

