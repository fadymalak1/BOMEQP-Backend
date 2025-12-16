<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\User;
use Illuminate\Http\Request;

class ACCController extends Controller
{
    public function applications()
    {
        $applications = ACC::where('status', 'pending')->with('documents')->get();
        return response()->json(['applications' => $applications]);
    }

    public function showApplication($id)
    {
        $application = ACC::with('documents')->findOrFail($id);
        return response()->json(['application' => $application]);
    }

    public function approve(Request $request, $id)
    {
        $acc = ACC::findOrFail($id);
        $acc->update([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        // Also activate the user account associated with this ACC
        $user = User::where('email', $acc->email)->first();
        if ($user && $user->role === 'acc_admin') {
            $user->update(['status' => 'active']);
        }

        return response()->json(['message' => 'ACC application approved', 'acc' => $acc]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $acc = ACC::findOrFail($id);
        $acc->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'ACC application rejected',
            'acc' => $acc->fresh(),
        ]);
    }

    public function createSpace($id)
    {
        $acc = ACC::findOrFail($id);
        // Implementation for creating ACC space
        return response()->json(['message' => 'ACC space created successfully']);
    }

    public function generateCredentials($id)
    {
        $acc = ACC::findOrFail($id);
        // Implementation for generating credentials
        return response()->json(['message' => 'Credentials generated successfully']);
    }

    public function index()
    {
        $accs = ACC::with('subscriptions')->get();
        return response()->json(['accs' => $accs]);
    }

    public function show($id)
    {
        $acc = ACC::with('subscriptions', 'documents')->findOrFail($id);
        return response()->json(['acc' => $acc]);
    }

    public function setCommissionPercentage(Request $request, $id)
    {
        $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $acc = ACC::findOrFail($id);
        $acc->update([
            'commission_percentage' => $request->commission_percentage,
        ]);

        return response()->json([
            'message' => 'Commission percentage set successfully',
            'acc' => $acc->fresh(),
        ]);
    }

    public function transactions($id)
    {
        $acc = ACC::findOrFail($id);
        // Implementation for getting transactions
        return response()->json(['transactions' => []]);
    }
}

