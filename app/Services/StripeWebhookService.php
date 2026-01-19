<?php

namespace App\Services;

use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeWebhookService
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Handle payment intent succeeded event
     *
     * @param array $paymentIntent
     * @return void
     */
    public function handlePaymentSucceeded(array $paymentIntent): void
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            // Only update if status is not already completed (idempotency)
            if ($transaction->status !== 'completed') {
                DB::beginTransaction();
                try {
                    $transaction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                    DB::commit();

                    Log::info('Transaction updated to completed via webhook', [
                        'transaction_id' => $transaction->id,
                        'payment_intent_id' => $paymentIntent['id'],
                        'transaction_type' => $transaction->transaction_type,
                    ]);

                    // تنفيذ التحويل التلقائي بعد نجاح الدفعة
                    $this->triggerAutomaticTransfer($transaction);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to update transaction to completed', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::info('Transaction already completed, skipping update', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntent['id'],
                ]);

                // حتى لو كانت المعاملة مكتملة مسبقاً، نتأكد من وجود transfer
                // (في حالة فشل التحويل الأول)
                $this->triggerAutomaticTransfer($transaction);
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
     * Trigger automatic transfer for completed transaction
     *
     * @param Transaction $transaction
     * @return void
     */
    protected function triggerAutomaticTransfer(Transaction $transaction): void
    {
        try {
            // التحقق من عدم وجود transfer مكتمل لهذه المعاملة
            $existingTransfer = \App\Models\Transfer::where('transaction_id', $transaction->id)
                ->where('status', 'completed')
                ->first();

            if ($existingTransfer) {
                Log::info('Transfer already completed for this transaction', [
                    'transaction_id' => $transaction->id,
                    'transfer_id' => $existingTransfer->id,
                ]);
                return;
            }

            // تنفيذ التحويل التلقائي
            $result = $this->transferService->handleAutomaticTransfer($transaction);

            if ($result['success']) {
                Log::info('Automatic transfer triggered successfully', [
                    'transaction_id' => $transaction->id,
                    'transfer_id' => $result['transfer']->id ?? null,
                ]);
            } else {
                Log::warning('Automatic transfer failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception in triggerAutomaticTransfer', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle payment intent failed event
     *
     * @param array $paymentIntent
     * @return void
     */
    public function handlePaymentFailed(array $paymentIntent): void
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            // Only update if status is not already failed
            if ($transaction->status !== 'failed') {
                DB::beginTransaction();
                try {
                    $transaction->update([
                        'status' => 'failed',
                    ]);

                    DB::commit();

                    Log::info('Transaction updated to failed via webhook', [
                        'transaction_id' => $transaction->id,
                        'payment_intent_id' => $paymentIntent['id'],
                        'error_message' => $paymentIntent['last_payment_error']['message'] ?? null,
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to update transaction to failed', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            Log::warning('Payment failed webhook received but transaction not found', [
                'payment_intent_id' => $paymentIntent['id'],
            ]);
        }
    }

    /**
     * Handle payment intent canceled event
     *
     * @param array $paymentIntent
     * @return void
     */
    public function handlePaymentCanceled(array $paymentIntent): void
    {
        $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();

        if ($transaction) {
            DB::beginTransaction();
            try {
                $transaction->update([
                    'status' => 'failed',
                ]);

                DB::commit();

                Log::info('Transaction updated to failed (canceled)', [
                    'transaction_id' => $transaction->id,
                    'payment_intent_id' => $paymentIntent['id'],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to update transaction to failed (canceled)', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle refund event
     *
     * @param array $charge
     * @return void
     */
    public function handleRefund(array $charge): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;
        
        if ($paymentIntentId) {
            $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntentId)->first();

            if ($transaction) {
                DB::beginTransaction();
                try {
                    $transaction->update([
                        'status' => 'refunded',
                    ]);

                    DB::commit();

                    Log::info('Transaction updated to refunded via webhook', [
                        'transaction_id' => $transaction->id,
                        'payment_intent_id' => $paymentIntentId,
                        'refund_amount' => $charge['amount_refunded'] ?? null,
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to update transaction to refunded', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::warning('Refund webhook received but transaction not found', [
                    'payment_intent_id' => $paymentIntentId,
                ]);
            }
        }
    }

    /**
     * Handle dispute created event
     *
     * @param array $dispute
     * @return void
     */
    public function handleDisputeCreated(array $dispute): void
    {
        $chargeId = $dispute['charge'] ?? null;
        
        if ($chargeId) {
            // Get payment intent from charge
            try {
                $charge = \Stripe\Charge::retrieve($chargeId);
                $paymentIntentId = $charge->payment_intent ?? null;

                if ($paymentIntentId) {
                    $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntentId)->first();

                    if ($transaction) {
                        Log::warning('Dispute created for transaction', [
                            'transaction_id' => $transaction->id,
                            'payment_intent_id' => $paymentIntentId,
                            'dispute_id' => $dispute['id'] ?? null,
                            'dispute_reason' => $dispute['reason'] ?? null,
                            'dispute_amount' => $dispute['amount'] ?? null,
                        ]);

                        // You might want to update transaction status or create a dispute record
                        // For now, we'll just log it
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to process dispute webhook', [
                    'charge_id' => $chargeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

