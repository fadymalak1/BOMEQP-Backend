<?php

namespace App\Console\Commands;

use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and suspend ACC accounts with expired subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');

        // Find ACCs with expired subscriptions
        $expiredSubscriptions = ACCSubscription::where('subscription_end_date', '<', now())
            ->where('payment_status', 'paid')
            ->with('acc')
            ->get();

        $suspendedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $acc = $subscription->acc;
            
            if (!$acc) {
                continue;
            }

            // Check if ACC has an active subscription
            $activeSubscription = ACCSubscription::where('acc_id', $acc->id)
                ->where('subscription_end_date', '>=', now())
                ->where('payment_status', 'paid')
                ->exists();

            // If no active subscription and ACC is not already suspended, suspend it
            if (!$activeSubscription && $acc->status !== 'suspended') {
                DB::transaction(function () use ($acc) {
                    $acc->update(['status' => 'suspended']);
                    
                    // Also suspend the user account
                    $user = User::where('email', $acc->email)->first();
                    if ($user && $user->role === 'acc_admin') {
                        $user->update(['status' => 'suspended']);
                    }
                });

                $suspendedCount++;
                $this->info("Suspended ACC: {$acc->name} (ID: {$acc->id})");
            }
        }

        $this->info("Completed. Suspended {$suspendedCount} ACC account(s).");
        
        return Command::SUCCESS;
    }
}

