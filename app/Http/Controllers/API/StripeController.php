<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StripeSetting;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;

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
            'payer_id' => 'required|numeric',
            'payee_type' => 'required|string',
            'payee_id' => 'required|numeric',
            'description' => 'nullable|string',
            'reference_id' => 'nullable|numeric',
            'reference_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Convert numeric strings to integers for IDs
        $payerId = (int)$request->payer_id;
        $payeeId = (int)$request->payee_id;

        $metadata = [
            'transaction_type' => $request->transaction_type,
            'payer_type' => $request->payer_type,
            'payer_id' => (string)$payerId,
            'payee_type' => $request->payee_type,
            'payee_id' => (string)$payeeId,
        ];

        if ($request->reference_id) {
            $metadata['reference_id'] = (string)((int)$request->reference_id);
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
            'payer_id' => $payerId,
            'payee_type' => $request->payee_type,
            'payee_id' => $payeeId,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_method' => 'credit_card',
            'payment_gateway_transaction_id' => $result['payment_intent_id'],
            'status' => 'pending',
            'description' => $request->description,
            'reference_id' => $request->reference_id ? (int)$request->reference_id : null,
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

        try {
            $event = json_decode($payload, true);

            if (!isset($event['type'])) {
                Log::warning('Stripe webhook received without event type');
                return response()->json(['error' => 'Invalid event data'], 400);
            }

            Log::info('Stripe webhook received', [
                'event_type' => $event['type'],
                'event_id' => $event['id'] ?? null,
            ]);

            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event['data']['object']);
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentCanceled($event['data']['object']);
                    break;

                case 'charge.refunded':
                    $this->handleRefund($event['data']['object']);
                    break;

                case 'charge.dispute.created':
                    $this->handleDisputeCreated($event['data']['object']);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['event_type' => $event['type']]);
            }

            return response()->json(['received' => true]);
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSucceeded(array $paymentIntent)
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            // Only update if status is not already completed (idempotency)
            if ($transaction->status !== 'completed') {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                Log::info('Transaction updated to completed via webhook', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntent['id'],
                    'transaction_type' => $transaction->transaction_type,
                ]);
            } else {
                Log::info('Transaction already completed, skipping update', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntent['id'],
                ]);
            }
        } else {
            Log::warning('Payment succeeded webhook received but transaction not found', [
                'payment_intent_id' => $paymentIntent['id'],
                'amount' => $paymentIntent['amount'] ?? null,
                'currency' => $paymentIntent['currency'] ?? null,
            ]);
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailed(array $paymentIntent)
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            // Only update if status is not already failed
            if ($transaction->status !== 'failed') {
                $transaction->update([
                    'status' => 'failed',
                ]);

                Log::info('Transaction updated to failed via webhook', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntent['id'],
                    'error_message' => $paymentIntent['last_payment_error']['message'] ?? null,
                ]);
            }
        } else {
            Log::warning('Payment failed webhook received but transaction not found', [
                'payment_intent_id' => $paymentIntent['id'],
            ]);
        }
    }

    /**
     * Handle payment canceled
     */
    protected function handlePaymentCanceled(array $paymentIntent)
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'failed',
            ]);

            Log::info('Transaction updated to failed (canceled)', [
                'transaction_id' => $transaction->id,
                'payment_intent_id' => $paymentIntent['id'],
            ]);
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

                Log::info('Transaction updated to refunded', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntentId,
                    'refund_amount' => $charge['amount_refunded'] ?? null,
                ]);
            }
        }
    }

    /**
     * Handle dispute created
     */
    protected function handleDisputeCreated(array $dispute)
    {
        $chargeId = $dispute['charge'] ?? null;
        
        if ($chargeId) {
            // Try to find transaction by charge ID or payment intent
            $transaction = Transaction::where('payment_gateway_transaction_id', $chargeId)->first();
            
            // If not found, try to get payment intent from charge
            if (!$transaction) {
                try {
                    $charge = Charge::retrieve($chargeId);
                    $paymentIntentId = $charge->payment_intent ?? null;
                    if ($paymentIntentId) {
                        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntentId)->first();
                    }
                } catch (\Exception $e) {
                    Log::error('Error retrieving charge for dispute', ['error' => $e->getMessage()]);
                }
            }

            if ($transaction) {
                Log::warning('Dispute created for transaction', [
                    'transaction_id' => $transaction->id,
                    'dispute_id' => $dispute['id'],
                    'dispute_reason' => $dispute['reason'] ?? null,
                    'dispute_amount' => $dispute['amount'] ?? null,
                ]);
                
                // Optionally update transaction status or create dispute record
                // Transaction status remains unchanged, but you may want to track disputes
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

