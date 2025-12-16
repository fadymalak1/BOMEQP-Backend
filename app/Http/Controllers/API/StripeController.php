<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StripeSetting;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Get Stripe configuration (publishable key)
     */
    public function getConfig()
    {
        $publishableKey = $this->stripeService->getPublishableKey();

        if (!$publishableKey) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'publishable_key' => $publishableKey,
            'is_configured' => $this->stripeService->isConfigured(),
        ]);
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'transaction_type' => 'required|string',
            'payer_type' => 'required|string',
            'payer_id' => 'required|integer',
            'payee_type' => 'required|string',
            'payee_id' => 'required|integer',
            'description' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'reference_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $metadata = [
            'transaction_type' => $request->transaction_type,
            'payer_type' => $request->payer_type,
            'payer_id' => $request->payer_id,
            'payee_type' => $request->payee_type,
            'payee_id' => $request->payee_id,
        ];

        if ($request->reference_id) {
            $metadata['reference_id'] = $request->reference_id;
            $metadata['reference_type'] = $request->reference_type;
        }

        $result = $this->stripeService->createPaymentIntent(
            $request->amount,
            $request->currency,
            $metadata
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        // Create pending transaction
        $transaction = Transaction::create([
            'transaction_type' => $request->transaction_type,
            'payer_type' => $request->payer_type,
            'payer_id' => $request->payer_id,
            'payee_type' => $request->payee_type,
            'payee_id' => $request->payee_id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_method' => 'credit_card',
            'payment_gateway_transaction_id' => $result['payment_intent_id'],
            'status' => 'pending',
            'description' => $request->description,
            'reference_id' => $request->reference_id,
            'reference_type' => $request->reference_type,
        ]);

        return response()->json([
            'success' => true,
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['payment_intent_id'],
            'transaction_id' => $transaction->id,
            'amount' => $result['amount'],
            'currency' => $result['currency'],
        ]);
    }

    /**
     * Confirm payment (webhook or manual confirmation)
     */
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'transaction_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->stripeService->confirmPaymentIntent($request->payment_intent_id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        // Update transaction if transaction_id is provided
        if ($request->transaction_id) {
            $transaction = Transaction::find($request->transaction_id);
            if ($transaction) {
                $transaction->update([
                    'status' => $result['status'] === 'succeeded' ? 'completed' : 'failed',
                    'completed_at' => $result['status'] === 'succeeded' ? now() : null,
                ]);
            }
        } else {
            // Try to find transaction by payment_intent_id
            $transaction = Transaction::where('payment_gateway_transaction_id', $request->payment_intent_id)->first();
            if ($transaction) {
                $transaction->update([
                    'status' => $result['status'] === 'succeeded' ? 'completed' : 'failed',
                    'completed_at' => $result['status'] === 'succeeded' ? now() : null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'status' => $result['status'],
            'transaction' => $transaction ?? null,
        ]);
    }

    /**
     * Handle Stripe webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$this->stripeService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Stripe webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        Log::info('Stripe webhook received', ['event_type' => $event['type']]);

        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;

            case 'charge.refunded':
                $this->handleRefund($event['data']['object']);
                break;
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSucceeded(array $paymentIntent)
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Transaction updated to completed', ['transaction_id' => $transaction->id]);
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailed(array $paymentIntent)
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'failed',
            ]);

            Log::info('Transaction updated to failed', ['transaction_id' => $transaction->id]);
        }
    }

    /**
     * Handle refund
     */
    protected function handleRefund(array $charge)
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;
        
        if ($paymentIntentId) {
            $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntentId)->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'refunded',
                ]);

                Log::info('Transaction updated to refunded', ['transaction_id' => $transaction->id]);
            }
        }
    }

    /**
     * Refund a payment
     */
    public function refund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->stripeService->refundPayment(
            $request->payment_intent_id,
            $request->amount
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        // Update transaction status
        $transaction = Transaction::where('payment_gateway_transaction_id', $request->payment_intent_id)->first();
        if ($transaction) {
            $transaction->update([
                'status' => 'refunded',
            ]);
        }

        return response()->json([
            'success' => true,
            'refund_id' => $result['refund_id'],
            'amount' => $result['amount'],
            'status' => $result['status'],
        ]);
    }
}

