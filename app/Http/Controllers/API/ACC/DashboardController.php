<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\Transaction;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subscription = $acc->subscriptions()->latest()->first();
        $pendingRequests = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'pending')
            ->count();

        $activeTrainingCenters = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->count();

        $revenueThisMonth = Transaction::where('payee_type', 'acc')
            ->where('payee_id', $acc->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        return response()->json([
            'subscription_status' => $subscription?->payment_status ?? 'pending',
            'subscription_expires' => $subscription?->subscription_end_date,
            'pending_requests' => $pendingRequests,
            'active_training_centers' => $activeTrainingCenters,
            'revenue_this_month' => $revenueThisMonth,
        ]);
    }
}

