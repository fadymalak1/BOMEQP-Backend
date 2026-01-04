<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\MonthlySettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FinancialService
{
    /**
     * Get financial dashboard statistics
     *
     * @return array
     */
    public function getDashboardStats(): array
    {
        // Calculate total revenue: Use commission_amount if available (destination charge)
        $totalRevenue = Transaction::where('status', 'completed')
            ->get()
            ->sum(function ($transaction) {
                // Use commission_amount if available, otherwise sum from commission ledgers
                if ($transaction->commission_amount) {
                    return $transaction->commission_amount;
                }
                // Fallback: sum from commission ledgers
                return $transaction->commissionLedgers->sum('group_commission_amount') ?? 0;
            });

        $pendingSettlements = MonthlySettlement::where('status', 'pending')
            ->sum('group_commission_amount');

        // Calculate this month revenue: Use commission_amount if available
        $thisMonthRevenue = Transaction::where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->get()
            ->sum(function ($transaction) {
                // Use commission_amount if available, otherwise sum from commission ledgers
                if ($transaction->commission_amount) {
                    return $transaction->commission_amount;
                }
                // Fallback: sum from commission ledgers
                return $transaction->commissionLedgers->sum('group_commission_amount') ?? 0;
            });

        $activeAccs = \App\Models\ACC::where('status', 'active')->count();

        return [
            'total_revenue' => $totalRevenue,
            'pending_settlements' => $pendingSettlements,
            'this_month_revenue' => $thisMonthRevenue,
            'active_accs' => $activeAccs,
        ];
    }

    /**
     * Get transactions with filters and summary
     *
     * @param Request $request
     * @return array
     */
    public function getTransactions(Request $request): array
    {
        // Admin should see transactions where:
        // 1. Group is payee (direct payments to group)
        // 2. Group is payer (group paid someone)
        // 3. Commission amount > 0 (admin received commission from transaction)
        // 4. Has commission ledger with group commission
        $query = Transaction::where(function ($q) {
            $q->where('payee_type', 'group')
              ->orWhere('payer_type', 'group')
              ->orWhere(function ($subQ) {
                  $subQ->whereNotNull('commission_amount')
                       ->where('commission_amount', '>', 0);
              })
              ->orWhereHas('commissionLedgers', function ($ledgerQ) {
                  $ledgerQ->where('group_commission_amount', '>', 0);
              });
        })
        ->with(['commissionLedgers.acc', 'commissionLedgers.trainingCenter', 'commissionLedgers.instructor'])
        ->orderBy('created_at', 'desc');

        // Apply filters
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
        $allTransactions = $summaryQuery->get();
        $completedTransactions = $allTransactions->where('status', 'completed');
        $pendingTransactions = $allTransactions->where('status', 'pending');
        
        $summary = [
            'total_transactions' => $allTransactions->count(),
            'total_amount' => round($allTransactions->sum('amount'), 2),
            'total_commission' => round($allTransactions->sum(function ($t) {
                // Use commission_amount if available, otherwise check commission ledgers
                if ($t->commission_amount && $t->commission_amount > 0) {
                    return $t->commission_amount;
                }
                // If payee is group, use amount as commission
                if ($t->payee_type === 'group') {
                    return $t->amount;
                }
                // Check commission ledgers
                return $t->commissionLedgers->sum('group_commission_amount') ?? 0;
            }), 2),
            'completed_amount' => round($completedTransactions->sum('amount'), 2),
            'completed_commission' => round($completedTransactions->sum(function ($t) {
                // Use commission_amount if available, otherwise check commission ledgers
                if ($t->commission_amount && $t->commission_amount > 0) {
                    return $t->commission_amount;
                }
                // If payee is group, use amount as commission
                if ($t->payee_type === 'group') {
                    return $t->amount;
                }
                // Check commission ledgers
                return $t->commissionLedgers->sum('group_commission_amount') ?? 0;
            }), 2),
            'pending_amount' => round($pendingTransactions->sum('amount'), 2),
        ];

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        // Format transactions with detailed information
        $formattedTransactions = $transactions->getCollection()->map(function ($transaction) {
            try {
                return $this->formatTransaction($transaction);
            } catch (\Exception $e) {
                Log::error('Error formatting transaction', [
                    'transaction_id' => $transaction->id ?? null,
                    'error' => $e->getMessage(),
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

        return [
            'data' => $formattedTransactions,
            'summary' => $summary,
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
        ];
    }

    /**
     * Format transaction with all details
     *
     * @param Transaction $transaction
     * @return array
     */
    public function formatTransaction(Transaction $transaction): array
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
            'commission_amount' => $transaction->commission_amount ? round($transaction->commission_amount, 2) : null,
            'provider_amount' => $transaction->provider_amount ? round($transaction->provider_amount, 2) : null,
            'currency' => $transaction->currency,
            'payment_method' => $transaction->payment_method,
            'payment_type' => $transaction->payment_type,
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
     *
     * @param string $type
     * @param int $id
     * @return mixed
     */
    protected function getModelByType(string $type, int $id)
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
     *
     * @param string $type
     * @param int $id
     * @return mixed
     */
    protected function getReferenceModel(string $type, int $id)
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
     *
     * @param string $type
     * @param mixed $model
     * @return array
     */
    protected function getReferenceDetails(string $type, $model): array
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
}

