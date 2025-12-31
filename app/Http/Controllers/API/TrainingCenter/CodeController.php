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
        description: "Create a Stripe payment intent for purchasing certificate codes. Calculates pricing including discounts.",
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
                        new OA\Property(property: "total_price", type: "number", example: 1000.00),
                        new OA\Property(property: "discount_amount", type: "number", nullable: true, example: 100.00)
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
            'payment_method' => 'required|in:credit_card',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string|max:255',
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
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
        }

        DB::beginTransaction();
        try {
            // Determine payment type and amounts
            $paymentType = 'standard';
            $commissionAmount = null;
            $providerAmount = null;
            
            // Check if destination charge was used (check payment intent metadata)
            try {
                $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
                if ($paymentIntent && isset($paymentIntent->metadata->payment_type) && $paymentIntent->metadata->payment_type === 'destination_charge') {
                    $paymentType = 'destination_charge';
                    $commissionAmount = $groupCommissionAmount;
                    $providerAmount = $finalAmount - $groupCommissionAmount;
                } else {
                    // Standard payment - commission handled through ledger
                    $commissionAmount = $groupCommissionAmount;
                    $providerAmount = $accCommissionAmount;
                }
            } catch (\Exception $e) {
                // If can't retrieve payment intent, use calculated amounts
                $commissionAmount = $groupCommissionAmount;
                $providerAmount = $accCommissionAmount;
            }
            
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'code_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $request->acc_id,
                'amount' => $finalAmount,
                'commission_amount' => $commissionAmount,
                'provider_amount' => $providerAmount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_type' => $paymentType,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
                'reference_type' => 'code_batch',
                'reference_id' => null, // Will be updated after batch creation
            ]);

            // Create batch
            $batch = CodeBatch::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $request->acc_id,
                'quantity' => $request->quantity,
                'total_amount' => $finalAmount,
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
                'purchase_date' => now(),
            ]);

            // Generate codes
            $codes = [];
            for ($i = 0; $i < $request->quantity; $i++) {
                $code = CertificateCode::create([
                    'code' => strtoupper(Str::random(12)),
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
            }

            // Update transaction with batch reference
            $transaction->update(['reference_id' => $batch->id]);

            // Create commission ledger entries for distribution
            \App\Models\CommissionLedger::create([
                'transaction_id' => $transaction->id,
                'acc_id' => $request->acc_id,
                'training_center_id' => $trainingCenter->id,
                'group_commission_amount' => $groupCommissionAmount,
                'group_commission_percentage' => $groupCommissionPercentage,
                'acc_commission_amount' => $accCommissionAmount,
                'acc_commission_percentage' => $accCommissionPercentage,
                'settlement_status' => 'pending',
            ]);

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

            DB::commit();

            // Send notifications
            $notificationService = new NotificationService();
            
            // Notify Training Center
            $notificationService->notifyCodePurchaseSuccess(
                $user->id,
                $batch->id,
                $request->quantity,
                $finalAmount
            );
            
            // Notify Admin
            $notificationService->notifyAdminCodePurchase(
                $batch->id,
                $trainingCenter->name,
                $request->quantity,
                $finalAmount
            );
            
            // Notify ACC (about commission)
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

            // Format response to match requirements
            return response()->json([
                'message' => 'Codes purchased successfully',
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
                    'payment_status' => 'completed',
                    'created_at' => $batch->created_at->toIso8601String(),
                ],
                'codes' => array_map(function($code) {
                    return [
                        'id' => $code->id,
                        'code' => $code->code,
                        'status' => $code->status,
                    ];
                }, $codes),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Purchase failed: ' . $e->getMessage()
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

