<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\Transaction;
use App\Models\User;
use App\Mail\InstructorCredentialsMail;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
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
            'email' => 'required|email|unique:instructors,email|unique:users,email',
            'phone' => 'required|string',
            'id_number' => 'required|string|unique:instructors,id_number',
            'cv' => 'nullable|file|mimes:pdf|max:10240', // PDF file, max 10MB
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $cvUrl = null;
        if ($request->hasFile('cv')) {
            $cvFile = $request->file('cv');
            $fileName = time() . '_' . $trainingCenter->id . '_' . $cvFile->getClientOriginalName();
            // Store file in public disk
            $cvPath = $cvFile->storeAs('instructors/cv', $fileName, 'public');
            // Generate URL using the API route
            $cvUrl = url('/api/storage/instructors/cv/' . $fileName);
        }

        // Generate a random password for the instructor
        $password = Str::random(12);
        $instructorName = $request->first_name . ' ' . $request->last_name;

        // Create instructor record
        $instructor = Instructor::create([
            'training_center_id' => $trainingCenter->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'id_number' => $request->id_number,
            'cv_url' => $cvUrl,
            'certificates_json' => $request->certificates_json ?? $request->certificates,
            'specializations' => $request->specializations,
            'status' => 'pending',
        ]);

        // Create user account for the instructor
        $user = User::create([
            'name' => $instructorName,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => 'instructor',
            'status' => 'active', // Instructors are active immediately
        ]);

        // Send email with credentials
        try {
            Mail::to($request->email)->send(new InstructorCredentialsMail(
                $request->email,
                $password,
                $instructorName,
                $trainingCenter->name
            ));
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to send instructor credentials email: ' . $e->getMessage());
            // You can optionally return a warning in the response
        }

        return response()->json([
            'message' => 'Instructor created successfully. Credentials have been sent to the instructor\'s email.',
            'instructor' => $instructor,
        ], 201);
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
            'cv' => 'nullable|file|mimes:pdf|max:10240', // PDF file, max 10MB
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'email', 'phone', 'id_number',
            'specializations'
        ]);
        
        // Handle CV file upload
        if ($request->hasFile('cv')) {
            // Delete old CV file if exists
            if ($instructor->cv_url) {
                // Extract filename from URL (format: /api/storage/instructors/cv/{filename} or full URL)
                $urlParts = parse_url($instructor->cv_url);
                $path = ltrim($urlParts['path'] ?? '', '/');
                // Extract filename from path like: api/storage/instructors/cv/filename.pdf
                if (preg_match('#instructors/cv/(.+)$#', $path, $matches)) {
                    $oldFileName = $matches[1];
                    $oldFilePath = 'instructors/cv/' . $oldFileName;
                    if (Storage::disk('public')->exists($oldFilePath)) {
                        Storage::disk('public')->delete($oldFilePath);
                    }
                }
            }

            // Upload new CV file
            $cvFile = $request->file('cv');
            $fileName = time() . '_' . $trainingCenter->id . '_' . $cvFile->getClientOriginalName();
            // Store file in public disk
            $cvPath = $cvFile->storeAs('instructors/cv', $fileName, 'public');
            // Generate URL using the API route
            $updateData['cv_url'] = url('/api/storage/instructors/cv/' . $fileName);
        }
        
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

        // Send notification to ACC admin
        $acc = \App\Models\ACC::find($request->acc_id);
        if ($acc) {
            $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
            if ($accUser) {
                $notificationService = new NotificationService();
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                $notificationService->notifyInstructorAuthorizationRequested(
                    $accUser->id,
                    $authorization->id,
                    $instructorName,
                    $trainingCenter->name
                );
            }
        }

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
     * Create payment intent for instructor authorization payment
     */
    public function createAuthorizationPaymentIntent(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorization = InstructorAccAuthorization::with(['instructor', 'acc'])
            ->where('id', $id)
            ->where('training_center_id', $trainingCenter->id)
            ->firstOrFail();

        // Verify authorization is approved and commission is set
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

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
        }

        try {
            $result = $this->stripeService->createPaymentIntent(
                $authorization->authorization_price,
                'USD',
                [
                    'authorization_id' => (string)$authorization->id,
                    'training_center_id' => (string)$trainingCenter->id,
                    'acc_id' => (string)$authorization->acc_id,
                    'instructor_id' => (string)$authorization->instructor_id,
                    'type' => 'instructor_authorization',
                    'amount' => (string)$authorization->authorization_price,
                ]
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $authorization->authorization_price,
                'currency' => $result['currency'],
                'authorization' => $authorization,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pay for instructor authorization
     * Called after Group Admin sets commission percentage
     */
    public function payAuthorization(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string',
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

        // Verify Stripe payment intent
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $authorization->authorization_price,
                    [
                        'authorization_id' => (string)$authorization->id,
                        'training_center_id' => (string)$trainingCenter->id,
                        'type' => 'instructor_authorization',
                    ]
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
        }

        DB::beginTransaction();
        try {
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

            // Send notifications
            $authorization->load(['instructor', 'acc', 'trainingCenter']);
            $notificationService = new NotificationService();
            $instructor = $authorization->instructor;
            $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
            
            // Notify Training Center about successful payment
            $notificationService->notifyInstructorAuthorizationPaymentSuccess(
                $user->id,
                $authorization->id,
                $instructorName,
                $authorization->authorization_price
            );
            
            // Notify Admin
            $notificationService->notifyInstructorAuthorizationPaid(
                $authorization->id,
                $instructorName,
                $authorization->authorization_price
            );

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

