<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WalletController extends Controller
{

    #[OA\Get(
        path: "/training-center/financial/transactions",
        summary: "Get Training Center transactions",
        description: "Get all transactions for Training Center with comprehensive details including payer, payee, commission ledger, and reference information.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by transaction ID, type, status, description, payer name (ACC/Training Center/Instructor), payee name (ACC/Training Center/Instructor), or payment gateway transaction ID"),
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["subscription", "code_purchase", "material_purchase", "course_purchase", "commission", "settlement", "instructor_authorization"]), example: "instructor_authorization"),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["pending", "completed", "failed", "refunded"]), example: "completed"),
            new OA\Parameter(name: "date_from", in: "query", schema: new OA\Schema(type: "string", format: "date"), example: "2024-01-01"),
            new OA\Parameter(name: "date_to", in: "query", schema: new OA\Schema(type: "string", format: "date"), example: "2024-12-31"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Transactions retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "summary", type: "object"),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 50)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function transactions(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = Transaction::where(function ($q) use ($trainingCenter) {
            $q->where('payer_type', 'training_center')->where('payer_id', $trainingCenter->id)
              ->orWhere('payee_type', 'training_center')->where('payee_id', $trainingCenter->id);
        })
        ->with(['commissionLedgers.acc', 'commissionLedgers.trainingCenter', 'commissionLedgers.instructor'])
        ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            
            // Get matching IDs for each entity type
            $matchingAccIds = \App\Models\ACC::where('name', 'like', "%{$searchTerm}%")
                ->orWhere('email', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->toArray();
            
            $matchingTrainingCenterIds = \App\Models\TrainingCenter::where('name', 'like', "%{$searchTerm}%")
                ->orWhere('email', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->toArray();
            
            $matchingInstructorIds = \App\Models\Instructor::where('first_name', 'like', "%{$searchTerm}%")
                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$searchTerm}%"])
                ->orWhere('email', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->toArray();
            
            $query->where(function ($q) use ($searchTerm, $matchingAccIds, $matchingTrainingCenterIds, $matchingInstructorIds) {
                $q->where('id', 'like', "%{$searchTerm}%")
                    ->orWhere('transaction_type', 'like', "%{$searchTerm}%")
                    ->orWhere('status', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('payment_gateway_transaction_id', 'like', "%{$searchTerm}%")
                    ->orWhere('amount', 'like', "%{$searchTerm}%")
                    ->orWhere('currency', 'like', "%{$searchTerm}%")
                    // Search in payer
                    ->orWhere(function ($payerQuery) use ($matchingAccIds, $matchingTrainingCenterIds, $matchingInstructorIds) {
                        $payerQuery->where(function ($q) use ($matchingAccIds, $matchingTrainingCenterIds, $matchingInstructorIds) {
                            if (!empty($matchingAccIds)) {
                                $q->where('payer_type', 'acc')->whereIn('payer_id', $matchingAccIds);
                            }
                            if (!empty($matchingTrainingCenterIds)) {
                                $q->orWhere(function ($subQ) use ($matchingTrainingCenterIds) {
                                    $subQ->where('payer_type', 'training_center')->whereIn('payer_id', $matchingTrainingCenterIds);
                                });
                            }
                            if (!empty($matchingInstructorIds)) {
                                $q->orWhere(function ($subQ) use ($matchingInstructorIds) {
                                    $subQ->where('payer_type', 'instructor')->whereIn('payer_id', $matchingInstructorIds);
                                });
                            }
                        });
                    })
                    // Search in payee
                    ->orWhere(function ($payeeQuery) use ($matchingAccIds, $matchingTrainingCenterIds, $matchingInstructorIds) {
                        $payeeQuery->where(function ($q) use ($matchingAccIds, $matchingTrainingCenterIds, $matchingInstructorIds) {
                            if (!empty($matchingAccIds)) {
                                $q->where('payee_type', 'acc')->whereIn('payee_id', $matchingAccIds);
                            }
                            if (!empty($matchingTrainingCenterIds)) {
                                $q->orWhere(function ($subQ) use ($matchingTrainingCenterIds) {
                                    $subQ->where('payee_type', 'training_center')->whereIn('payee_id', $matchingTrainingCenterIds);
                                });
                            }
                            if (!empty($matchingInstructorIds)) {
                                $q->orWhere(function ($subQ) use ($matchingInstructorIds) {
                                    $subQ->where('payee_type', 'instructor')->whereIn('payee_id', $matchingInstructorIds);
                                });
                            }
                        });
                    });
            });
        }

        // Get summary statistics (before search filter for accurate totals)
        $summaryBaseQuery = Transaction::where(function ($q) use ($trainingCenter) {
            $q->where('payer_type', 'training_center')->where('payer_id', $trainingCenter->id)
              ->orWhere('payee_type', 'training_center')->where('payee_id', $trainingCenter->id);
        });
        
        // Apply same filters to summary (except search)
        if ($request->has('type')) {
            $summaryBaseQuery->where('transaction_type', $request->type);
        }
        if ($request->has('status')) {
            $summaryBaseQuery->where('status', $request->status);
        }
        if ($request->has('date_from')) {
            $summaryBaseQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $summaryBaseQuery->whereDate('created_at', '<=', $request->date_to);
        }
        
        // Get summary statistics
        $summaryQuery = clone $summaryBaseQuery;
        $summary = [
            'total_transactions' => $summaryQuery->count(),
            'total_spent' => round($summaryQuery->where('payer_type', 'training_center')->where('payer_id', $trainingCenter->id)->sum('amount'), 2),
            'total_received' => round($summaryQuery->where('payee_type', 'training_center')->where('payee_id', $trainingCenter->id)->sum('amount'), 2),
            'completed_amount' => round($summaryQuery->where('status', 'completed')->sum('amount'), 2),
            'pending_amount' => round($summaryQuery->where('status', 'pending')->sum('amount'), 2),
        ];

        $perPage = $request->get('per_page', 15);
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Format transactions with detailed information
        $formattedTransactions = $transactions->getCollection()->map(function ($transaction) {
            try {
                return $this->formatTransaction($transaction);
            } catch (\Exception $e) {
                \Log::error('Error formatting transaction: ' . $e->getMessage(), [
                    'transaction_id' => $transaction->id ?? null,
                    'trace' => $e->getTraceAsString()
                ]);
                // Return basic transaction data if formatting fails
                return [
                    'id' => $transaction->id ?? null,
                    'transaction_type' => $transaction->transaction_type ?? null,
                    'amount' => $transaction->amount ?? 0,
                    'currency' => $transaction->currency ?? 'USD',
                    'status' => $transaction->status ?? null,
                    'error' => 'Failed to format transaction details',
                ];
            }
        });

        return response()->json([
            'data' => $formattedTransactions,
            'summary' => $summary,
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
        ]);
    }

    /**
     * Format transaction with all details
     */
    private function formatTransaction($transaction)
    {
        // Get payer details
        $payer = null;
        if ($transaction->payer_type && $transaction->payer_id) {
            $payerModel = $this->getModelByType($transaction->payer_type, $transaction->payer_id);
            $payer = $payerModel ? [
                'id' => $payerModel->id,
                'name' => $payerModel->name ?? ($payerModel->first_name ?? '') . ' ' . ($payerModel->last_name ?? ''),
                'email' => $payerModel->email ?? null,
                'type' => $transaction->payer_type,
            ] : null;
        }

        // Get payee details
        $payee = null;
        if ($transaction->payee_type && $transaction->payee_id) {
            $payeeModel = $this->getModelByType($transaction->payee_type, $transaction->payee_id);
            $payee = $payeeModel ? [
                'id' => $payeeModel->id,
                'name' => $payeeModel->name ?? ($payeeModel->first_name ?? '') . ' ' . ($payeeModel->last_name ?? ''),
                'email' => $payeeModel->email ?? null,
                'type' => $transaction->payee_type,
            ] : null;
        }

        // Get reference details
        $reference = null;
        if ($transaction->reference_type && $transaction->reference_id) {
            $referenceModel = $this->getReferenceModel($transaction->reference_type, $transaction->reference_id);
            $reference = $referenceModel ? [
                'id' => $referenceModel->id,
                'type' => $transaction->reference_type,
                'details' => $this->getReferenceDetails($transaction->reference_type, $referenceModel),
            ] : null;
        }

        // Format commission ledgers
        $commissionLedgers = [];
        if ($transaction->commissionLedgers && $transaction->commissionLedgers->count() > 0) {
            $commissionLedgers = $transaction->commissionLedgers->map(function ($ledger) {
                return [
                    'id' => $ledger->id ?? null,
                    'acc' => ($ledger->acc ?? null) ? [
                        'id' => $ledger->acc->id ?? null,
                        'name' => $ledger->acc->name ?? null,
                    ] : null,
                    'training_center' => ($ledger->trainingCenter ?? null) ? [
                        'id' => $ledger->trainingCenter->id ?? null,
                        'name' => $ledger->trainingCenter->name ?? null,
                    ] : null,
                    'instructor' => ($ledger->instructor ?? null) ? [
                        'id' => $ledger->instructor->id ?? null,
                        'name' => trim(($ledger->instructor->first_name ?? '') . ' ' . ($ledger->instructor->last_name ?? '')),
                    ] : null,
                    'group_commission_amount' => $ledger->group_commission_amount ?? 0,
                    'group_commission_percentage' => $ledger->group_commission_percentage ?? 0,
                    'acc_commission_amount' => $ledger->acc_commission_amount ?? 0,
                    'acc_commission_percentage' => $ledger->acc_commission_percentage ?? 0,
                    'settlement_status' => $ledger->settlement_status ?? null,
                    'settlement_date' => $ledger->settlement_date ?? null,
                ];
            })->toArray();
        }

        return [
            'id' => $transaction->id,
            'transaction_type' => $transaction->transaction_type,
            'payer' => $payer,
            'payee' => $payee,
            'amount' => round($transaction->amount, 2),
            'currency' => $transaction->currency,
            'payment_method' => $transaction->payment_method,
            'payment_gateway_transaction_id' => $transaction->payment_gateway_transaction_id,
            'status' => $transaction->status,
            'description' => $transaction->description,
            'reference' => $reference,
            'commission_ledgers' => $commissionLedgers,
            'completed_at' => $transaction->completed_at,
            'created_at' => $transaction->created_at,
            'updated_at' => $transaction->updated_at,
        ];
    }

    /**
     * Get model by type and ID
     */
    private function getModelByType($type, $id)
    {
        switch ($type) {
            case 'acc':
                return \App\Models\ACC::find($id);
            case 'training_center':
                return \App\Models\TrainingCenter::find($id);
            case 'group':
                return (object)['id' => 1, 'name' => 'BOMEQP Group', 'type' => 'group'];
            case 'instructor':
                return \App\Models\Instructor::find($id);
            default:
                return null;
        }
    }

    /**
     * Get reference model
     */
    private function getReferenceModel($type, $id)
    {
        switch ($type) {
            case 'ACCSubscription':
                return \App\Models\ACCSubscription::find($id);
            case 'CodeBatch':
                return \App\Models\CodeBatch::find($id);
            case 'TrainingCenterPurchase':
                return \App\Models\TrainingCenterPurchase::find($id);
            case 'MonthlySettlement':
                return \App\Models\MonthlySettlement::find($id);
            case 'InstructorAccAuthorization':
                return \App\Models\InstructorAccAuthorization::find($id);
            default:
                return null;
        }
    }

    /**
     * Get reference details
     */
    private function getReferenceDetails($type, $model)
    {
        if (!$model) {
            return [];
        }

        try {
            switch ($type) {
                case 'ACCSubscription':
                    return [
                        'acc_id' => $model->acc_id ?? null,
                        'plan' => $model->plan ?? null,
                        'start_date' => $model->subscription_start_date ?? $model->start_date ?? null,
                        'end_date' => $model->subscription_end_date ?? $model->end_date ?? null,
                        'status' => $model->payment_status ?? $model->status ?? null,
                    ];
                case 'CodeBatch':
                    return [
                        'training_center_id' => $model->training_center_id ?? null,
                        'acc_id' => $model->acc_id ?? null,
                        'quantity' => $model->quantity ?? null,
                        'total_amount' => $model->total_amount ?? null,
                    ];
                case 'TrainingCenterPurchase':
                    return [
                        'training_center_id' => $model->training_center_id ?? null,
                        'acc_id' => $model->acc_id ?? null,
                        'purchase_type' => $model->purchase_type ?? null,
                        'item_id' => $model->item_id ?? null,
                        'amount' => $model->amount ?? null,
                    ];
                case 'MonthlySettlement':
                    return [
                        'acc_id' => $model->acc_id ?? null,
                        'settlement_month' => $model->settlement_month ?? null,
                        'total_revenue' => $model->total_revenue ?? null,
                        'group_commission_amount' => $model->group_commission_amount ?? null,
                        'status' => $model->status ?? null,
                    ];
                case 'InstructorAccAuthorization':
                    $instructor = $model->instructor ?? null;
                    $acc = $model->acc ?? null;
                    return [
                        'instructor_id' => $model->instructor_id ?? null,
                        'instructor_name' => $instructor ? trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')) : null,
                        'acc_id' => $model->acc_id ?? null,
                        'acc_name' => $acc->name ?? null,
                        'training_center_id' => $model->training_center_id ?? null,
                        'authorization_price' => $model->authorization_price ?? null,
                        'status' => $model->status ?? null,
                        'payment_status' => $model->payment_status ?? null,
                    ];
                default:
                    return [];
            }
        } catch (\Exception $e) {
            return [];
        }
    }
}

