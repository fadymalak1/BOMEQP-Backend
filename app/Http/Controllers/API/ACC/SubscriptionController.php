<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create payment intent for subscription payment
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
        }

        try {
            $result = $this->stripeService->createPaymentIntent(
                $request->amount,
                'USD',
                [
                    'acc_id' => (string)$acc->id,
                    'user_id' => (string)$user->id,
                    'type' => 'subscription',
                    'amount' => (string)$request->amount,
                ]
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment intent for subscription renewal
     */
    public function createRenewalPaymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $currentSubscription = $acc->subscriptions()->latest()->first();
        if (!$currentSubscription) {
            return response()->json(['message' => 'No subscription found. Please create a new subscription.'], 404);
        }

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
        }

        try {
            $result = $this->stripeService->createPaymentIntent(
                $request->amount,
                'USD',
                [
                    'acc_id' => (string)$acc->id,
                    'user_id' => (string)$user->id,
                    'type' => 'subscription_renewal',
                    'amount' => (string)$request->amount,
                    'subscription_id' => (string)$currentSubscription->id,
                ]
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subscription = $acc->subscriptions()->latest()->first();

        return response()->json(['subscription' => $subscription]);
    }

    public function payment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:credit_card,wallet',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Verify Stripe payment intent if credit card payment
        if ($request->payment_method === 'credit_card') {
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $request->amount,
                    [
                        'acc_id' => (string)$acc->id,
                        'type' => 'subscription',
                    ]
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1, // Group ID
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
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
                'amount' => $request->amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
            ]);

            // Also activate the user account associated with this ACC
            $user = \App\Models\User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin' && $user->status !== 'active') {
                $user->update(['status' => 'active']);
            }

            DB::commit();

            // Send notifications
            $notificationService = new NotificationService();
            $notificationService->notifySubscriptionPaid($user->id, $subscription->id, $request->amount);
            
            // Notify Admin
            $notificationService->notifyAdminSubscriptionPaid($acc->id, $acc->name, $request->amount, false);

            return response()->json([
                'message' => 'Payment successful',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Payment failed: ' . $e->getMessage()], 500);
        }
    }

    public function renew(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:credit_card,wallet',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string',
            'auto_renew' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $currentSubscription = $acc->subscriptions()->latest()->first();

        if (!$currentSubscription) {
            return response()->json(['message' => 'No subscription found. Please create a new subscription.'], 404);
        }

        // Check if subscription is expired
        if ($currentSubscription->subscription_end_date < now() && $acc->status === 'suspended') {
            return response()->json([
                'message' => 'Subscription expired. Account is suspended. Please renew to reactivate.',
                'requires_payment' => true
            ], 400);
        }

        // Verify Stripe payment intent if credit card payment
        if ($request->payment_method === 'credit_card') {
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $request->amount,
                    [
                        'acc_id' => (string)$acc->id,
                        'type' => 'subscription_renewal',
                        'subscription_id' => (string)$currentSubscription->id,
                    ]
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create transaction for renewal
            $transaction = Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1,
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Calculate new dates
            $startDate = $currentSubscription->subscription_end_date > now() 
                ? $currentSubscription->subscription_end_date 
                : now();
            $endDate = $startDate->copy()->addYear();

            // Create new subscription record
            $newSubscription = ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => $startDate,
                'subscription_end_date' => $endDate,
                'renewal_date' => $endDate,
                'amount' => $request->amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
                'auto_renew' => $request->auto_renew ?? false,
            ]);

            // Reactivate ACC if suspended
            if ($acc->status === 'suspended') {
                $acc->update(['status' => 'active']);
                
                // Also activate the user account
                $userModel = \App\Models\User::where('email', $acc->email)->first();
                if ($userModel && $userModel->role === 'acc_admin') {
                    $userModel->update(['status' => 'active']);
                }
            }

            DB::commit();

            // Send notifications
            $userModel = \App\Models\User::where('email', $acc->email)->first();
            if ($userModel) {
                $notificationService = new NotificationService();
                $notificationService->notifySubscriptionPaid($userModel->id, $newSubscription->id, $request->amount);
                
                // Notify Admin
                $notificationService->notifyAdminSubscriptionPaid($acc->id, $acc->name, $request->amount, true);
            }

            return response()->json([
                'message' => 'Subscription renewed successfully',
                'subscription' => $newSubscription,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Renewal failed: ' . $e->getMessage()], 500);
        }
    }
}

