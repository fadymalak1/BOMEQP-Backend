<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Transaction;
use App\Models\MonthlySettlement;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    public function transactions(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $query = Transaction::where(function ($q) use ($acc) {
            $q->where('payee_type', 'acc')->where('payee_id', $acc->id)
              ->orWhere('payer_type', 'acc')->where('payer_id', $acc->id);
        })->orderBy('created_at', 'desc');

        $transactions = $query->get();
        return response()->json(['transactions' => $transactions]);
    }

    public function settlements(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $settlements = MonthlySettlement::where('acc_id', $acc->id)
            ->orderBy('settlement_month', 'desc')
            ->get();

        return response()->json(['settlements' => $settlements]);
    }
}

