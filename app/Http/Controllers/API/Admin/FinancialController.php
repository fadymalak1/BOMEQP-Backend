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

    public function transactions(Request $request)
    {
        $query = Transaction::where('payee_type', 'group')
            ->orWhere('payer_type', 'group')
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json($transactions);
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

