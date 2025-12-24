<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\CertificateCode;
use App\Models\CodeBatch;
use App\Models\DiscountCode;
use App\Models\CertificatePricing;
use App\Models\TrainingCenterWallet;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CodeController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create payment intent for code purchase (Stripe)
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'course_id' => 'required|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string',
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

        // Create payment intent
        $metadata = [
            'transaction_type' => 'code_purchase',
            'payer_type' => 'training_center',
            'payer_id' => $trainingCenter->id,
            'payee_type' => 'acc',
            'payee_id' => $request->acc_id,
            'course_id' => $request->course_id,
            'quantity' => $request->quantity,
            'discount_code' => $request->discount_code ?? '',
        ];

        $result = $this->stripeService->createPaymentIntent(
            $finalAmount,
            $pricing->currency ?? 'USD',
            $metadata
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
            'amount' => $finalAmount,
            'currency' => $pricing->currency ?? 'USD',
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'final_amount' => number_format($finalAmount, 2, '.', ''),
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'quantity' => $request->quantity,
        ], 200);
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'course_id' => 'required|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string',
            'payment_method' => 'required|in:wallet,credit_card',
            'payment_intent_id' => 'nullable|string', // For Stripe credit card payments
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

        // Check wallet balance before starting transaction
        if ($request->payment_method === 'wallet') {
            $wallet = TrainingCenterWallet::firstOrCreate(
                ['training_center_id' => $trainingCenter->id],
                ['balance' => 0, 'currency' => 'USD']
            );

            if ($wallet->balance < $finalAmount) {
                return response()->json([
                    'message' => 'Insufficient wallet balance'
                ], 402); // Payment Required
            }
        } elseif ($request->payment_method === 'credit_card') {
            // Validate payment_intent_id for credit card payments
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            // Verify payment intent with Stripe
            if (!$this->stripeService->isConfigured()) {
                return response()->json([
                    'message' => 'Stripe payment is not configured'
                ], 400);
            }

            $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
            
            if (!$paymentIntent) {
                return response()->json([
                    'message' => 'Invalid payment intent'
                ], 400);
            }

            // Verify payment intent status
            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'message' => 'Payment intent is not completed. Status: ' . $paymentIntent->status
                ], 400);
            }

            // Verify payment intent amount matches
            $paymentAmount = $paymentIntent->amount / 100; // Convert from cents
            if (abs($paymentAmount - $finalAmount) > 0.01) { // Allow small floating point differences
                return response()->json([
                    'message' => 'Payment amount mismatch. Expected: ' . $finalAmount . ', Received: ' . $paymentAmount
                ], 400);
            }

            // Verify metadata matches
            $metadata = $paymentIntent->metadata->toArray();
            if (($metadata['payer_id'] ?? null) != $trainingCenter->id ||
                ($metadata['payee_id'] ?? null) != $request->acc_id ||
                ($metadata['course_id'] ?? null) != $request->course_id ||
                ($metadata['quantity'] ?? null) != $request->quantity) {
                return response()->json([
                    'message' => 'Payment intent metadata does not match purchase details'
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Process payment
            if ($request->payment_method === 'wallet') {
                $wallet = TrainingCenterWallet::findOrFail($wallet->id);
                $wallet->decrement('balance', $finalAmount);
                $wallet->update(['last_updated' => now()]);
            }

            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'code_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $request->acc_id,
                'amount' => $finalAmount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
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

