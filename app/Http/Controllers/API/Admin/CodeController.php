<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodeBatch;
use App\Models\CertificateCode;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CodeController extends Controller
{
    /**
     * Get all pending manual payment requests
     */
    #[OA\Get(
        path: "/admin/code-batches/pending-payments",
        summary: "Get all pending manual payment requests",
        description: "Get all pending manual payment requests from all ACCs with pagination and search.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by batch ID, training center name/email, ACC name/email, course name, or amount"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15), example: 15),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Pending requests retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "batches", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 50),
                        new OA\Property(property: "last_page", type: "integer", example: 4)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function pendingPayments(Request $request)
    {
        $query = CodeBatch::where('payment_method', 'manual_payment')
            ->where('payment_status', 'pending')
            ->with(['trainingCenter', 'acc', 'course', 'certificateCodes']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'like', "%{$searchTerm}%")
                    ->orWhere('total_amount', 'like', "%{$searchTerm}%")
                    ->orWhere('payment_amount', 'like', "%{$searchTerm}%")
                    ->orWhere('quantity', 'like', "%{$searchTerm}%")
                    ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                        $tcQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('acc', function ($accQuery) use ($searchTerm) {
                        $accQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('course', function ($courseQuery) use ($searchTerm) {
                        $courseQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('name_ar', 'like', "%{$searchTerm}%")
                            ->orWhere('code', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'batches' => $batches->getCollection()->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'training_center' => [
                        'id' => $batch->trainingCenter->id,
                        'name' => $batch->trainingCenter->name,
                        'email' => $batch->trainingCenter->email,
                    ],
                    'acc' => [
                        'id' => $batch->acc->id,
                        'name' => $batch->acc->name,
                        'email' => $batch->acc->email,
                    ],
                    'course' => $batch->course ? [
                        'id' => $batch->course->id,
                        'name' => $batch->course->name,
                    ] : null,
                    'quantity' => $batch->quantity,
                    'total_amount' => $batch->total_amount,
                    'payment_amount' => $batch->payment_amount,
                    'payment_receipt_url' => $batch->payment_receipt_url,
                    'payment_status' => $batch->payment_status,
                    'created_at' => $batch->created_at,
                    'updated_at' => $batch->updated_at,
                ];
            }),
            'current_page' => $batches->currentPage(),
            'per_page' => $batches->perPage(),
            'total' => $batches->total(),
            'last_page' => $batches->lastPage(),
        ]);
    }

    /**
     * Approve manual payment request
     */
    #[OA\Put(
        path: "/admin/code-batches/{id}/approve-payment",
        summary: "Approve manual payment request",
        description: "Approve a manual payment request and complete the code purchase.",
        tags: ["Admin"],
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
            new OA\Response(response: 404, description: "Batch not found")
        ]
    )]
    public function approvePayment(Request $request, $id)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $batch = CodeBatch::where('id', $id)
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
            $transaction = Transaction::find($batch->transaction_id);
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

            // Notify ACC
            $acc = \App\Models\ACC::find($batch->acc_id);
            if ($acc) {
                $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
                if ($accUser) {
                    $notificationService->notifyAccCodePurchase(
                        $accUser->id,
                        $batch->id,
                        $trainingCenter->name,
                        $batch->quantity,
                        $batch->total_amount,
                        $accCommissionAmount ?? 0
                    );
                }
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
        path: "/admin/code-batches/{id}/reject-payment",
        summary: "Reject manual payment request",
        description: "Reject a manual payment request.",
        tags: ["Admin"],
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
            new OA\Response(response: 404, description: "Batch not found")
        ]
    )]
    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $batch = CodeBatch::where('id', $id)
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

