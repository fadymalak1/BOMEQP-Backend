<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\TrainingCenterWallet;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructors = Instructor::where('training_center_id', $trainingCenter->id)->get();
        return response()->json(['instructors' => $instructors]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:instructors,email',
            'phone' => 'required|string',
            'id_number' => 'required|string|unique:instructors,id_number',
            'cv_url' => 'nullable|string',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::create([
            'training_center_id' => $trainingCenter->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'id_number' => $request->id_number,
            'cv_url' => $request->cv_url,
            'certificates_json' => $request->certificates_json ?? $request->certificates,
            'specializations' => $request->specializations,
            'status' => 'pending',
        ]);

        return response()->json(['instructor' => $instructor], 201);
    }

    public function show($id)
    {
        $instructor = Instructor::with('trainingCenter')->findOrFail($id);
        return response()->json(['instructor' => $instructor]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:instructors,email,' . $id,
            'phone' => 'sometimes|string',
            'id_number' => 'sometimes|string|unique:instructors,id_number,' . $id,
            'cv_url' => 'nullable|string',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'email', 'phone', 'id_number',
            'cv_url', 'specializations'
        ]);
        
        if ($request->has('certificates_json') || $request->has('certificates')) {
            $updateData['certificates_json'] = $request->certificates_json ?? $request->certificates;
        }
        
        $instructor->update($updateData);

        return response()->json(['message' => 'Instructor updated successfully', 'instructor' => $instructor]);
    }

    public function requestAuthorization(Request $request, $id)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
            'documents_json' => 'nullable|array',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $authorization = InstructorAccAuthorization::create([
            'instructor_id' => $instructor->id,
            'acc_id' => $request->acc_id,
            'training_center_id' => $trainingCenter->id,
            'request_date' => now(),
            'status' => 'pending',
            'documents_json' => $request->documents_json ?? $request->documents,
        ]);

        // TODO: Send notification to ACC

        return response()->json([
            'message' => 'Authorization request submitted successfully',
            'authorization' => $authorization,
        ], 201);
    }

    public function destroy($id)
    {
        $instructor = Instructor::findOrFail($id);
        
        // Check if instructor belongs to the training center
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();
        
        if (!$trainingCenter || $instructor->training_center_id !== $trainingCenter->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $instructor->delete();
        
        return response()->json(['message' => 'Instructor deleted successfully']);
    }

    /**
     * Pay for instructor authorization
     * Called after Group Admin sets commission percentage
     */
    public function payAuthorization(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|in:wallet,credit_card',
            'payment_intent_id' => 'nullable|string', // For Stripe
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->findOrFail($id);

        // Verify authorization is ready for payment
        if ($authorization->status !== 'approved') {
            return response()->json([
                'message' => 'Authorization must be approved by ACC Admin first'
            ], 400);
        }

        if ($authorization->group_admin_status !== 'commission_set') {
            return response()->json([
                'message' => 'Group Admin must set commission percentage first'
            ], 400);
        }

        if ($authorization->payment_status === 'paid') {
            return response()->json([
                'message' => 'Authorization already paid'
            ], 400);
        }

        if (!$authorization->authorization_price || $authorization->authorization_price <= 0) {
            return response()->json([
                'message' => 'Authorization price not set'
            ], 400);
        }

        // Check wallet balance before starting transaction
        if ($request->payment_method === 'wallet') {
            $wallet = TrainingCenterWallet::firstOrCreate(
                ['training_center_id' => $trainingCenter->id],
                ['balance' => 0, 'currency' => 'USD']
            );

            if ($wallet->balance < $authorization->authorization_price) {
                return response()->json([
                    'message' => 'Insufficient wallet balance'
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Process payment
            if ($request->payment_method === 'wallet') {
                $wallet = TrainingCenterWallet::findOrFail($wallet->id);
                $wallet->decrement('balance', $authorization->authorization_price);
                $wallet->update(['last_updated' => now()]);
            }

            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'commission',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $authorization->acc_id,
                'amount' => $authorization->authorization_price,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
                'reference_type' => 'instructor_authorization',
                'reference_id' => $authorization->id,
            ]);

            // Update authorization payment status
            $authorization->update([
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_transaction_id' => $transaction->id,
                'group_admin_status' => 'completed',
            ]);

            // Calculate and create commission ledger entries
            // Use commission_percentage from authorization (set by Group Admin)
            $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
            $accCommissionPercentage = 100 - $groupCommissionPercentage;

            $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
            $accCommissionAmount = ($authorization->authorization_price * $accCommissionPercentage) / 100;

            \App\Models\CommissionLedger::create([
                'transaction_id' => $transaction->id,
                'acc_id' => $authorization->acc_id,
                'training_center_id' => $trainingCenter->id,
                'instructor_id' => $authorization->instructor_id,
                'group_commission_amount' => $groupCommissionAmount,
                'group_commission_percentage' => $groupCommissionPercentage,
                'acc_commission_amount' => $accCommissionAmount,
                'acc_commission_percentage' => $accCommissionPercentage,
                'settlement_status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment successful. Instructor is now officially authorized.',
                'authorization' => $authorization->fresh()->load(['instructor', 'acc', 'trainingCenter']),
                'transaction' => $transaction
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Payment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authorization requests with payment status
     * GET /api/training-center/instructors/authorizations
     */
    public function authorizations(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = InstructorAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->with([
                'instructor:id,first_name,last_name',
                'acc:id,name'
            ]);

        // Filter by status if provided
        if ($request->has('status')) {
            $validStatuses = ['pending', 'approved', 'rejected', 'returned'];
            if (in_array($request->status, $validStatuses)) {
                $query->where('status', $request->status);
            }
        }

        // Filter by payment_status if provided
        if ($request->has('payment_status')) {
            $validPaymentStatuses = ['pending', 'paid', 'failed'];
            if (in_array($request->payment_status, $validPaymentStatuses)) {
                $query->where('payment_status', $request->payment_status);
            }
        }

        $authorizations = $query->orderBy('request_date', 'desc')->get();

        return response()->json(['authorizations' => $authorizations]);
    }
}

