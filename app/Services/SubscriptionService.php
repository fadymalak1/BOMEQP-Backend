<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected StripeService $stripeService;
    protected NotificationService $notificationService;

    public function __construct(StripeService $stripeService, NotificationService $notificationService)
    {
        $this->stripeService = $stripeService;
        $this->notificationService = $notificationService;
    }

    /**
     * Process subscription payment
     *
     * @param ACC $acc
     * @param float $amount
     * @param string $paymentMethod
     * @param string|null $paymentIntentId
     * @return array
     */
    public function processPayment(ACC $acc, float $amount, string $paymentMethod, ?string $paymentIntentId = null): array
    {
        // Verify Stripe payment intent if credit card payment
        if ($paymentMethod === 'credit_card') {
            if (!$paymentIntentId) {
                return [
                    'success' => false,
                    'message' => 'payment_intent_id is required for credit card payments',
                    'code' => 400
                ];
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $paymentIntentId,
                    $amount,
                    [
                        'acc_id' => (string)$acc->id,
                        'type' => 'subscription',
                    ]
                );
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage(),
                    'code' => 400
                ];
            }
        }

        try {
            DB::beginTransaction();

            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1, // Group ID
                'amount' => $amount,
                'currency' => 'USD',
                'payment_method' => $paymentMethod,
                'payment_gateway_transaction_id' => $paymentIntentId,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Check if ACC is suspended due to expired subscription
            if ($acc->status === 'suspended') {
                // Reactivate ACC account
                $acc->update(['status' => 'active']);
            }

            // Create or update subscription
            $subscription = ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => now(),
                'subscription_end_date' => now()->addYear(), // Default 1 year subscription
                'renewal_date' => now()->addYear(),
                'amount' => $amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'transaction_id' => $transaction->id,
            ]);

            // Also activate the user account associated with this ACC
            $user = User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin' && $user->status !== 'active') {
                $user->update(['status' => 'active']);
            }

            DB::commit();

            // Send notifications
            if ($user) {
                $this->notificationService->notifySubscriptionPaid($user->id, $subscription->id, $amount);
            }
            $this->notificationService->notifyAdminSubscriptionPaid($acc->id, $acc->name, $amount, false);

            return [
                'success' => true,
                'subscription' => $subscription,
                'transaction' => $transaction,
                'message' => 'Payment successful'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process subscription payment', [
                'acc_id' => $acc->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Renew subscription
     *
     * @param ACC $acc
     * @param ACCSubscription $currentSubscription
     * @param float $amount
     * @param string $paymentMethod
     * @param string|null $paymentIntentId
     * @param bool|null $autoRenew
     * @return array
     */
    public function renewSubscription(
        ACC $acc,
        ACCSubscription $currentSubscription,
        float $amount,
        string $paymentMethod,
        ?string $paymentIntentId = null,
        ?bool $autoRenew = null
    ): array {
        // Check if subscription is expired
        if ($currentSubscription->subscription_end_date < now() && $acc->status === 'suspended') {
            return [
                'success' => false,
                'message' => 'Subscription expired. Account is suspended. Please renew to reactivate.',
                'requires_payment' => true,
                'code' => 400
            ];
        }

        // Verify Stripe payment intent if credit card payment
        if ($paymentMethod === 'credit_card') {
            if (!$paymentIntentId) {
                return [
                    'success' => false,
                    'message' => 'payment_intent_id is required for credit card payments',
                    'code' => 400
                ];
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $paymentIntentId,
                    $amount,
                    [
                        'acc_id' => (string)$acc->id,
                        'type' => 'subscription_renewal',
                        'subscription_id' => (string)$currentSubscription->id,
                    ]
                );
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage(),
                    'code' => 400
                ];
            }
        }

        try {
            DB::beginTransaction();

            // Create transaction for renewal
            $transaction = Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1,
                'amount' => $amount,
                'currency' => 'USD',
                'payment_method' => $paymentMethod,
                'payment_gateway_transaction_id' => $paymentIntentId,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Calculate new dates
            $startDate = $currentSubscription->subscription_end_date > now() 
                ? $currentSubscription->subscription_end_date 
                : now();
            
            $endDate = $startDate->copy()->addYear();
            $renewalDate = $endDate->copy();

            // Update current subscription or create new one
            $subscription = ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => $startDate,
                'subscription_end_date' => $endDate,
                'renewal_date' => $renewalDate,
                'amount' => $amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'transaction_id' => $transaction->id,
                'auto_renew' => $autoRenew ?? $currentSubscription->auto_renew ?? false,
            ]);

            // Update ACC status if suspended
            if ($acc->status === 'suspended') {
                $acc->update(['status' => 'active']);
            }

            // Activate user account if needed
            $user = User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin' && $user->status !== 'active') {
                $user->update(['status' => 'active']);
            }

            DB::commit();

            // Send notifications
            if ($user) {
                $this->notificationService->notifySubscriptionPaid($user->id, $subscription->id, $amount);
            }
            $this->notificationService->notifyAdminSubscriptionPaid($acc->id, $acc->name, $amount, true);

            return [
                'success' => true,
                'subscription' => $subscription,
                'transaction' => $transaction,
                'message' => 'Subscription renewed successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to renew subscription', [
                'acc_id' => $acc->id,
                'subscription_id' => $currentSubscription->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

