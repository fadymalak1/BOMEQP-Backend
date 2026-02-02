<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\Certificate;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function revenue(Request $request)
    {
        $query = Transaction::where('payee_type', 'group')
            ->where('status', 'completed');

        if ($request->has('start_date')) {
            $query->whereDate('completed_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('completed_at', '<=', $request->end_date);
        }

        $revenue = $query->sum('amount');
        $transactions = $query->get();

        return response()->json([
            'total_revenue' => $revenue,
            'transaction_count' => $transactions->count(),
            'transactions' => $transactions,
        ]);
    }

    public function accs()
    {
        $accs = ACC::with(['subscriptions', 'courses'])
            ->get()
            ->map(function ($acc) {
                return [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'status' => $acc->status,
                    'subscription_status' => $acc->subscriptions()->latest()->first()?->payment_status,
                    'total_courses' => $acc->courses()->count(),
                    'created_at' => $acc->created_at,
                ];
            });

        return response()->json(['accs' => $accs]);
    }

    public function trainingCenters()
    {
        $trainingCenters = TrainingCenter::with('authorizations')
            ->get()
            ->map(function ($tc) {
                return [
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'status' => $tc->status,
                    'authorized_accs' => $tc->authorizations()->where('status', 'approved')->count(),
                    'created_at' => $tc->created_at,
                ];
            });

        return response()->json(['training_centers' => $trainingCenters]);
    }

    public function certificates(Request $request)
    {
        $query = Certificate::with(['course', 'trainingCenter']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('issue_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('issue_date', '<=', $request->end_date);
        }

        $total = $query->count();
        $certificates = $query->get();

        return response()->json([
            'total' => $total,
            'certificates' => $certificates,
        ]);
    }
}

