<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\CertificateCode;
use App\Models\CertificatePricing;
use App\Models\CodeBatch;
use App\Models\Course;
use App\Models\DiscountCode;
use App\Models\TrainingCenter;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CodePurchaseService
{
    protected StripeService $stripeService;
    protected NotificationService $notificationService;
    protected FileUploadService $fileUploadService;

    public function __construct(
        StripeService $stripeService,
        NotificationService $notificationService,
        FileUploadService $fileUploadService
    ) {
        $this->stripeService = $stripeService;
        $this->notificationService = $notificationService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Validate purchase request
     *
     * @param Request $request
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function validatePurchaseRequest(Request $request, TrainingCenter $trainingCenter): array
    {
        // Verify ACC exists and is active
        $acc = ACC::find($request->acc_id);
        if (!$acc) {
            return ['valid' => false, 'message' => 'ACC not found', 'code' => 404];
        }

        if ($acc->status !== 'active') {
            return ['valid' => false, 'message' => 'ACC is not active', 'code' => 403];
        }

        // Verify Training Center has authorization from ACC
        $authorization = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('acc_id', $request->acc_id)
            ->where('status', 'approved')
            ->first();

        if (!$authorization) {
            return [
                'valid' => false,
                'message' => 'Training Center does not have authorization from this ACC',
                'code' => 403
            ];
        }

        // Verify course exists and belongs to ACC
        $course = Course::where('id', $request->course_id)
            ->where('acc_id', $request->acc_id)
            ->first();

        if (!$course) {
            return [
                'valid' => false,
                'message' => 'Course not found or does not belong to this ACC',
                'code' => 404
            ];
        }

        // Get pricing (effective_from and effective_to are date fields, not datetime)
        $today = now()->toDateString();
        $pricing = CertificatePricing::where('course_id', $request->course_id)
            ->where('acc_id', $request->acc_id)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->latest('effective_from')
            ->first();

        if (!$pricing) {
            // Check if pricing exists but is not active (for better error message)
            $inactivePricing = CertificatePricing::where('course_id', $request->course_id)
                ->where('acc_id', $request->acc_id)
                ->first();
            
            if ($inactivePricing) {
                $message = 'No active price found for this course. ';
                $today = now()->toDateString();
                
                if ($inactivePricing->effective_from > $today) {
                    $message .= 'Price will be effective from ' . $inactivePricing->effective_from . '.';
                } elseif ($inactivePricing->effective_to && $inactivePricing->effective_to < $today) {
                    $message .= 'Price expired on ' . $inactivePricing->effective_to . '.';
                } else {
                    $message .= 'Please contact the ACC to set up pricing for this course.';
                }
                return ['valid' => false, 'message' => $message, 'code' => 404];
            }
            
            return [
                'valid' => false, 
                'message' => 'No price found for this course. Please contact the ACC to set up pricing for this course.', 
                'code' => 404
            ];
        }

        return [
            'valid' => true,
            'acc' => $acc,
            'course' => $course,
            'pricing' => $pricing,
            'authorization' => $authorization
        ];
    }

    /**
     * Calculate price with discount
     *
     * @param CertificatePricing $pricing
     * @param int $quantity
     * @param string|null $discountCode
     * @param int $accId
     * @param int $courseId
     * @return array
     */
    public function calculatePrice(
        CertificatePricing $pricing,
        int $quantity,
        ?string $discountCode,
        int $accId,
        int $courseId
    ): array {
        $unitPrice = $pricing->base_price;
        $totalAmount = $unitPrice * $quantity;
        $discountAmount = 0;
        $discountCodeId = null;
        $finalAmount = $totalAmount;
        $discountCodeModel = null;

        // Validate and apply discount if provided
        if ($discountCode) {
            $discountCodeModel = DiscountCode::where('code', $discountCode)
                ->where('acc_id', $accId)
                ->first();

            if (!$discountCodeModel) {
                return [
                    'success' => false,
                    'message' => 'Invalid discount code'
                ];
            }

            // Validate discount code status
            if ($discountCodeModel->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Discount code is not active'
                ];
            }

            // Validate discount code dates
            if ($discountCodeModel->start_date && $discountCodeModel->start_date > now()) {
                return [
                    'success' => false,
                    'message' => 'Discount code has not started yet'
                ];
            }

            if ($discountCodeModel->end_date && $discountCodeModel->end_date < now()) {
                return [
                    'success' => false,
                    'message' => 'Discount code has expired'
                ];
            }

            // Validate if discount applies to this course
            if ($discountCodeModel->applicable_course_ids &&
                !in_array($courseId, $discountCodeModel->applicable_course_ids)) {
                return [
                    'success' => false,
                    'message' => 'Discount code does not apply to this course'
                ];
            }

            // Validate quantity limit for quantity-based discounts
            if ($discountCodeModel->discount_type === 'quantity_based') {
                $remainingQuantity = $discountCodeModel->total_quantity - ($discountCodeModel->used_quantity ?? 0);
                if ($remainingQuantity < $quantity) {
                    return [
                        'success' => false,
                        'message' => 'Discount code quantity limit exceeded'
                    ];
                }
            }

            // Apply discount
            $discountAmount = ($totalAmount * $discountCodeModel->discount_percentage) / 100;
            $finalAmount = $totalAmount - $discountAmount;
            $discountCodeId = $discountCodeModel->id;
        }

        return [
            'success' => true,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'discount_code_id' => $discountCodeId,
            'discount_code' => $discountCodeModel
        ];
    }

    /**
     * Create payment intent for code purchase
     *
     * @param Request $request
     * @param TrainingCenter $trainingCenter
     * @param array $validationResult
     * @param array $priceCalculation
     * @return array
     */
    public function createPaymentIntent(
        Request $request,
        TrainingCenter $trainingCenter,
        array $validationResult,
        array $priceCalculation
    ): array {
        $acc = $validationResult['acc'];
        $pricing = $validationResult['pricing'];
        $finalAmount = $priceCalculation['final_amount'];

        // Check if Stripe is configured
        if (!$this->stripeService->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Stripe payment is not configured',
                'code' => 400
            ];
        }

        // Calculate commission amounts
        $groupCommissionPercentage = $acc->commission_percentage ?? 0;
        $groupCommissionAmount = ($finalAmount * $groupCommissionPercentage) / 100;

        // Prepare metadata
        $metadata = [
            'transaction_type' => 'code_purchase',
            'payer_type' => 'training_center',
            'payer_id' => (string)$trainingCenter->id,
            'payee_type' => 'acc',
            'payee_id' => (string)$request->acc_id,
            'course_id' => (string)$request->course_id,
            'quantity' => (string)$request->quantity,
            'type' => 'code_purchase',
            'discount_code' => $request->discount_code ?? '',
            'group_commission_percentage' => (string)$groupCommissionPercentage,
            'group_commission_amount' => (string)$groupCommissionAmount,
        ];

        // Use destination charges if ACC has Stripe account ID
        if (!empty($acc->stripe_account_id) && $groupCommissionAmount > 0) {
            $result = $this->stripeService->createDestinationChargePaymentIntent(
                $finalAmount,
                $acc->stripe_account_id,
                $groupCommissionAmount,
                strtolower($pricing->currency ?? 'usd'),
                $metadata
            );

            // If destination charge fails, fallback to standard payment
            if (!$result['success']) {
                Log::warning('Destination charge failed, falling back to standard payment', [
                    'acc_id' => $acc->id,
                    'stripe_account_id' => $acc->stripe_account_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                $result = $this->stripeService->createPaymentIntent(
                    $finalAmount,
                    $pricing->currency ?? 'USD',
                    $metadata
                );
            }
        } else {
            $result = $this->stripeService->createPaymentIntent(
                $finalAmount,
                $pricing->currency ?? 'USD',
                $metadata
            );
        }

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create payment intent',
                'error' => $result['error'] ?? 'Unknown error',
                'code' => 500
            ];
        }

        return [
            'success' => true,
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['payment_intent_id'],
            'amount' => $finalAmount,
            'currency' => $pricing->currency ?? 'USD',
            'commission_amount' => $result['commission_amount'] ?? $groupCommissionAmount,
            'provider_amount' => $result['provider_amount'] ?? null,
            'payment_type' => !empty($acc->stripe_account_id) && $groupCommissionAmount > 0 ? 'destination_charge' : 'standard',
        ];
    }


