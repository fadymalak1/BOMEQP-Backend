<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\TrainingCenterWallet;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function addFunds(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:credit_card,bank_transfer',
            'payment_gateway_transaction_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'code_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'group',
                'payee_id' => 1,
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway_transaction_id' => $request->payment_gateway_transaction_id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update or create wallet
            $wallet = TrainingCenterWallet::firstOrCreate(
                ['training_center_id' => $trainingCenter->id],
                ['balance' => 0, 'currency' => 'USD']
            );

            $wallet->increment('balance', $request->amount);
            $wallet->update(['last_updated' => now()]);

            DB::commit();

            return response()->json([
                'message' => 'Funds added successfully',
                'wallet' => $wallet,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add funds: ' . $e->getMessage()], 500);
        }
    }

    public function balance(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $wallet = TrainingCenterWallet::firstOrCreate(
            ['training_center_id' => $trainingCenter->id],
            ['balance' => 0, 'currency' => 'USD', 'last_updated' => now()]
        );

        return response()->json(['wallet' => $wallet]);
    }

    public function transactions(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $transactions = Transaction::where('payer_type', 'training_center')
            ->where('payer_id', $trainingCenter->id)
            ->orWhere(function ($q) use ($trainingCenter) {
                $q->where('payee_type', 'training_center')
                  ->where('payee_id', $trainingCenter->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['transactions' => $transactions]);
    }
}

