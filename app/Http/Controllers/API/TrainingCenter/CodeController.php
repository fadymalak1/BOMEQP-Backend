<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\CertificateCode;
use App\Models\CodeBatch;
use App\Models\DiscountCode;
use App\Models\CertificatePricing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CodeController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    #[OA\Post(
        path: "/training-center/codes/create-payment-intent",
        summary: "Create payment intent for code purchase",
        description: "Create a Stripe payment intent for purchasing certificate codes. Calculates pricing including discounts. Also returns information about available payment methods including manual payment option.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["acc_id", "course_id", "quantity"],
                properties: [
                    new OA\Property(property: "acc_id", type: "integer", example: 1),
                    new OA\Property(property: "course_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 10, minimum: 1),
                    new OA\Property(property: "discount_code", type: "string", nullable: true, example: "DISCOUNT10")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment intent created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "client_secret", type: "string", example: "pi_xxx_secret_xxx"),
                        new OA\Property(property: "payment_intent_id", type: "string", example: "pi_xxx"),
                        new OA\Property(property: "amount", type: "number", example: 1000.00),
                        new OA\Property(property: "currency", type: "string", example: "USD"),
                        new OA\Property(property: "total_amount", type: "string", example: "1000.00"),
                        new OA\Property(property: "discount_amount", type: "string", nullable: true, example: "100.00"),
                        new OA\Property(property: "final_amount", type: "string", example: "900.00"),
                        new OA\Property(property: "unit_price", type: "string", example: "100.00"),
                        new OA\Property(property: "quantity", type: "integer", example: 10),
                        new OA\Property(property: "payment_methods_available", type: "array", items: new OA\Items(type: "string"), example: ["credit_card", "manual_payment"]),
                        new OA\Property(property: "manual_payment_info", type: "object", description: "Information about manual payment option")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid request or pricing not found"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center, ACC, or course not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to create payment intent")
        ]
    )]
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'acc_id' => 'required|integer|exists:accs,id',
            'course_id' => 'required|integer|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Verify ACC exists and is active
        $acc = \App\Models\ACC::find($request->acc_id);
        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        if ($acc->status !== 'active') {
            return response()->json(['message' => 'ACC is not active'], 403);
        }

        // Verify Training Center has authorization from ACC
        $authorization = \App\Models\TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('acc_id', $request->acc_id)
            ->where('status', 'approved')
            ->first();

        if (!$authorization) {
            return response()->json([
                'message' => 'Training Center does not have authorization from this ACC'
            ], 403);
        }

        // Verify course exists and belongs to ACC
        $course = \App\Models\Course::where('id', $request->course_id)
            ->where('acc_id', $request->acc_id)
            ->first();

        if (!$course) {
            return response()->json([
                'message' => 'Course not found or does not belong to this ACC'
            ], 404);
        }

        // Get pricing
        $pricing = CertificatePricing::where('course_id', $request->course_id)
            ->where('acc_id', $request->acc_id)
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->latest()
            ->first();

        if (!$pricing) {
            return response()->json(['message' => 'Pricing not found for this course'], 404);
        }

        // Calculate total
        $unitPrice = $pricing->base_price;
        $totalAmount = $unitPrice * $request->quantity;
        $discountAmount = 0;
        $finalAmount = $totalAmount;

        // Validate and apply discount if provided
        if ($request->discount_code) {
            $discountCode = DiscountCode::where('code', $request->discount_code)
                ->where('acc_id', $request->acc_id)
                ->first();

            if (!$discountCode) {
                return response()->json([
                    'message' => 'Invalid discount code'
                ], 422);
            }

            // Validate discount code status
            if ($discountCode->status !== 'active') {
                return response()->json([
                    'message' => 'Discount code is not active'
                ], 422);
            }

            // Validate discount code dates
            if ($discountCode->start_date && $discountCode->start_date > now()) {
                return response()->json([
                    'message' => 'Discount code has not started yet'
                ], 422);
            }

            if ($discountCode->end_date && $discountCode->end_date < now()) {
                return response()->json([
                    'message' => 'Discount code has expired'
                ], 422);
            }

            // Validate if discount applies to this course
            if ($discountCode->applicable_course_ids && 
                !in_array($request->course_id, $discountCode->applicable_course_ids)) {
                return response()->json([
                    'message' => 'Discount code does not apply to this course'
                ], 422);
            }

            // Validate quantity limit for quantity-based discounts
            if ($discountCode->discount_type === 'quantity_based') {
                $remainingQuantity = $discountCode->total_quantity - ($discountCode->used_quantity ?? 0);
                if ($remainingQuantity < $request->quantity) {
                    return response()->json([
                        'message' => 'Discount code quantity limit exceeded'
                    ], 422);
                }
            }

            // Apply discount
            $discountAmount = ($totalAmount * $discountCode->discount_percentage) / 100;
            $finalAmount = $totalAmount - $discountAmount;
        }

        // Check if Stripe is configured
        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
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
            // Destination charge: money goes to ACC, commission goes to platform
            $result = $this->stripeService->createDestinationChargePaymentIntent(
                $finalAmount,
                $acc->stripe_account_id,
                $groupCommissionAmount,
                strtolower($pricing->currency ?? 'usd'),
                $metadata
            );
            
            // If destination charge fails, fallback to standard payment
            if (!$result['success']) {
                \Log::warning('Destination charge failed, falling back to standard payment', [
                    'acc_id' => $acc->id,
                    'stripe_account_id' => $acc->stripe_account_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                
                // Fallback to standard payment
                $result = $this->stripeService->createPaymentIntent(
                    $finalAmount,
                    $pricing->currency ?? 'USD',
                    $metadata
                );
            }
        } else {
            // Regular payment intent (fallback if no Stripe account or no commission)
        $result = $this->stripeService->createPaymentIntent(
            $finalAmount,
            $pricing->currency ?? 'USD',
            $metadata
        );
        }

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $result['error'] ?? 'Unknown error',
                'error_code' => $result['error_code'] ?? 'unknown_error'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['payment_intent_id'],
            'amount' => $finalAmount,
            'currency' => $pricing->currency ?? 'USD',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'final_amount' => number_format($finalAmount, 2, '.', ''),
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'quantity' => $request->quantity,
            'commission_amount' => isset($result['commission_amount']) ? number_format($result['commission_amount'], 2, '.', '') : number_format($groupCommissionAmount, 2, '.', ''),
            'provider_amount' => isset($result['provider_amount']) ? number_format($result['provider_amount'], 2, '.', '') : null,
            'payment_type' => !empty($acc->stripe_account_id) && $groupCommissionAmount > 0 ? 'destination_charge' : 'standard',
            'payment_methods_available' => ['credit_card', 'manual_payment'], // Indicate that manual payment is available
            'manual_payment_info' => [
                'available' => true,
                'requires_receipt' => true,
                'receipt_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_receipt_size_mb' => 10,
                'status_after_submission' => 'pending',
                'approval_required' => true,
            ],
        ], 200);
    }

    #[OA\Post(
        path: "/training-center/codes/purchase",
        summary: "Purchase certificate codes",
        description: "Purchase certificate codes after payment intent is confirmed. Generates codes and creates batch.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["acc_id", "course_id", "quantity", "payment_method", "payment_intent_id"],
                properties: [
                    new OA\Property(property: "acc_id", type: "integer", example: 1),
                    new OA\Property(property: "course_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 10, minimum: 1),
                    new OA\Property(property: "discount_code", type: "string", nullable: true, example: "DISCOUNT10"),
                    new OA\Property(property: "payment_method", type: "string", enum: ["credit_card"], example: "credit_card"),
                    new OA\Property(property: "payment_intent_id", type: "string", example: "pi_xxx")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Codes purchased successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Codes purchased successfully"),
                        new OA\Property(property: "batch", type: "object"),
                        new OA\Property(property: "codes", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "transaction", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Payment verification failed or invalid request"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "ACC not active or authorization required"),
            new OA\Response(response: 404, description: "Training center, ACC, course, or pricing not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function purchase(Request $request)
    {
        $request->validate([
            'acc_id' => 'required|integer|exists:accs,id',
            'course_id' => 'required|integer|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string|max:255',
            'payment_method' => 'required|in:credit_card,manual_payment',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string|max:255',
            'payment_receipt' => 'required_if:payment_method,manual_payment|nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
            'payment_amount' => 'required_if:payment_method,manual_payment|nullable|numeric|min:0',
        ], [
            'payment_receipt.required_if' => 'Payment receipt is required for manual payment. Please ensure you are sending the file as multipart/form-data.',
            'payment_receipt.file' => 'Payment receipt must be a valid file upload.',
            'payment_receipt.mimes' => 'Payment receipt must be a PDF, JPG, JPEG, or PNG file.',
            'payment_receipt.max' => 'Payment receipt file size must not exceed 10MB.',
            'payment_amount.required_if' => 'Payment amount is required for manual payment.',
            'payment_amount.numeric' => 'Payment amount must be a valid number.',
            'payment_amount.min' => 'Payment amount must be greater than 0.',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Verify ACC exists and is active
        $acc = \App\Models\ACC::find($request->acc_id);
        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        if ($acc->status !== 'active') {
            return response()->json(['message' => 'ACC is not active'], 403);
        }

        // Verify Training Center has authorization from ACC
        $authorization = \App\Models\TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('acc_id', $request->acc_id)
            ->where('status', 'approved')
            ->first();

        if (!$authorization) {
            return response()->json([
                'message' => 'Training Center does not have authorization from this ACC'
            ], 403);
        }

        // Verify course exists and belongs to ACC
        $course = \App\Models\Course::where('id', $request->course_id)
            ->where('acc_id', $request->acc_id)
            ->first();

        if (!$course) {
            return response()->json([
                'message' => 'Course not found or does not belong to this ACC'
            ], 404);
        }

        // Get pricing
        $pricing = CertificatePricing::where('course_id', $request->course_id)
            ->where('acc_id', $request->acc_id)
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->latest()
            ->first();

        if (!$pricing) {
            return response()->json(['message' => 'Pricing not found for this course'], 404);
        }

        // Calculate total
        $unitPrice = $pricing->base_price;
        $totalAmount = $unitPrice * $request->quantity;
        $discountAmount = 0;
        $discountCodeId = null;
        $finalAmount = $totalAmount;

        // Validate and apply discount if provided
        if ($request->discount_code) {
            $discountCode = DiscountCode::where('code', $request->discount_code)
                ->where('acc_id', $request->acc_id)
                ->first();

            if (!$discountCode) {
                return response()->json([
                    'message' => 'Invalid discount code'
                ], 422);
            }

            // Validate discount code status
            if ($discountCode->status !== 'active') {
                return response()->json([
                    'message' => 'Discount code is not active'
                ], 422);
            }

            // Validate discount code dates
            if ($discountCode->start_date && $discountCode->start_date > now()) {
                return response()->json([
                    'message' => 'Discount code has not started yet'
                ], 422);
            }

            if ($discountCode->end_date && $discountCode->end_date < now()) {
                return response()->json([
                    'message' => 'Discount code has expired'
                ], 422);
            }

            // Validate if discount applies to this course
            if ($discountCode->applicable_course_ids && 
                !in_array($request->course_id, $discountCode->applicable_course_ids)) {
                return response()->json([
                    'message' => 'Discount code does not apply to this course'
                ], 422);
            }

            // Validate quantity limit for quantity-based discounts
            if ($discountCode->discount_type === 'quantity_based') {
                $remainingQuantity = $discountCode->total_quantity - ($discountCode->used_quantity ?? 0);
                if ($remainingQuantity < $request->quantity) {
                    return response()->json([
                        'message' => 'Discount code quantity limit exceeded'
                    ], 422);
                }
            }

            // Apply discount
            $discountAmount = ($totalAmount * $discountCode->discount_percentage) / 100;
            $finalAmount = $totalAmount - $discountAmount;
            $discountCodeId = $discountCode->id;
        }

        // Get ACC commission percentage
        $groupCommissionPercentage = $acc->commission_percentage ?? 0;
        $accCommissionPercentage = 100 - $groupCommissionPercentage;

        // Calculate commission amounts based on final amount after discount
        $groupCommissionAmount = ($finalAmount * $groupCommissionPercentage) / 100;
        $accCommissionAmount = ($finalAmount * $accCommissionPercentage) / 100;

        // Handle payment based on payment method
        if ($request->payment_method === 'credit_card') {
            // Validate payment_intent_id for credit card payments
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            // Verify payment intent with Stripe
            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $finalAmount,
                    [
                        'payer_id' => (string)$trainingCenter->id,
                        'payee_id' => (string)$request->acc_id,
                        'course_id' => (string)$request->course_id,
                        'quantity' => (string)$request->quantity,
                        'type' => 'code_purchase',
                    ]
                );
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                
                // Provide more helpful error messages based on payment status
                if (strpos($errorMessage, 'requires_payment_method') !== false) {
                    return response()->json([
                        'message' => 'Payment not confirmed. Please complete the payment on the frontend before submitting the purchase.',
                        'error' => $errorMessage,
                        'error_code' => 'payment_not_confirmed',
                        'instructions' => 'The payment intent has been created but not yet confirmed. Please use Stripe.js to confirm the payment before calling this endpoint.'
                    ], 400);
                } elseif (strpos($errorMessage, 'processing') !== false) {
                    return response()->json([
                        'message' => 'Payment is still processing. Please wait a moment and try again.',
                        'error' => $errorMessage,
                        'error_code' => 'payment_processing'
                    ], 400);
                } elseif (strpos($errorMessage, 'canceled') !== false || strpos($errorMessage, 'requires_action') !== false) {
                    return response()->json([
                        'message' => 'Payment was canceled or requires additional action. Please try again with a new payment.',
                        'error' => $errorMessage,
                        'error_code' => 'payment_canceled'
                    ], 400);
                }
                
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $errorMessage
                ], 400);
            }
        } elseif ($request->payment_method === 'manual_payment') {
            // Validate manual payment fields
            if (!$request->hasFile('payment_receipt')) {
                return response()->json([
                    'message' => 'Payment receipt is required for manual payment'
                ], 422);
            }

            if (!$request->payment_amount || $request->payment_amount <= 0) {
                return response()->json([
                    'message' => 'Payment amount is required and must be greater than 0'
                ], 422);
            }

            // Validate payment amount matches calculated amount (allow small difference for rounding)
            $amountDifference = abs($request->payment_amount - $finalAmount);
            if ($amountDifference > 0.01) {
                return response()->json([
                    'message' => 'Payment amount does not match the calculated total amount',
                    'expected_amount' => $finalAmount,
                    'provided_amount' => $request->payment_amount
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $paymentStatus = 'completed';
            $paymentReceiptUrl = null;
            $paymentAmount = null;
            
            // Handle manual payment - upload receipt
            if ($request->payment_method === 'manual_payment') {
                $paymentStatus = 'pending';
                $paymentAmount = $request->payment_amount;
                
                // Upload payment receipt
                $receiptFile = $request->file('payment_receipt');
                
                if (!$receiptFile || !$receiptFile->isValid()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Payment receipt file is invalid or missing. Please ensure the request is sent as multipart/form-data with the receipt file.',
                        'error' => 'Invalid file upload'
                    ], 422);
                }
                
                try {
                    $originalName = $receiptFile->getClientOriginalName();
                    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                    $fileName = time() . '_' . $trainingCenter->id . '_' . $sanitizedName;
                    
                    $directory = 'training-centers/' . $trainingCenter->id . '/payment-receipts';
                    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($directory)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($directory, 0755, true);
                    }
                    
                    $receiptPath = $receiptFile->storeAs($directory, $fileName, 'public');
                    
                    if (!$receiptPath) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Failed to upload payment receipt. Please try again.',
                            'error' => 'File upload failed'
                        ], 500);
                    }
                    
                    $paymentReceiptUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($receiptPath);
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('Payment receipt upload failed', [
                        'error' => $e->getMessage(),
                        'training_center_id' => $trainingCenter->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'message' => 'Failed to upload payment receipt: ' . $e->getMessage(),
                        'error' => 'File upload error'
                    ], 500);
                }
            }
            
            // Determine payment type and amounts for credit card
            $paymentType = 'standard';
            $commissionAmount = 0;
            $providerAmount = 0;
            
            if ($request->payment_method === 'credit_card') {
                // Check if destination charge was used (check payment intent metadata)
                try {
                    if ($request->payment_intent_id) {
                        $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
                        if ($paymentIntent && isset($paymentIntent->metadata->payment_type) && $paymentIntent->metadata->payment_type === 'destination_charge') {
                            $paymentType = 'destination_charge';
                            $commissionAmount = $groupCommissionAmount ?? 0;
                            $providerAmount = $finalAmount - ($groupCommissionAmount ?? 0);
                        } else {
                            // Standard payment - commission handled through ledger
                            $commissionAmount = $groupCommissionAmount ?? 0;
                            $providerAmount = $accCommissionAmount ?? 0;
                        }
                    } else {
                        // No payment intent ID provided, use calculated amounts
                        $commissionAmount = $groupCommissionAmount ?? 0;
                        $providerAmount = $accCommissionAmount ?? 0;
                    }
                } catch (\Exception $e) {
                    // If can't retrieve payment intent, use calculated amounts
                    \Log::warning('Failed to retrieve payment intent, using calculated amounts', [
                        'error' => $e->getMessage(),
                        'payment_intent_id' => $request->payment_intent_id
                    ]);
                    $commissionAmount = $groupCommissionAmount ?? 0;
                    $providerAmount = $accCommissionAmount ?? 0;
                }
            } else {
                // Manual payment - amounts will be set after verification
                $commissionAmount = $groupCommissionAmount ?? 0;
                $providerAmount = $accCommissionAmount ?? 0;
            }
            
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'code_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $request->acc_id,
                'amount' => $finalAmount,
                'commission_amount' => $commissionAmount ?? 0,
                'provider_amount' => $providerAmount ?? 0,
                'currency' => 'USD',
                'payment_method' => $request->payment_method === 'credit_card' ? 'credit_card' : 'bank_transfer',
                'payment_type' => $paymentType,
                'payment_gateway_transaction_id' => $request->payment_intent_id ?? null,
                'status' => $paymentStatus === 'pending' ? 'pending' : 'completed',
                'completed_at' => $paymentStatus === 'completed' ? now() : null,
                'reference_type' => 'code_batch',
                'reference_id' => null, // Will be updated after batch creation
            ]);

            // Create batch
            $batch = CodeBatch::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $request->acc_id,
                'course_id' => $request->course_id,
                'quantity' => $request->quantity,
                'total_amount' => $finalAmount,
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
                'purchase_date' => now(),
                'payment_status' => $paymentStatus,
                'payment_receipt_url' => $paymentReceiptUrl,
                'payment_amount' => $paymentAmount,
            ]);

            // Only generate codes if payment is completed (not manual/pending)
            $codes = [];
            if ($paymentStatus === 'completed') {
                $maxAttempts = 10; // Maximum attempts to generate unique code
                for ($i = 0; $i < $request->quantity; $i++) {
                    $attempts = 0;
                    $codeCreated = false;
                    
                    while (!$codeCreated && $attempts < $maxAttempts) {
                        try {
                            $generatedCode = strtoupper(Str::random(12));
                            
                            // Check if code already exists
                            $existingCode = CertificateCode::where('code', $generatedCode)->first();
                            if ($existingCode) {
                                $attempts++;
                                continue;
                            }
                            
                            $code = CertificateCode::create([
                                'code' => $generatedCode,
                                'batch_id' => $batch->id,
                                'training_center_id' => $trainingCenter->id,
                                'acc_id' => $request->acc_id,
                                'course_id' => $request->course_id,
                                'purchased_price' => $unitPrice,
                                'discount_applied' => $discountAmount > 0,
                                'discount_code_id' => $discountCodeId,
                                'status' => 'available',
                                'purchased_at' => now(),
                            ]);
                            $codes[] = $code;
                            $codeCreated = true;
                        } catch (\Exception $codeError) {
                            $attempts++;
                            if ($attempts >= $maxAttempts) {
                                throw new \Exception('Failed to generate unique certificate code after ' . $maxAttempts . ' attempts: ' . $codeError->getMessage());
                            }
                        }
                    }
                    
                    if (!$codeCreated) {
                        throw new \Exception('Failed to generate certificate code after ' . $maxAttempts . ' attempts');
                    }
                }

                // Update discount code usage
                if ($discountCodeId) {
                    $discountCode = DiscountCode::find($discountCodeId);
                    if ($discountCode && $discountCode->discount_type === 'quantity_based') {
                        $discountCode->increment('used_quantity', $request->quantity);
                        if ($discountCode->used_quantity >= $discountCode->total_quantity) {
                            $discountCode->update(['status' => 'depleted']);
                        }
                    }
                }
            }

            // Update transaction with batch reference
            $transaction->update(['reference_id' => $batch->id]);

            // Create commission ledger entries for distribution (only if completed)
            if ($paymentStatus === 'completed') {
                \App\Models\CommissionLedger::create([
                    'transaction_id' => $transaction->id,
                    'acc_id' => $request->acc_id,
                    'training_center_id' => $trainingCenter->id,
                    'group_commission_amount' => $groupCommissionAmount ?? 0,
                    'group_commission_percentage' => $groupCommissionPercentage ?? 0,
                    'acc_commission_amount' => $accCommissionAmount ?? 0,
                    'acc_commission_percentage' => $accCommissionPercentage ?? 0,
                    'settlement_status' => 'pending',
                ]);
            }

            DB::commit();

            // Send notifications (wrap in try-catch to prevent notification failures from affecting the purchase)
            $notificationService = new NotificationService();
            
            try {
                if ($paymentStatus === 'completed') {
                // Notify Training Center about success
                $notificationService->notifyCodePurchaseSuccess(
                    $user->id,
                    $batch->id,
                    $request->quantity,
                    $finalAmount
                );
                
                // Notify Admin about code purchase and commission
                $notificationService->notifyAdminCodePurchase(
                    $batch->id,
                    $trainingCenter->name,
                    $request->quantity,
                    $finalAmount,
                    $groupCommissionAmount
                );
                
                // Notify Admin about commission received
                if ($groupCommissionAmount > 0) {
                    $acc = \App\Models\ACC::find($request->acc_id);
                    $notificationService->notifyAdminCommissionReceived(
                        $transaction->id,
                        'code_purchase',
                        $groupCommissionAmount,
                        $finalAmount,
                        $trainingCenter->name,
                        $acc ? $acc->name : null
                    );
                }
                
                // Notify ACC
                $acc = \App\Models\ACC::find($request->acc_id);
                if ($acc) {
                    $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
                    if ($accUser) {
                        $notificationService->notifyAccCodePurchase(
                            $accUser->id,
                            $batch->id,
                            $trainingCenter->name,
                            $request->quantity,
                            $finalAmount,
                            $accCommissionAmount
                        );
                    }
                }
            } else {
                // Manual payment - notify ACC and Admin about pending request
                $acc = \App\Models\ACC::find($request->acc_id);
                if ($acc) {
                    $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
                    if ($accUser) {
                        $notificationService->notifyManualPaymentRequest(
                            $accUser->id,
                            $batch->id,
                            $trainingCenter->name,
                            $request->quantity,
                            $finalAmount
                        );
                    }
                }
                
                // Notify Admin
                $notificationService->notifyAdminManualPaymentRequest(
                    $batch->id,
                    $trainingCenter->name,
                    $request->quantity,
                    $finalAmount
                );
                
                // Notify Training Center that request is pending
                $notificationService->notifyManualPaymentPending(
                    $user->id,
                    $batch->id,
                    $request->quantity,
                    $finalAmount
                );
            }
            } catch (\Exception $notificationError) {
                // Log notification errors but don't fail the purchase
                \Log::error('Notification sending failed after code purchase', [
                    'error' => $notificationError->getMessage(),
                    'batch_id' => $batch->id ?? null,
                    'trace' => $notificationError->getTraceAsString()
                ]);
            }

            // Format response
            $response = [
                'message' => $paymentStatus === 'completed' 
                    ? 'Codes purchased successfully' 
                    : 'Payment request submitted successfully. Waiting for approval.',
                'batch' => [
                    'id' => $batch->id,
                    'training_center_id' => $batch->training_center_id,
                    'acc_id' => $batch->acc_id,
                    'course_id' => (int)$request->course_id,
                    'quantity' => $batch->quantity,
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'discount_amount' => number_format($discountAmount, 2, '.', ''),
                    'final_amount' => number_format($finalAmount, 2, '.', ''),
                    'payment_method' => $batch->payment_method,
                    'payment_status' => $batch->payment_status,
                    'created_at' => $batch->created_at->toIso8601String(),
                ],
            ];
            
            if ($paymentStatus === 'completed') {
                $response['codes'] = array_map(function($code) {
                    return [
                        'id' => $code->id,
                        'code' => $code->code,
                        'status' => $code->status,
                    ];
                }, $codes);
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the full error for debugging
            \Log::error('Code purchase failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'training_center_id' => $trainingCenter->id ?? null,
                'request_data' => [
                    'acc_id' => $request->acc_id ?? null,
                    'course_id' => $request->course_id ?? null,
                    'quantity' => $request->quantity ?? null,
                    'payment_method' => $request->payment_method ?? null,
                ]
            ]);
            
            // Return user-friendly error message
            $errorMessage = 'Purchase failed. Please try again.';
            if (config('app.debug')) {
                $errorMessage .= ' Error: ' . $e->getMessage();
            }
            
            return response()->json([
                'message' => $errorMessage,
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/training-center/codes/inventory",
        summary: "Get certificate codes inventory",
        description: "Get all certificate codes owned by the training center with optional filtering.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["available", "used", "expired"]), example: "available"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Inventory retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "codes", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function inventory(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = CertificateCode::where('training_center_id', $trainingCenter->id)
            ->with('course');

        if ($request->has('acc_id')) {
            $query->where('acc_id', $request->acc_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $codes = $query->get();

        $summary = [
            'total' => CertificateCode::where('training_center_id', $trainingCenter->id)->count(),
            'available' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'available')->count(),
            'used' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'used')->count(),
            'expired' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'expired')->count(),
        ];

        return response()->json([
            'codes' => $codes,
            'summary' => $summary,
        ]);
    }

    #[OA\Get(
        path: "/training-center/codes/batches",
        summary: "Get code purchase batches",
        description: "Get all code purchase batches for the training center.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Batches retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "batches", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function batches(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $batches = CodeBatch::where('training_center_id', $trainingCenter->id)
            ->with('certificateCodes')
            ->orderBy('purchase_date', 'desc')
            ->get();

        return response()->json(['batches' => $batches]);
    }
}

