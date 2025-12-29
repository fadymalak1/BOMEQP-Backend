<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\MonthlySettlement;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    public function dashboard()
    {
        $totalRevenue = Transaction::where('payee_type', 'group')
            ->where('status', 'completed')
            ->sum('amount');

        $pendingSettlements = MonthlySettlement::where('status', 'pending')
            ->sum('group_commission_amount');

        $thisMonthRevenue = Transaction::where('payee_type', 'group')
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        $activeAccs = \App\Models\ACC::where('status', 'active')->count();

        return response()->json([
            'total_revenue' => $totalRevenue,
            'pending_settlements' => $pendingSettlements,
            'this_month_revenue' => $thisMonthRevenue,
            'active_accs' => $activeAccs,
        ]);
    }

    /**
     * Get all transactions for Group Admin
     * 
     * Returns comprehensive transaction details including payer, payee, commission ledger, and reference information.
     * 
     * @group Group Admin Financial
     * @authenticated
     * 
     * @queryParam type string Filter by transaction type (subscription, code_purchase, material_purchase, course_purchase, commission, settlement). Example: subscription
     * @queryParam status string Filter by status (pending, completed, failed, refunded). Example: completed
     * @queryParam payer_type string Filter by payer type (acc, training_center, group). Example: acc
     * @queryParam payee_type string Filter by payee type (group, acc, instructor). Example: group
     * @queryParam date_from date Filter transactions from date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter transactions to date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam per_page integer Items per page. Example: 15
     * @queryParam page integer Page number. Example: 1
     * 
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "transaction_type": "subscription",
     *       "payer": {
     *         "id": 1,
     *         "name": "ABC Accreditation Body",
     *         "email": "info@abc.com",
     *         "type": "acc"
     *       },
     *       "payee": {
     *         "id": 1,
     *         "name": "BOMEQP Group",
     *         "type": "group"
     *       },
     *       "amount": 5000.00,
     *       "currency": "USD",
     *       "payment_method": "credit_card",
     *       "payment_gateway_transaction_id": "pi_1234567890",
     *       "status": "completed",
     *       "description": "ACC Subscription Payment",
     *       "reference": {
     *         "id": 1,
     *         "type": "ACCSubscription",
     *         "details": {}
     *       },
     *       "commission_ledgers": [],
     *       "completed_at": "2024-01-15T10:30:00.000000Z",
     *       "created_at": "2024-01-15T10:30:00.000000Z",
     *       "updated_at": "2024-01-15T10:30:00.000000Z"
     *     }
     *   ],
     *   "summary": {
     *     "total_transactions": 100,
     *     "total_amount": 50000.00,
     *     "completed_amount": 45000.00,
     *     "pending_amount": 5000.00
     *   }
     * }
     */
    public function transactions(Request $request)
    {
        $query = Transaction::where(function ($q) {
            $q->where('payee_type', 'group')
              ->orWhere('payer_type', 'group');
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

        if ($request->has('payer_type')) {
            $query->where('payer_type', $request->payer_type);
        }

        if ($request->has('payee_type')) {
            $query->where('payee_type', $request->payee_type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get summary statistics
        $summaryQuery = clone $query;
        $summary = [
            'total_transactions' => $summaryQuery->count(),
            'total_amount' => round($summaryQuery->sum('amount'), 2),
            'completed_amount' => round($summaryQuery->where('status', 'completed')->sum('amount'), 2),
            'pending_amount' => round($summaryQuery->where('status', 'pending')->sum('amount'), 2),
        ];

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

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
                default:
                    return [];
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    public function settlements(Request $request)
    {
        $settlements = MonthlySettlement::with('acc')
            ->orderBy('settlement_month', 'desc')
            ->get();

        return response()->json(['settlements' => $settlements]);
    }

    public function requestPayment(Request $request, $id)
    {
        $settlement = MonthlySettlement::findOrFail($id);
        
        $settlement->update([
            'status' => 'requested',
            'request_date' => now(),
        ]);

        // TODO: Send notification/email to ACC

        return response()->json(['message' => 'Payment request sent successfully']);
    }
}

