<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\Transaction;
use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\Instructor;
use App\Services\StripeService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Transfer as StripeTransfer;
use Stripe\Exception\ApiErrorException;

class TransferService
{
    protected StripeService $stripeService;
    protected NotificationService $notificationService;
    protected float $defaultCommissionPercentage = 15.0; // 15% default commission

    public function __construct(
        StripeService $stripeService,
        NotificationService $notificationService
    ) {
        $this->stripeService = $stripeService;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle automatic transfer for a completed transaction
     * 
     * @param Transaction $transaction
     * @return array
     */
    public function handleAutomaticTransfer(Transaction $transaction): array
    {
        try {
            // التحقق من صحة البيانات
            if (!$this->validateTransaction($transaction)) {
                return [
                    'success' => false,
                    'message' => 'Invalid transaction data',
                ];
            }

            // حساب التقسيم (العمولة والصافي)
            $splitResult = $this->calculateSplit($transaction);
            
            if (!$splitResult['success']) {
                return $splitResult;
            }

            // الحصول على Stripe account ID للمستخدم
            $stripeAccountId = $this->getStripeAccountId($transaction);
            
            if (!$stripeAccountId) {
                // إذا لم يكن هناك Stripe account، ننشئ transfer في حالة pending
                return $this->createPendingTransfer($transaction, $splitResult, null);
            }

            // إنشاء transfer record في قاعدة البيانات
            $transfer = $this->createTransferRecord($transaction, $splitResult, $stripeAccountId);

            // تنفيذ التحويل عبر Stripe
            $transferResult = $this->executeStripeTransfer($transfer);

            if ($transferResult['success']) {
                // تحديث حالة التحويل إلى completed
                $transfer->markAsCompleted($transferResult['stripe_transfer_id']);

                // إرسال إشعار للمستخدم
                $this->sendTransferNotification($transfer, 'completed');

                Log::info('Automatic transfer completed successfully', [
                    'transfer_id' => $transfer->id,
                    'transaction_id' => $transaction->id,
                    'net_amount' => $splitResult['net_amount'],
                    'commission_amount' => $splitResult['commission_amount'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Transfer completed successfully',
                    'transfer' => $transfer,
                ];
            } else {
                // فشل التحويل - سيتم إعادة المحاولة لاحقاً
                $transfer->markAsFailed($transferResult['error'] ?? 'Transfer failed');

                Log::error('Automatic transfer failed', [
                    'transfer_id' => $transfer->id,
                    'transaction_id' => $transaction->id,
                    'error' => $transferResult['error'],
                ]);

                return [
                    'success' => false,
                    'message' => 'Transfer failed, will retry',
                    'transfer' => $transfer,
                    'error' => $transferResult['error'],
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exception in handleAutomaticTransfer', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Transfer processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate transaction data
     */
    protected function validateTransaction(Transaction $transaction): bool
    {
        // التحقق من أن حالة المعاملة هي completed
        if ($transaction->status !== 'completed') {
            return false;
        }

        // التحقق من وجود مبلغ
        if (!$transaction->amount || $transaction->amount <= 0) {
            return false;
        }

        // التحقق من وجود payee (المستفيد)
        if (!$transaction->payee_type || !$transaction->payee_id) {
            return false;
        }

        // التحقق من أن المعاملة لم يتم تحويلها من قبل
        $existingTransfer = Transfer::where('transaction_id', $transaction->id)
            ->where('status', 'completed')
            ->first();

        if ($existingTransfer) {
            return false; // تم التحويل مسبقاً
        }

        return true;
    }

    /**
     * Calculate split (commission and net amount)
     */
    protected function calculateSplit(Transaction $transaction): array
    {
        try {
            $grossAmount = (float) $transaction->amount;
            
            // استخدام commission_amount الموجود في المعاملة إذا كان موجوداً
            $commissionAmount = (float) ($transaction->commission_amount ?? 0);

            // إذا لم يكن هناك commission_amount، نحسبه
            if ($commissionAmount <= 0) {
                // الحصول على نسبة العمولة
                $commissionPercentage = $this->getCommissionPercentage($transaction);
                
                // حساب العمولة
                $commissionAmount = ($grossAmount * $commissionPercentage) / 100;
            }

            // حساب المبلغ الصافي
            $netAmount = $grossAmount - $commissionAmount;

            // التأكد من أن المبالغ صحيحة
            if ($netAmount < 0) {
                return [
                    'success' => false,
                    'message' => 'Net amount cannot be negative',
                ];
            }

            return [
                'success' => true,
                'gross_amount' => round($grossAmount, 2),
                'commission_amount' => round($commissionAmount, 2),
                'net_amount' => round($netAmount, 2),
            ];

        } catch (\Exception $e) {
            Log::error('Error calculating split', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error calculating split: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get commission percentage for transaction
     */
    protected function getCommissionPercentage(Transaction $transaction): float
    {
        // إذا كان payee هو ACC، استخدم commission_percentage من ACC
        if ($transaction->payee_type === 'acc') {
            $acc = ACC::find($transaction->payee_id);
            if ($acc && $acc->commission_percentage) {
                // نسبة العمولة للمنصة من ACC
                // إذا كان ACC يأخذ 10%، فالمنصة تأخذ 100 - 10 = 90% من المبلغ الإجمالي
                // لكننا نريد نسبة المنصة من المبلغ الإجمالي
                // للبساطة، نستخدم نسبة ثابتة أو من إعدادات ACC
                return 100 - (float) $acc->commission_percentage; // نسبة المنصة
            }
        }

        // استخدام النسبة الافتراضية
        return $this->defaultCommissionPercentage;
    }

    /**
     * Get Stripe account ID for the payee
     */
    protected function getStripeAccountId(Transaction $transaction): ?string
    {
        try {
            if ($transaction->payee_type === 'acc') {
                $acc = ACC::find($transaction->payee_id);
                return $acc?->stripe_account_id;
            }

            if ($transaction->payee_type === 'training_center') {
                $trainingCenter = TrainingCenter::find($transaction->payee_id);
                // يمكن إضافة stripe_account_id لـ TrainingCenter لاحقاً
                return null;
            }

            if ($transaction->payee_type === 'instructor') {
                $instructor = Instructor::find($transaction->payee_id);
                // يمكن إضافة stripe_account_id لـ Instructor لاحقاً
                return null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting Stripe account ID', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create transfer record in database
     */
    protected function createTransferRecord(Transaction $transaction, array $splitResult, ?string $stripeAccountId): Transfer
    {
        // الحصول على user_id من payee
        $userId = null;
        if ($transaction->payee_type === 'acc') {
            $acc = ACC::find($transaction->payee_id);
            $user = \App\Models\User::where('email', $acc?->email)->first();
            $userId = $user?->id;
        }

        return Transfer::create([
            'transaction_id' => $transaction->id,
            'user_id' => $userId,
            'user_type' => $transaction->payee_type,
            'user_type_id' => $transaction->payee_id,
            'gross_amount' => $splitResult['gross_amount'],
            'commission_amount' => $splitResult['commission_amount'],
            'net_amount' => $splitResult['net_amount'],
            'stripe_account_id' => $stripeAccountId,
            'status' => 'pending',
        ]);
    }

    /**
     * Create pending transfer when Stripe account is not available
     */
    protected function createPendingTransfer(Transaction $transaction, array $splitResult, ?string $errorMessage): array
    {
        $transfer = $this->createTransferRecord($transaction, $splitResult, null);
        
        $transfer->update([
            'status' => 'pending',
            'error_message' => $errorMessage ?? 'Stripe account not configured',
        ]);

        Log::info('Transfer created in pending status (no Stripe account)', [
            'transfer_id' => $transfer->id,
            'transaction_id' => $transaction->id,
        ]);

        // إشعار الإدارة بأن هناك transfer في حالة pending
        $this->notifyAdminPendingTransfer($transfer);

        return [
            'success' => true,
            'message' => 'Transfer created in pending status',
            'transfer' => $transfer,
            'pending_reason' => 'Stripe account not configured',
        ];
    }

    /**
     * Execute Stripe transfer
     */
    protected function executeStripeTransfer(Transfer $transfer): array
    {
        try {
            if (!$this->stripeService->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Stripe is not configured',
                ];
            }

            if (!$transfer->stripe_account_id) {
                return [
                    'success' => false,
                    'error' => 'Stripe account ID is required',
                ];
            }

            // تحويل المبلغ إلى cents (Stripe requires amount in smallest currency unit)
            $amountInCents = (int) ($transfer->net_amount * 100);

            // التحقق من أن المبلغ أكبر من 0
            if ($amountInCents <= 0) {
                return [
                    'success' => false,
                    'error' => 'Transfer amount must be greater than 0',
                ];
            }

            // إنشاء Transfer في Stripe
            $stripeTransfer = StripeTransfer::create([
                'amount' => $amountInCents,
                'currency' => strtolower($transfer->transaction->currency ?? 'usd'),
                'destination' => $transfer->stripe_account_id,
                'metadata' => [
                    'transfer_id' => $transfer->id,
                    'transaction_id' => $transfer->transaction_id,
                    'user_type' => $transfer->user_type,
                    'user_type_id' => $transfer->user_type_id,
                ],
            ], [
                'idempotency_key' => 'transfer_' . $transfer->id . '_' . $transfer->transaction_id,
            ]);

            return [
                'success' => true,
                'stripe_transfer_id' => $stripeTransfer->id,
                'stripe_transfer' => $stripeTransfer,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe transfer failed', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Exception in executeStripeTransfer', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retry failed transfer
     */
    public function retryFailedTransfer(Transfer $transfer): array
    {
        if (!$transfer->canRetry()) {
            return [
                'success' => false,
                'message' => 'Transfer cannot be retried',
            ];
        }

        $transfer->markAsRetrying();

        return $this->executeStripeTransfer($transfer);
    }

    /**
     * Send transfer notification to user
     */
    public function sendTransferNotification(Transfer $transfer, string $status): void
    {
        try {
            if ($transfer->user_id) {
                $message = $status === 'completed'
                    ? "تم تحويل مبلغ {$transfer->net_amount} {$transfer->transaction->currency} إلى حسابك بنجاح"
                    : "فشل تحويل مبلغ {$transfer->net_amount} {$transfer->transaction->currency}";

                $this->notificationService->createNotification(
                    $transfer->user_id,
                    'transfer_' . $status,
                    'Transfer ' . ucfirst($status),
                    $message,
                    [
                        'transfer_id' => $transfer->id,
                        'amount' => $transfer->net_amount,
                        'currency' => $transfer->transaction->currency,
                        'status' => $status,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send transfer notification', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admin about pending transfer
     */
    protected function notifyAdminPendingTransfer(Transfer $transfer): void
    {
        try {
            // إرسال إشعار للمديرين
            $admins = \App\Models\User::where('role', 'admin')->get();
            
            foreach ($admins as $admin) {
                $this->notificationService->createNotification(
                    $admin->id,
                    'pending_transfer',
                    'Pending Transfer',
                    "Transfer #{$transfer->id} is pending - Stripe account not configured",
                    [
                        'transfer_id' => $transfer->id,
                        'transaction_id' => $transfer->transaction_id,
                        'amount' => $transfer->net_amount,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify admin about pending transfer', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

