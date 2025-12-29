<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{

    /**
     * Get all transactions for Training Center
     * 
     * Returns comprehensive transaction details including payer, payee, commission ledger, and reference information.
     * 
     * @group Training Center Financial
     * @authenticated
     * 
     * @queryParam type string Filter by transaction type (subscription, code_purchase, material_purchase, course_purchase, commission, settlement). Example: code_purchase
     * @queryParam status string Filter by status (pending, completed, failed, refunded). Example: completed
     * @queryParam date_from date Filter transactions from date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter transactions to date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam per_page integer Items per page. Example: 15
     * @queryParam page integer Page number. Example: 1
     * 
     * @response 200 {
     *   "data": [...],
     *   "summary": {
     *     "total_transactions": 30,
     *     "total_spent": 15000.00,
     *     "total_received": 2000.00,
     *     "completed_amount": 12000.00,
     *     "pending_amount": 3000.00
     *   }
     * }
     */
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

        // Get summary statistics
        $summaryQuery = clone $query;
        $summary = [
            'total_transactions' => $summaryQuery->count(),
            'total_spent' => round($summaryQuery->where('payer_type', 'training_center')->where('payer_id', $trainingCenter->id)->sum('amount'), 2),
            'total_received' => round($summaryQuery->where('payee_type', 'training_center')->where('payee_id', $trainingCenter->id)->sum('amount'), 2),
            'completed_amount' => round($summaryQuery->where('status', 'completed')->sum('amount'), 2),
            'pending_amount' => round($summaryQuery->where('status', 'pending')->sum('amount'), 2),
        ];

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        // Format transactions with detailed information
        $formattedTransactions = $transactions->getCollection()->map(function ($transaction) {
            return $this->formatTransaction($transaction);
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
        $commissionLedgers = $transaction->commissionLedgers->map(function ($ledger) {
            return [
                'id' => $ledger->id,
                'acc' => $ledger->acc ? [
                    'id' => $ledger->acc->id,
                    'name' => $ledger->acc->name,
                ] : null,
                'training_center' => $ledger->trainingCenter ? [
                    'id' => $ledger->trainingCenter->id,
                    'name' => $ledger->trainingCenter->name,
                ] : null,
                'instructor' => $ledger->instructor ? [
                    'id' => $ledger->instructor->id,
                    'name' => ($ledger->instructor->first_name ?? '') . ' ' . ($ledger->instructor->last_name ?? ''),
                ] : null,
                'group_commission_amount' => $ledger->group_commission_amount,
                'group_commission_percentage' => $ledger->group_commission_percentage,
                'acc_commission_amount' => $ledger->acc_commission_amount,
                'acc_commission_percentage' => $ledger->acc_commission_percentage,
                'settlement_status' => $ledger->settlement_status,
                'settlement_date' => $ledger->settlement_date,
            ];
        });

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
        switch ($type) {
            case 'ACCSubscription':
                return [
                    'acc_id' => $model->acc_id,
                    'plan' => $model->plan,
                    'start_date' => $model->start_date,
                    'end_date' => $model->end_date,
                    'status' => $model->status,
                ];
            case 'CodeBatch':
                return [
                    'training_center_id' => $model->training_center_id,
                    'acc_id' => $model->acc_id,
                    'quantity' => $model->quantity,
                    'total_amount' => $model->total_amount,
                ];
            case 'TrainingCenterPurchase':
                return [
                    'training_center_id' => $model->training_center_id,
                    'acc_id' => $model->acc_id,
                    'purchase_type' => $model->purchase_type,
                    'item_id' => $model->item_id,
                    'amount' => $model->amount,
                ];
            case 'MonthlySettlement':
                return [
                    'acc_id' => $model->acc_id,
                    'settlement_month' => $model->settlement_month,
                    'total_revenue' => $model->total_revenue,
                    'group_commission_amount' => $model->group_commission_amount,
                    'status' => $model->status,
                ];
            default:
                return [];
        }
    }
}

