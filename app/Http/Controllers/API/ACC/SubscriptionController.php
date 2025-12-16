<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
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
            'payment_intent_id' => 'nullable|string', // if using Stripe
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
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
                'payment_gateway_transaction_id' => $request->payment_intent_id ?? $request->payment_gateway_transaction_id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Create or update subscription
            $subscription = ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => now(),
                'subscription_end_date' => now()->addMonth(),
                'renewal_date' => now()->addMonth(),
                'amount' => $request->amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
            ]);

            DB::commit();

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
            'auto_renew' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $currentSubscription = $acc->subscriptions()->latest()->first();

        if (!$currentSubscription) {
            return response()->json(['message' => 'No active subscription found'], 404);
        }

        // Update auto_renew if provided
        if ($request->has('auto_renew')) {
            $currentSubscription->update(['auto_renew' => $request->auto_renew]);
        }

        // TODO: Implement renewal logic with payment if auto_renew is true

        return response()->json([
            'message' => 'Subscription renewed successfully',
            'subscription' => $currentSubscription->fresh(),
        ]);
    }
}

