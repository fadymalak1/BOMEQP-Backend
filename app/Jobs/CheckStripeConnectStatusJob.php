<?php

namespace App\Jobs;

use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\Instructor;
use App\Services\StripeConnectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\Exception\ApiErrorException;

class CheckStripeConnectStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(StripeConnectService $stripeConnectService): void
    {
        try {
            // Check ACCs
            $accs = ACC::whereNotNull('stripe_account_id')
                ->where('stripe_connect_status', '!=', 'inactive')
                ->get();

            foreach ($accs as $acc) {
                $this->checkAccountStatus($acc, 'acc', $stripeConnectService);
            }

            // Check Training Centers
            $trainingCenters = TrainingCenter::whereNotNull('stripe_account_id')
                ->where('stripe_connect_status', '!=', 'inactive')
                ->get();

            foreach ($trainingCenters as $tc) {
                $this->checkAccountStatus($tc, 'training_center', $stripeConnectService);
            }

            // Check Instructors
            $instructors = Instructor::whereNotNull('stripe_account_id')
                ->where('stripe_connect_status', '!=', 'inactive')
                ->get();

            foreach ($instructors as $instructor) {
                $this->checkAccountStatus($instructor, 'instructor', $stripeConnectService);
            }

            Log::info('Stripe Connect status check completed', [
                'checked_accounts' => $accs->count() + $trainingCenters->count() + $instructors->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in CheckStripeConnectStatusJob', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check status for a single account
     */
    protected function checkAccountStatus($account, string $accountType, StripeConnectService $service): void
    {
        try {
            $result = $service->getStripeConnectStatus($accountType, $account->id);

            if ($result['success']) {
                Log::debug('Stripe Connect status checked', [
                    'account_type' => $accountType,
                    'account_id' => $account->id,
                    'status' => $result['data']['status'] ?? null,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to check account status', [
                'account_type' => $accountType,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

