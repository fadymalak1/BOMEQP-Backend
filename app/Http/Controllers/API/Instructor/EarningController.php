<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\Transaction;
use App\Models\CommissionLedger;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EarningController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $query = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed');

        if ($request->has('month')) {
            $month = Carbon::createFromFormat('Y-m', $request->month);
            $query->whereMonth('completed_at', $month->month)
                  ->whereYear('completed_at', $month->year);
        }

        if ($request->has('year')) {
            $query->whereYear('completed_at', $request->year);
        }

        $transactions = $query->orderBy('completed_at', 'desc')->get();

        $total = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->sum('amount');

        $thisMonth = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        $pending = CommissionLedger::where('instructor_id', $instructor->id)
            ->where('settlement_status', 'pending')
            ->sum('group_commission_amount'); // TODO: Should be instructor commission

        $paid = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->sum('amount');

        return response()->json([
            'earnings' => [
                'total' => $total,
                'this_month' => $thisMonth,
                'pending' => $pending,
                'paid' => $paid,
            ],
            'transactions' => $transactions,
        ]);
    }
}

