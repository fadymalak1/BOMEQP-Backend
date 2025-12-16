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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CodeController extends Controller
{
    public function purchase(Request $request)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'course_id' => 'required|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string',
            'payment_method' => 'required|in:wallet,credit_card',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
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

        // Apply discount if provided
        if ($request->discount_code) {
            $discountCode = DiscountCode::where('code', $request->discount_code)->first();
            if ($discountCode && $discountCode->status === 'active') {
                // Validate discount code (simplified - should use the validate endpoint logic)
                $discountAmount = ($totalAmount * $discountCode->discount_percentage) / 100;
                $totalAmount -= $discountAmount;
                $discountCodeId = $discountCode->id;
            }
        }

        DB::beginTransaction();
        try {
            // Process payment
            if ($request->payment_method === 'wallet') {
                $wallet = TrainingCenterWallet::firstOrCreate(
                    ['training_center_id' => $trainingCenter->id],
                    ['balance' => 0, 'currency' => 'USD']
                );

                if ($wallet->balance < $totalAmount) {
                    return response()->json(['message' => 'Insufficient wallet balance'], 400);
                }

                $wallet->decrement('balance', $totalAmount);
                $wallet->update(['last_updated' => now()]);
            }

            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'code_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $request->acc_id,
                'amount' => $totalAmount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Create batch
            $batch = CodeBatch::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $request->acc_id,
                'quantity' => $request->quantity,
                'total_amount' => $totalAmount,
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

            return response()->json([
                'message' => 'Codes purchased successfully',
                'batch' => $batch->load('certificateCodes'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Purchase failed: ' . $e->getMessage()], 500);
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

