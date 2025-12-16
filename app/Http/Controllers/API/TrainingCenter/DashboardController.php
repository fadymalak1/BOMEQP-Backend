<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\CertificateCode;
use App\Models\TrainingClass;
use App\Models\TrainingCenterWallet;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Get authorizations
        $authorizations = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->with('acc')
            ->get()
            ->map(function ($auth) {
                return [
                    'acc' => [
                        'name' => $auth->acc->name,
                    ],
                    'status' => $auth->status,
                ];
            });

        // Get code inventory summary
        $codeInventory = [
            'total' => CertificateCode::where('training_center_id', $trainingCenter->id)->count(),
            'used' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'used')->count(),
            'available' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'available')->count(),
        ];

        $activeClasses = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->count();

        $wallet = TrainingCenterWallet::where('training_center_id', $trainingCenter->id)->first();
        $walletBalance = $wallet ? $wallet->balance : 0;

        return response()->json([
            'authorizations' => $authorizations,
            'code_inventory' => $codeInventory,
            'active_classes' => $activeClasses,
            'wallet_balance' => $walletBalance,
        ]);
    }
}

