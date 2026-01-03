<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\CodeBatch;
use App\Models\CertificateCode;
use App\Models\Transaction;
use App\Models\DiscountCode;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CodeController extends Controller
{
    /**
     * Get pending manual payment requests for this ACC
     */
    #[OA\Get(
        path: "/acc/code-batches/pending-payments",
        summary: "Get pending manual payment requests",
        description: "Get all pending manual payment requests for code purchases from this ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Pending requests retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "batches", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function pendingPayments(Request $request)
    {
        $user = $request->user();
        $acc = \App\Models\ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $batches = CodeBatch::where('acc_id', $acc->id)
            ->where('payment_method', 'manual_payment')
            ->where('payment_status', 'pending')
            ->with(['trainingCenter', 'certificateCodes'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'batches' => $batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'training_center' => [
                        'id' => $batch->trainingCenter->id,
                        'name' => $batch->trainingCenter->name,
                        'email' => $batch->trainingCenter->email,
                    ],
                    'quantity' => $batch->quantity,
                    'total_amount' => $batch->total_amount,
                    'payment_amount' => $batch->payment_amount,
                    'payment_receipt_url' => $batch->payment_receipt_url,
                    'payment_status' => $batch->payment_status,
                    'created_at' => $batch->created_at,
                    'updated_at' => $batch->updated_at,
                ];
            })
        ]);
    }

    /**
     * Approve manual payment request
     */
    #[OA\Put(
        path: "/acc/code-batches/{id}/approve-payment",
        summary: "Approve manual payment request",
        description: "Approve a manual payment request and complete the code purchase.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_amount"],
                properties: [
                    new OA\Property(property: "payment_amount", type: "number", example: 1000.00, description: "The payment amount to verify")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment approved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Payment approved and codes generated successfully"),
                        new OA\Property(property: "batch", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid payment amount or batch already processed"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Not authorized to approve this batch"),
            new OA\Response(response: 404, description: "Batch not found")
        ]
    )]
    public function approvePayment(Request $request, $id)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = \App\Models\ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $batch = CodeBatch::where('id', $id)
            ->where('acc_id', $acc->id)
            ->where('payment_method', 'manual_payment')
            ->where('payment_status', 'pending')
            ->first();

        if (!$batch) {
            return response()->json(['message' => 'Batch not found or not pending'], 404);
        }

        // Verify payment amount matches
        if (abs($request->payment_amount - $batch->total_amount) > 0.01) {
            return response()->json([
                'message' => 'Payment amount does not match the batch total amount',
                'expected_amount' => $batch->total_amount,
                'provided_amount' => $request->payment_amount
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Get course_id from batch
            $courseId = $batch->course_id;
            
            if (!$courseId) {
                return response()->json([
                    'message' => 'Course ID not found in batch'
                ], 400);
            }

            // Generate codes
            $codes = [];
            $unitPrice = $batch->total_amount / $batch->quantity;
            
            for ($i = 0; $i < $batch->quantity; $i++) {
                $code = CertificateCode::create([
                    'code' => strtoupper(Str::random(12)),
                    'batch_id' => $batch->id,
                    'training_center_id' => $batch->training_center_id,
                    'acc_id' => $batch->acc_id,
                    'course_id' => $courseId,
                    'purchased_price' => $unitPrice,
                    'discount_applied' => false,
                    'discount_code_id' => null,
                    'status' => 'available',
                    'purchased_at' => now(),
                ]);
                $codes[] = $code;
            }

            // Update batch status
            $batch->update([
                'payment_status' => 'approved',
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            // Update transaction status
            if ($transaction) {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Create commission ledger entry
                $acc = \App\Models\ACC::find($batch->acc_id);
                $groupCommissionPercentage = $acc->commission_percentage ?? 0;
                $groupCommissionAmount = ($batch->total_amount * $groupCommissionPercentage) / 100;
                $accCommissionAmount = $batch->total_amount - $groupCommissionAmount;

                \App\Models\CommissionLedger::create([
                    'transaction_id' => $transaction->id,
                    'acc_id' => $batch->acc_id,
                    'training_center_id' => $batch->training_center_id,
                    'group_commission_amount' => $groupCommissionAmount,
                    'group_commission_percentage' => $groupCommissionPercentage,
                    'acc_commission_amount' => $accCommissionAmount,
                    'acc_commission_percentage' => 100 - $groupCommissionPercentage,
                    'settlement_status' => 'pending',
                ]);
            }

            DB::commit();

            // Send notifications
            $notificationService = new NotificationService();
            
            // Notify Training Center
            $trainingCenter = $batch->trainingCenter;
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService->notifyManualPaymentApproved(
                    $trainingCenterUser->id,
                    $batch->id,
                    $batch->quantity,
                    $batch->total_amount
                );
            }

            return response()->json([
                'message' => 'Payment approved and codes generated successfully',
                'batch' => [
                    'id' => $batch->id,
                    'payment_status' => $batch->payment_status,
                    'codes_count' => count($codes),
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
                'message' => 'Failed to approve payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject manual payment request
     */
    #[OA\Put(
        path: "/acc/code-batches/{id}/reject-payment",
        summary: "Reject manual payment request",
        description: "Reject a manual payment request.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["rejection_reason"],
                properties: [
                    new OA\Property(property: "rejection_reason", type: "string", example: "Payment receipt is unclear or amount mismatch")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment rejected successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Payment request rejected"),
                        new OA\Property(property: "batch", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Not authorized to reject this batch"),
            new OA\Response(response: 404, description: "Batch not found")
        ]
    )]
    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $acc = \App\Models\ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $batch = CodeBatch::where('id', $id)
            ->where('acc_id', $acc->id)
            ->where('payment_method', 'manual_payment')
            ->where('payment_status', 'pending')
            ->first();

        if (!$batch) {
            return response()->json(['message' => 'Batch not found or not pending'], 404);
        }

        DB::beginTransaction();
        try {
            // Update batch status
            $batch->update([
                'payment_status' => 'rejected',
                'verified_by' => $user->id,
                'verified_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Update transaction status
            $transaction = Transaction::find($batch->transaction_id);
            if ($transaction) {
                $transaction->update([
                    'status' => 'failed',
                ]);
            }

            DB::commit();

            // Send notification to Training Center
            $notificationService = new NotificationService();
            $trainingCenter = $batch->trainingCenter;
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService->notifyManualPaymentRejected(
                    $trainingCenterUser->id,
                    $batch->id,
                    $batch->quantity,
                    $batch->total_amount,
                    $request->rejection_reason
                );
            }

            return response()->json([
                'message' => 'Payment request rejected',
                'batch' => [
                    'id' => $batch->id,
                    'payment_status' => $batch->payment_status,
                    'rejection_reason' => $batch->rejection_reason,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to reject payment: ' . $e->getMessage()
            ], 500);
        }
    }
}