/**
 * Process code purchase
 *
 * @param Request $request
 * @param TrainingCenter $trainingCenter
 * @param array $validationResult
 * @param array $priceCalculation
 * @return array
 * @throws \Exception
 */
public function processPurchase(
    Request $request,
    TrainingCenter $trainingCenter,
    array $validationResult,
    array $priceCalculation
): array {
    $acc = $validationResult['acc'];
    $course = $validationResult['course'];
    $pricing = $validationResult['pricing'];
    $finalAmount = $priceCalculation['final_amount'];
    $discountCode = $priceCalculation['discount_code'];

    $paymentMethod = $request->payment_method;
    $uploadedFiles = [];

    try {
        DB::beginTransaction();

        // Handle payment based on payment method
        $paymentResult = $this->processPayment(
            $request,
            $trainingCenter,
            $acc,
            $finalAmount,
            $pricing->currency ?? 'USD'
        );

        // Check if payment processing failed
        if (!$paymentResult['success']) {
            Log::error('Payment processing failed in processPurchase', [
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $acc->id,
                'payment_result' => $paymentResult,
            ]);
            
            // Rollback transaction
            DB::rollBack();
            
            // Return the error directly instead of throwing exception
            return [
                'success' => false,
                'message' => $paymentResult['message'] ?? 'Payment processing failed',
                'error' => $paymentResult['error'] ?? null,
                'error_code' => $paymentResult['error_code'] ?? 'payment_failed',
                'expected_amount' => $paymentResult['expected_amount'] ?? null,
                'provided_amount' => $paymentResult['provided_amount'] ?? null,
                'difference' => $paymentResult['difference'] ?? null,
            ];
        }

        $transactionData = $paymentResult['transaction_data'];
        $uploadedFiles = $paymentResult['uploaded_files'] ?? [];
        $paymentStatus = $paymentResult['payment_status'] ?? 'completed';
        $paymentReceiptUrl = $paymentResult['payment_receipt_url'] ?? null;
        $paymentAmount = $paymentResult['payment_amount'] ?? null;

        // Create transaction first (before batch)
        $transaction = Transaction::create($transactionData);

        // Create batch
        $batchData = [
            'training_center_id' => $trainingCenter->id,
            'acc_id' => $acc->id,
            'course_id' => $course->id,
            'quantity' => $request->quantity,
            'total_amount' => $finalAmount,
            'payment_method' => $paymentMethod, // Store payment method directly (credit_card or manual_payment)
            'transaction_id' => (string)$transaction->id,
            'purchase_date' => now(),
            'payment_status' => $paymentStatus,
        ];

        if ($paymentReceiptUrl) {
            $batchData['payment_receipt_url'] = $paymentReceiptUrl;
        }
        if ($paymentAmount) {
            $batchData['payment_amount'] = $paymentAmount;
        }

        $batch = CodeBatch::create($batchData);

        // Update transaction with batch reference
        $transaction->update([
            'reference_id' => $batch->id,
            'reference_type' => 'code_batch',
        ]);

        // Generate codes only if payment is completed
        $codes = [];
        $codeModels = [];
        if ($paymentStatus === 'completed') {
            $codes = $this->generateCodes($course->id, $request->quantity);

            // Create certificate codes
            foreach ($codes as $code) {
                $codeModel = CertificateCode::create([
                    'code' => $code,
                    'batch_id' => $batch->id,
                    'training_center_id' => $trainingCenter->id,
                    'acc_id' => $acc->id,
                    'course_id' => $course->id,
                    'purchased_price' => $priceCalculation['unit_price'],
                    'discount_applied' => $priceCalculation['discount_amount'] > 0,
                    'discount_code_id' => $priceCalculation['discount_code_id'],
                    'status' => 'available',
                    'purchased_at' => now(),
                ]);
                $codeModels[] = $codeModel;
            }

            // Update discount code usage if applicable
            if ($discountCode && $discountCode->discount_type === 'quantity_based') {
                $discountCode->increment('used_quantity', $request->quantity);
                if ($discountCode->used_quantity >= $discountCode->total_quantity) {
                    $discountCode->update(['status' => 'depleted']);
                }
            }

            // Create commission ledger entry
            $groupCommissionPercentage = $acc->commission_percentage ?? 0;
            $groupCommissionAmount = ($finalAmount * $groupCommissionPercentage) / 100;
            $accCommissionAmount = $finalAmount - $groupCommissionAmount;
            $accCommissionPercentage = 100 - $groupCommissionPercentage;

            try {
                \App\Models\CommissionLedger::create([
                    'transaction_id' => $transaction->id,
                    'acc_id' => $acc->id,
                    'training_center_id' => $trainingCenter->id,
                    'group_commission_amount' => $groupCommissionAmount,
                    'group_commission_percentage' => $groupCommissionPercentage,
                    'acc_commission_amount' => $accCommissionAmount,
                    'acc_commission_percentage' => $accCommissionPercentage,
                    'settlement_status' => 'pending',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create commission ledger entry', [
                    'error' => $e->getMessage(),
                    'transaction_id' => $transaction->id,
                ]);
            }
        }

        DB::commit();

        // Send notifications (wrap in try-catch to prevent failures)
        try {
            $user = \App\Models\User::where('email', $trainingCenter->email)->first();
            
            if ($paymentStatus === 'completed') {
                if ($user) {
                    $this->notificationService->notifyCodePurchaseSuccess(
                        $user->id,
                        $batch->id,
                        $request->quantity,
                        $finalAmount
                    );
                }
                
                $groupCommissionPercentage = $acc->commission_percentage ?? 0;
                $groupCommissionAmount = ($finalAmount * $groupCommissionPercentage) / 100;
                
                // Notify admin
                if (method_exists($this->notificationService, 'notifyAdminCodePurchase')) {
                    $this->notificationService->notifyAdminCodePurchase(
                        $batch->id,
                        $trainingCenter->name,
                        $request->quantity,
                        $finalAmount,
                        $groupCommissionAmount
                    );
                }
            } else {
                // Manual payment notifications
                if ($user) {
                    if (method_exists($this->notificationService, 'notifyManualPaymentPending')) {
                        $this->notificationService->notifyManualPaymentPending(
                            $user->id,
                            $batch->id,
                            $request->quantity,
                            $finalAmount
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Notification sending failed', [
                'error' => $e->getMessage(),
                'batch_id' => $batch->id,
            ]);
        }

        return [
            'success' => true,
            'batch' => $batch->load(['course', 'acc']),
            'codes' => $codeModels,
            'transaction' => $transaction,
            'payment_status' => $paymentStatus,
        ];

    } catch (\Exception $e) {
        DB::rollBack();

        // Cleanup uploaded files
        $this->fileUploadService->cleanupFiles($uploadedFiles);

        Log::error('Code purchase failed with exception', [
            'training_center_id' => $trainingCenter->id,
            'acc_id' => $acc->id,
            'course_id' => $course->id,
            'error' => $e->getMessage(),
            'error_class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        throw $e;
    }
}


/**
 * Process payment based on payment method
 *
 * @param Request $request
 * @param TrainingCenter $trainingCenter
 * @param ACC $acc
 * @param float $finalAmount
 * @param string $currency
 * @return array
 */
private function processPayment(
    Request $request,
    TrainingCenter $trainingCenter,
    ACC $acc,
    float $finalAmount,
    string $currency
): array {
    $paymentMethod = $request->payment_method;
    $uploadedFiles = [];
    $paymentStatus = 'completed';
    $paymentReceiptUrl = null;
    $paymentAmount = null;
    
    $transactionData = [
        'payer_type' => 'training_center',
        'payer_id' => $trainingCenter->id,
        'payee_type' => 'acc',
        'payee_id' => $acc->id,
        'amount' => $finalAmount,
        'currency' => $currency,
        'type' => 'code_purchase',
        'status' => 'completed',
        'payment_method' => $paymentMethod === 'manual_payment' ? 'manual_payment' : 'credit_card',
    ];

    if ($paymentMethod === 'credit_card') {
        // Verify payment intent with enhanced error handling
        try {
            // First, retrieve the payment intent to check its status
            $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
            
            if (!$paymentIntent) {
                return [
                    'success' => false,
                    'message' => 'Payment intent not found',
                    'error' => 'Invalid payment intent ID'
                ];
            }
            
            // If payment method ID is provided and payment intent requires payment method, attach it
            if ($request->payment_method_id && $paymentIntent->status === 'requires_payment_method') {
                try {
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);
                    $paymentIntent = $paymentIntent->update([
                        'payment_method' => $request->payment_method_id,
                    ]);
                    
                    Log::info('Payment method attached to payment intent', [
                        'payment_intent_id' => $request->payment_intent_id,
                        'payment_method_id' => $request->payment_method_id,
                        'new_status' => $paymentIntent->status
                    ]);
                } catch (\Exception $attachError) {
                    Log::error('Failed to attach payment method', [
                        'payment_intent_id' => $request->payment_intent_id,
                        'payment_method_id' => $request->payment_method_id,
                        'error' => $attachError->getMessage()
                    ]);
                    return [
                        'success' => false,
                        'message' => 'Failed to attach payment method to payment intent',
                        'error' => $attachError->getMessage(),
                        'error_code' => 'payment_method_attach_failed'
                    ];
                }
            }
            
            // Refresh payment intent status after potential attachment
            if ($request->payment_method_id) {
                $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
            }
            
            // If payment intent requires confirmation and has a payment method, try to confirm it
            if ($paymentIntent->status === 'requires_confirmation' && $paymentIntent->payment_method) {
                try {
                    $confirmResult = $this->stripeService->confirmPaymentIntent($request->payment_intent_id);
                    if ($confirmResult['success']) {
                        $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
                    }
                } catch (\Exception $confirmError) {
                    Log::warning('Failed to auto-confirm payment intent', [
                        'payment_intent_id' => $request->payment_intent_id,
                        'error' => $confirmError->getMessage()
                    ]);
                }
            }
            
            // Now verify the payment intent
            $this->stripeService->verifyPaymentIntent(
                $request->payment_intent_id,
                $finalAmount,
                [
                    'payer_id' => (string)$trainingCenter->id,
                    'payee_id' => (string)$acc->id,
                    'course_id' => (string)$request->course_id,
                    'quantity' => (string)$request->quantity,
                    'type' => 'code_purchase',
                ]
            );

            $transactionData['stripe_payment_intent_id'] = $request->payment_intent_id;
            $transactionData['status'] = 'completed';
            
            // Get commission and provider amounts from payment intent metadata if available
            if ($paymentIntent && isset($paymentIntent->metadata)) {
                $metadata = $paymentIntent->metadata;
                if (isset($metadata['payment_type'])) {
                    $transactionData['payment_type'] = $metadata['payment_type'];
                }
                // If destination charge, commission and provider amounts are already set in transactionData
                // but we can verify from payment intent metadata
                if (isset($metadata['commission_amount'])) {
                    $transactionData['commission_amount'] = (float)$metadata['commission_amount'];
                    $transactionData['provider_amount'] = $finalAmount - (float)$metadata['commission_amount'];
                }
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Provide helpful error messages based on payment status
            if (strpos($errorMessage, 'requires_payment_method') !== false) {
                return [
                    'success' => false,
                    'message' => 'Payment method not attached. Please provide payment_method_id or attach a payment method on the frontend before submitting the purchase.',
                    'error' => $errorMessage,
                    'error_code' => 'payment_not_confirmed'
                ];
            } elseif (strpos($errorMessage, 'requires_confirmation') !== false) {
                return [
                    'success' => false,
                    'message' => 'Payment requires confirmation.',
                    'error' => $errorMessage,
                    'error_code' => 'payment_requires_confirmation'
                ];
            } elseif (strpos($errorMessage, 'processing') !== false) {
                return [
                    'success' => false,
                    'message' => 'Payment is still processing. Please wait a moment and try again.',
                    'error' => $errorMessage,
                    'error_code' => 'payment_processing'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $errorMessage
            ];
        }

    } elseif ($paymentMethod === 'manual_payment') {
        // Log the incoming request data for debugging
        Log::info('Processing manual payment', [
            'training_center_id' => $trainingCenter->id,
            'acc_id' => $acc->id,
            'has_file' => $request->hasFile('payment_receipt'),
            'payment_amount' => $request->payment_amount,
            'final_amount' => $finalAmount,
            'files' => $request->allFiles(),
        ]);

        // Validate payment amount exists
        if (!$request->has('payment_amount') || $request->payment_amount === null) {
            return [
                'success' => false,
                'message' => 'Payment amount is required for manual payment'
            ];
        }

        // Validate payment amount matches calculated amount
        $paymentAmount = floatval($request->payment_amount);
        $amountDifference = abs($paymentAmount - $finalAmount);
        
        Log::info('Payment amount validation', [
            'payment_amount' => $paymentAmount,
            'final_amount' => $finalAmount,
            'difference' => $amountDifference,
        ]);

        if ($amountDifference > 0.01) {
            return [
                'success' => false,
                'message' => 'Payment amount does not match the calculated total amount',
                'expected_amount' => $finalAmount,
                'provided_amount' => $paymentAmount,
                'difference' => $amountDifference
            ];
        }

        // Handle manual payment receipt upload
        if (!$request->hasFile('payment_receipt')) {
            Log::error('Payment receipt file not found', [
                'has_file' => $request->hasFile('payment_receipt'),
                'all_files' => $request->allFiles(),
                'input_keys' => array_keys($request->all()),
            ]);
            
            return [
                'success' => false,
                'message' => 'Payment receipt is required for manual payment. Please ensure the file is being uploaded correctly.'
            ];
        }

        $file = $request->file('payment_receipt');
        
        // Validate file
        if (!$file->isValid()) {
            Log::error('Invalid payment receipt file', [
                'file_error' => $file->getError(),
                'file_error_message' => $file->getErrorMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Payment receipt file is invalid: ' . $file->getErrorMessage()
            ];
        }

        Log::info('Uploading payment receipt', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
        ]);

        try {
            $receiptResult = $this->fileUploadService->uploadDocument(
                $file,
                $trainingCenter->id,
                'training_center',
                'receipt'
            );

            if (!$receiptResult['success']) {
                Log::error('File upload service failed', [
                    'error' => $receiptResult['error'] ?? 'Unknown error',
                    'result' => $receiptResult,
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to upload payment receipt: ' . ($receiptResult['error'] ?? 'Unknown error')
                ];
            }

            $paymentReceiptUrl = $receiptResult['url'];
            $uploadedFiles[] = $receiptResult['file_path'];
            
            Log::info('Payment receipt uploaded successfully', [
                'url' => $paymentReceiptUrl,
                'file_path' => $receiptResult['file_path'],
            ]);

        } catch (\Exception $uploadError) {
            Log::error('Exception during file upload', [
                'error' => $uploadError->getMessage(),
                'trace' => $uploadError->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to upload payment receipt: ' . $uploadError->getMessage()
            ];
        }

        $paymentStatus = 'pending';
        $transactionData['payment_receipt_url'] = $paymentReceiptUrl;
        $transactionData['payment_amount'] = $paymentAmount;
        $transactionData['status'] = 'pending';
    }

    // Calculate commissions using ACC's global commission_percentage
    // This commission is applied globally for all certificate purchases from this ACC
    $groupCommissionPercentage = $acc->commission_percentage ?? 0;
    $groupCommissionAmount = ($finalAmount * $groupCommissionPercentage) / 100;
    $accCommissionAmount = $finalAmount - $groupCommissionAmount;

    // Save commission amounts in Transaction (for Group Admin and ACC)
    $transactionData['commission_amount'] = $groupCommissionAmount; // Goes to Group Admin
    $transactionData['provider_amount'] = $accCommissionAmount; // Goes to ACC
    $transactionData['group_commission_percentage'] = $groupCommissionPercentage;
    $transactionData['group_commission_amount'] = $groupCommissionAmount;
    $transactionData['acc_commission_amount'] = $accCommissionAmount;
    
    // Set payment_type if not already set (for destination charge)
    if (!isset($transactionData['payment_type'])) {
        $transactionData['payment_type'] = (!empty($acc->stripe_account_id) && $groupCommissionAmount > 0) 
            ? 'destination_charge' 
            : 'standard';
    }

    return [
        'success' => true,
        'transaction_data' => $transactionData,
        'uploaded_files' => $uploadedFiles,
        'payment_status' => $paymentStatus,
        'payment_receipt_url' => $paymentReceiptUrl,
        'payment_amount' => $paymentAmount,
    ];
}

    /**
     * Generate unique certificate codes
     *
     * @param int $courseId
     * @param int $quantity
     * @return array
     */
    private function generateCodes(int $courseId, int $quantity): array
    {
        $codes = [];
        $attempts = 0;
        $maxAttempts = 1000;

        while (count($codes) < $quantity && $attempts < $maxAttempts) {
            $code = strtoupper(Str::random(12));
            
            // Check if code already exists
            if (!CertificateCode::where('code', $code)->exists()) {
                $codes[] = $code;
            }
            
            $attempts++;
        }

        if (count($codes) < $quantity) {
            throw new \Exception('Failed to generate unique codes. Please try again.');
        }

        return $codes;
    }
}

