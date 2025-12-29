<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\ACCMaterial;
use App\Models\TrainingCenterPurchase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    public function materials(Request $request)
    {
        $query = ACCMaterial::where('status', 'active')
            ->with('acc');

        if ($request->has('acc_id')) {
            $query->where('acc_id', $request->acc_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('material_type')) {
            $query->where('material_type', $request->material_type);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $materials = $query->get();
        return response()->json(['materials' => $materials]);
    }

    public function showMaterial($id)
    {
        $material = ACCMaterial::with('acc', 'course')->findOrFail($id);
        return response()->json(['material' => $material]);
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'purchase_type' => 'required|in:material,course,package',
            'item_id' => 'required|integer',
            'acc_id' => 'required|exists:accs,id',
            'payment_method' => 'required|in:credit_card',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Get item based on purchase type
        if ($request->purchase_type === 'material') {
            $item = ACCMaterial::findOrFail($request->item_id);
            $amount = $item->price;
        } else {
            return response()->json(['message' => 'Purchase type not implemented yet'], 400);
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'material_purchase',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $request->acc_id,
                'amount' => $amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Create purchase record
            $purchase = TrainingCenterPurchase::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $request->acc_id,
                'purchase_type' => $request->purchase_type,
                'item_id' => $request->item_id,
                'amount' => $amount,
                'group_commission_percentage' => 0, // TODO: Calculate based on referral
                'group_commission_amount' => 0,
                'transaction_id' => $transaction->id,
                'purchased_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Purchase successful',
                'purchase' => $purchase,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Purchase failed: ' . $e->getMessage()], 500);
        }
    }

    public function library(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $purchases = TrainingCenterPurchase::where('training_center_id', $trainingCenter->id)
            ->with(['acc'])
            ->get()
            ->map(function ($purchase) {
                $item = null;
                if ($purchase->purchase_type === 'material') {
                    $item = ACCMaterial::find($purchase->item_id);
                }

                return [
                    'id' => $purchase->id,
                    'name' => $item?->name ?? 'Unknown',
                    'purchase_type' => $purchase->purchase_type,
                    'purchased_at' => $purchase->purchased_at,
                    'file_url' => $item?->file_url,
                ];
            });

        return response()->json(['library' => $purchases]);
    }
}

