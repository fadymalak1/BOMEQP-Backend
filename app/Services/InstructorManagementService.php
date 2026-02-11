<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Mail\InstructorCredentialsMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorManagementService
{
    protected FileUploadService $fileUploadService;
    protected NotificationService $notificationService;
    protected StripeService $stripeService;

    public function __construct(
        FileUploadService $fileUploadService,
        NotificationService $notificationService,
        StripeService $stripeService
    ) {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
        $this->stripeService = $stripeService;
    }

    /**
     * Create a new instructor
     *
     * @param Request $request
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function createInstructor(Request $request, TrainingCenter $trainingCenter): array
    {
        try {
            DB::beginTransaction();

            // Handle CV upload (required)
            $cvUrl = null;
            if ($request->hasFile('cv')) {
                $cvResult = $this->fileUploadService->uploadDocument(
                    $request->file('cv'),
                    $trainingCenter->id,
                    'training_center',
                    'instructor_cv'
                );

                if ($cvResult['success']) {
                    $cvUrl = $cvResult['url'];
                }
            }

            // Handle Passport upload (required)
            $passportImageUrl = null;
            if ($request->hasFile('passport')) {
                $passportResult = $this->fileUploadService->uploadDocument(
                    $request->file('passport'),
                    $trainingCenter->id,
                    'training_center',
                    'instructor_passport'
                );

                if ($passportResult['success']) {
                    $passportImageUrl = $passportResult['url'];
                }
            }

            // Generate random password
            $password = Str::random(12);
            $instructorName = $request->first_name . ' ' . $request->last_name;

            // Create instructor record
            $instructor = Instructor::create([
                'training_center_id' => $trainingCenter->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'id_number' => $request->id_number ?? null,
                'cv_url' => $cvUrl,
                'passport_image_url' => $passportImageUrl,
                'certificates_json' => $request->certificates_json ?? $request->certificates ?? null,
                'specializations' => $request->languages ?? $request->specializations,
                'is_assessor' => $request->boolean('is_assessor'),
                'status' => 'pending',
            ]);

            // Create user account
            $user = User::create([
                'name' => $instructorName,
                'email' => $request->email,
                'password' => Hash::make($password),
                'role' => 'instructor',
                'status' => 'active',
            ]);

            DB::commit();

            // Send credentials email
            try {
                Mail::to($request->email)->send(new InstructorCredentialsMail(
                    $request->email,
                    $password,
                    $instructorName,
                    $trainingCenter->name
                ));
                Log::info('Instructor credentials email sent successfully', [
                    'email' => $request->email,
                    'instructor_name' => $instructorName
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send instructor credentials email', [
                    'email' => $request->email,
                    'instructor_name' => $instructorName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return [
                'success' => true,
                'instructor' => $instructor,
                'message' => 'Instructor created successfully. Credentials have been sent to the instructor\'s email.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create instructor', [
                'training_center_id' => $trainingCenter->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update instructor
     *
     * @param Request $request
     * @param Instructor $instructor
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function updateInstructor(Request $request, Instructor $instructor, TrainingCenter $trainingCenter): array
    {
        // Verify ownership
        if ($instructor->training_center_id !== $trainingCenter->id) {
            return [
                'success' => false,
                'message' => 'Unauthorized',
                'code' => 403
            ];
        }

        try {
            DB::beginTransaction();

            // Enhanced data collection for POST (multipart/form-data) and PUT (form-urlencoded)
            $updateData = [];
            $allRequestData = $request->all();
            $requestMethod = $request->method();
            $contentType = $request->header('Content-Type', '');
            
            // Handle PUT/PATCH requests with form-urlencoded (Laravel limitation)
            if (in_array($requestMethod, ['PUT', 'PATCH']) && 
                str_contains($contentType, 'application/x-www-form-urlencoded') && 
                empty($allRequestData)) {
                parse_str($request->getContent(), $parsedData);
                $allRequestData = $parsedData;
                $request->merge($parsedData);
            }

            // All fields are required for update
            $updateData['first_name'] = $request->input('first_name');
            $updateData['last_name'] = $request->input('last_name');
            $updateData['email'] = $request->input('email');
            $updateData['date_of_birth'] = $request->input('date_of_birth');
            $updateData['phone'] = $request->input('phone');
            $updateData['specializations'] = $request->input('languages') ?? $request->input('specializations');
            $updateData['is_assessor'] = $request->boolean('is_assessor');

            // Handle CV upload (required)
            if ($request->hasFile('cv')) {
                // Delete old CV if exists
                if ($instructor->cv_url) {
                    try {
                        $oldPath = str_replace(Storage::disk('public')->url(''), '', $instructor->cv_url);
                        $oldPath = ltrim($oldPath, '/storage/');
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old CV', [
                            'instructor_id' => $instructor->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $cvResult = $this->fileUploadService->uploadDocument(
                    $request->file('cv'),
                    $trainingCenter->id,
                    'training_center',
                    'instructor_cv'
                );

                if ($cvResult['success']) {
                    $updateData['cv_url'] = $cvResult['url'];
                }
            }

            // Handle Passport upload (required)
            if ($request->hasFile('passport')) {
                // Delete old passport if exists
                if ($instructor->passport_image_url) {
                    try {
                        $oldPath = str_replace(Storage::disk('public')->url(''), '', $instructor->passport_image_url);
                        $oldPath = ltrim($oldPath, '/storage/');
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old passport', [
                            'instructor_id' => $instructor->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $passportResult = $this->fileUploadService->uploadDocument(
                    $request->file('passport'),
                    $trainingCenter->id,
                    'training_center',
                    'instructor_passport'
                );

                if ($passportResult['success']) {
                    $updateData['passport_image_url'] = $passportResult['url'];
                }
            }

            $instructor->update($updateData);

            DB::commit();

            return [
                'success' => true,
                'instructor' => $instructor->fresh(),
                'message' => 'Instructor updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update instructor', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Request authorization for instructor
     *
     * @param Request $request
     * @param Instructor $instructor
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function requestAuthorization(Request $request, Instructor $instructor, TrainingCenter $trainingCenter): array
    {
        // Validate that Training Center has authorization with the requested ACC
        $trainingCenterAccAuthorization = \App\Models\TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('acc_id', $request->acc_id)
            ->where('status', 'approved')
            ->first();

        if (!$trainingCenterAccAuthorization) {
            return [
                'success' => false,
                'message' => 'Training Center does not have authorization with this ACC. Please request authorization for the ACC first.',
                'code' => 403
            ];
        }

        // Validate that either sub_category_id or course_ids is provided
        if (!$request->has('sub_category_id') && !$request->has('course_ids')) {
            return [
                'success' => false,
                'message' => 'Either sub_category_id or course_ids must be provided',
                'code' => 422
            ];
        }

        if ($request->has('sub_category_id') && $request->has('course_ids')) {
            return [
                'success' => false,
                'message' => 'Cannot provide both sub_category_id and course_ids. Please provide only one.',
                'code' => 422
            ];
        }

        // Get course IDs based on selection type
        $courseIds = [];
        if ($request->has('sub_category_id')) {
            $courseIds = \App\Models\Course::where('sub_category_id', $request->sub_category_id)
                ->where('acc_id', $request->acc_id)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            if (empty($courseIds)) {
                return [
                    'success' => false,
                    'message' => 'No active courses found for the selected sub-category in this ACC',
                    'code' => 422
                ];
            }
        } else {
            // Validate that all course_ids belong to the selected ACC
            $accCourses = \App\Models\Course::where('acc_id', $request->acc_id)
                ->whereIn('id', $request->course_ids)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            if (count($accCourses) !== count($request->course_ids)) {
                return [
                    'success' => false,
                    'message' => 'Some selected courses do not belong to the selected ACC or are not active',
                    'code' => 422
                ];
            }

            $courseIds = $request->course_ids;
        }

        // Check if there's already a pending authorization request for this instructor, ACC, and same category/courses
        $existingAuthorizations = InstructorAccAuthorization::where('instructor_id', $instructor->id)
            ->where('acc_id', $request->acc_id)
            ->where('training_center_id', $trainingCenter->id)
            ->where('status', 'pending')
            ->get();

        // Check for conflicts: same sub_category_id or overlapping courses
        foreach ($existingAuthorizations as $existingAuth) {
            // Check if same sub_category_id
            if ($request->has('sub_category_id') && 
                $existingAuth->sub_category_id == $request->sub_category_id) {
                return [
                    'success' => false,
                    'message' => "There is already a pending authorization request for this instructor with this ACC and sub-category. Please wait for the current request to be processed.",
                    'code' => 409, // Conflict status code
                    'existing_authorization_id' => $existingAuth->id,
                ];
            }

            // Check if courses overlap
            $existingCourseIds = $existingAuth->documents_json['requested_course_ids'] ?? [];
            if (!empty($courseIds) && !empty($existingCourseIds)) {
                $overlappingCourses = array_intersect($courseIds, $existingCourseIds);
                if (!empty($overlappingCourses)) {
                    return [
                        'success' => false,
                        'message' => "There is already a pending authorization request for this instructor with this ACC that includes some of the same courses. Please wait for the current request to be processed.",
                        'code' => 409, // Conflict status code
                        'existing_authorization_id' => $existingAuth->id,
                        'overlapping_courses' => array_values($overlappingCourses),
                    ];
                }
            }
        }

        try {
            DB::beginTransaction();

            // Create authorization request
            $documentsData = $request->documents_json ?? $request->documents ?? [];
            $documentsData['requested_course_ids'] = $courseIds;

            $authorization = InstructorAccAuthorization::create([
                'instructor_id' => $instructor->id,
                'acc_id' => $request->acc_id,
                'sub_category_id' => $request->sub_category_id,
                'training_center_id' => $trainingCenter->id,
                'request_date' => now(),
                'status' => 'pending',
                'documents_json' => $documentsData,
            ]);

            DB::commit();

            // Send notification to ACC admin
            $acc = \App\Models\ACC::find($request->acc_id);
            if ($acc) {
                $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
                if ($accUser) {
                    $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                    
                    $subCategoryName = null;
                    if ($request->sub_category_id) {
                        $subCategory = \App\Models\SubCategory::find($request->sub_category_id);
                        $subCategoryName = $subCategory?->name;
                    }
                    
                    $this->notificationService->notifyInstructorAuthorizationRequested(
                        $accUser->id,
                        $authorization->id,
                        $instructorName,
                        $trainingCenter->name,
                        $request->sub_category_id,
                        $courseIds,
                        $subCategoryName,
                        count($courseIds)
                    );
                }
            }

            return [
                'success' => true,
                'authorization' => $authorization->load('subCategory'),
                'courses_count' => count($courseIds),
                'message' => 'Authorization request submitted successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create authorization request', [
                'instructor_id' => $instructor->id,
                'acc_id' => $request->acc_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create payment intent for authorization payment
     *
     * @param InstructorAccAuthorization $authorization
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function createAuthorizationPaymentIntent(
        InstructorAccAuthorization $authorization,
        TrainingCenter $trainingCenter
    ): array {
        // Verify authorization is ready for payment
        if ($authorization->status !== 'approved') {
            return [
                'success' => false,
                'message' => 'Authorization must be approved by ACC Admin first',
                'code' => 400
            ];
        }

        if ($authorization->group_admin_status !== 'commission_set') {
            return [
                'success' => false,
                'message' => 'Group Admin must set commission percentage first',
                'code' => 400
            ];
        }

        if ($authorization->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => 'Authorization already paid',
                'code' => 400
            ];
        }

        if (!$authorization->authorization_price || $authorization->authorization_price <= 0) {
            return [
                'success' => false,
                'message' => 'Authorization price not set',
                'code' => 400
            ];
        }

        if (!$this->stripeService->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Stripe payment is not configured',
                'code' => 400
            ];
        }

        // Calculate commission amounts
        $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
        $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
        $acc = $authorization->acc;

        // Prepare metadata
        $metadata = [
            'authorization_id' => (string)$authorization->id,
            'training_center_id' => (string)$trainingCenter->id,
            'acc_id' => (string)$authorization->acc_id,
            'instructor_id' => (string)$authorization->instructor_id,
            'type' => 'instructor_authorization',
            'amount' => (string)$authorization->authorization_price,
            'group_commission_percentage' => (string)$groupCommissionPercentage,
            'group_commission_amount' => (string)$groupCommissionAmount,
        ];

        // Use destination charges if ACC has Stripe account ID
        if (!empty($acc->stripe_account_id) && $groupCommissionAmount > 0) {
            $result = $this->stripeService->createDestinationChargePaymentIntent(
                $authorization->authorization_price,
                $acc->stripe_account_id,
                $groupCommissionAmount,
                'usd',
                $metadata
            );

            if (!$result['success']) {
                Log::warning('Destination charge failed, falling back to standard payment', [
                    'acc_id' => $acc->id,
                    'stripe_account_id' => $acc->stripe_account_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                $result = $this->stripeService->createPaymentIntent(
                    $authorization->authorization_price,
                    'USD',
                    $metadata
                );
            }
        } else {
            $result = $this->stripeService->createPaymentIntent(
                $authorization->authorization_price,
                'USD',
                $metadata
            );
        }

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create payment intent',
                'error' => $result['error'] ?? 'Unknown error',
                'code' => 500
            ];
        }

        return [
            'success' => true,
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['payment_intent_id'],
            'amount' => $authorization->authorization_price,
            'currency' => $result['currency'] ?? 'USD',
            'commission_amount' => $result['commission_amount'] ?? $groupCommissionAmount,
            'provider_amount' => $result['provider_amount'] ?? null,
            'payment_type' => !empty($acc->stripe_account_id) && $groupCommissionAmount > 0 ? 'destination_charge' : 'standard',
        ];
    }

    /**
     * Process authorization payment
     *
     * @param Request $request
     * @param InstructorAccAuthorization $authorization
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function processAuthorizationPayment(
        Request $request,
        InstructorAccAuthorization $authorization,
        TrainingCenter $trainingCenter
    ): array {
        // Verify authorization is ready for payment
        if ($authorization->status !== 'approved') {
            return [
                'success' => false,
                'message' => 'Authorization must be approved by ACC Admin first',
                'code' => 400
            ];
        }

        if ($authorization->group_admin_status !== 'commission_set') {
            return [
                'success' => false,
                'message' => 'Group Admin must set commission percentage first',
                'code' => 400
            ];
        }

        if ($authorization->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => 'Authorization already paid',
                'code' => 400
            ];
        }

        if (!$authorization->authorization_price || $authorization->authorization_price <= 0) {
            return [
                'success' => false,
                'message' => 'Authorization price not set',
                'code' => 400
            ];
        }

        try {
            DB::beginTransaction();

            // Verify payment intent
            $this->stripeService->verifyPaymentIntent(
                $request->payment_intent_id,
                $authorization->authorization_price,
                [
                    'authorization_id' => (string)$authorization->id,
                    'training_center_id' => (string)$trainingCenter->id,
                    'acc_id' => (string)$authorization->acc_id,
                    'instructor_id' => (string)$authorization->instructor_id,
                    'type' => 'instructor_authorization',
                ]
            );

            // Calculate commission amounts
            $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
            $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
            $accCommissionAmount = $authorization->authorization_price - $groupCommissionAmount;
            
            // Determine payment type
            $paymentType = 'standard';
            try {
                $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
                if ($paymentIntent && isset($paymentIntent->metadata->payment_type) && 
                    $paymentIntent->metadata->payment_type === 'destination_charge') {
                    $paymentType = 'destination_charge';
                }
            } catch (\Exception $e) {
                Log::warning('Failed to retrieve payment intent for payment type', [
                    'payment_intent_id' => $request->payment_intent_id,
                    'error' => $e->getMessage()
                ]);
            }

            // Load instructor relationship for description
            $authorization->load('instructor');
            
            // Create transaction
            $instructorName = ($authorization->instructor->first_name ?? '') . ' ' . ($authorization->instructor->last_name ?? '');
            $transaction = \App\Models\Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $authorization->acc_id,
                'amount' => $authorization->authorization_price,
                'commission_amount' => $groupCommissionAmount,
                'provider_amount' => $accCommissionAmount,
                'currency' => 'USD',
                'payment_method' => 'credit_card',
                'payment_type' => $paymentType,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
                'description' => 'Instructor authorization payment for ' . trim($instructorName),
                'reference_type' => 'InstructorAccAuthorization',
                'reference_id' => $authorization->id,
            ]);

            // Update authorization payment status for this request and all merged requests
            // Get all approved authorizations for this instructor and ACC that are waiting for payment
            $allApprovedAuthorizations = \App\Models\InstructorAccAuthorization::where('instructor_id', $authorization->instructor_id)
                ->where('acc_id', $authorization->acc_id)
                ->where('status', 'approved')
                ->where('group_admin_status', 'commission_set')
                ->where('payment_status', '!=', 'paid')
                ->get();

            // Update all merged authorization requests to paid
            $allApprovedAuthorizations->each(function ($auth) use ($transaction) {
                $auth->update([
                    'payment_status' => 'paid',
                    'payment_date' => now(),
                    'payment_transaction_id' => $transaction->id,
                    'group_admin_status' => 'completed',
                ]);
            });

            // Create commission ledger entry
            try {
                \App\Models\CommissionLedger::create([
                    'transaction_id' => $transaction->id,
                    'acc_id' => $authorization->acc_id,
                    'training_center_id' => $trainingCenter->id,
                    'instructor_id' => $authorization->instructor_id,
                    'group_commission_amount' => $groupCommissionAmount,
                    'group_commission_percentage' => $groupCommissionPercentage,
                    'acc_commission_amount' => $accCommissionAmount,
                    'acc_commission_percentage' => 100 - $groupCommissionPercentage,
                    'settlement_status' => 'pending',
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create commission ledger entry', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Load ACC relationship for certificate generation
            $authorization->load('acc');
            $acc = $authorization->acc;
            
            if (!$acc) {
                Log::warning('ACC not found for authorization', [
                    'authorization_id' => $authorization->id,
                    'acc_id' => $authorization->acc_id
                ]);
            } else {
                // Generate and send certificates for each authorized course
                $this->generateAndSendInstructorCertificates($authorization, $acc);
            }

            DB::commit();

            // Send notifications
            try {
                $authorization->load(['instructor', 'acc', 'trainingCenter']);
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
                $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
                
                // Get training center user for notification
                $trainingCenterUser = User::where('email', $trainingCenter->email)
                    ->where('role', 'training_center_admin')
                    ->first();
                
                if ($trainingCenterUser) {
                    $this->notificationService->notifyInstructorAuthorizationPaymentSuccess(
                        $trainingCenterUser->id,
                        $authorization->id,
                        $instructorName,
                        $authorization->authorization_price
                    );
                }
                
                $this->notificationService->notifyInstructorAuthorizationPaid(
                    $authorization->id,
                    $instructorName,
                    $authorization->authorization_price,
                    $groupCommissionAmount
                );
                
                if ($groupCommissionAmount > 0) {
                    $acc = $authorization->acc;
                    $this->notificationService->notifyAdminCommissionReceived(
                        $transaction->id,
                        'instructor_authorization',
                        $groupCommissionAmount,
                        $authorization->authorization_price,
                        $trainingCenter->name,
                        $acc ? $acc->name : null
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Failed to send notifications', [
                    'authorization_id' => $authorization->id,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'success' => true,
                'authorization' => $authorization->fresh(),
                'transaction' => $transaction,
                'message' => 'Authorization payment processed successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process authorization payment', [
                'authorization_id' => $authorization->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate and send instructor certificates for each authorized course
     * 
     * @param InstructorAccAuthorization $authorization
     * @param \App\Models\ACC $acc
     * @return void
     */
    private function generateAndSendInstructorCertificates($authorization, $acc): void
    {
        try {
            // Get instructor certificate template
            $certificateTemplate = \App\Models\CertificateTemplate::where('acc_id', $acc->id)
                ->where('template_type', 'instructor')
                ->where('status', 'active')
                ->first();

            if (!$certificateTemplate) {
                Log::info('No active instructor certificate template found for ACC', [
                    'acc_id' => $acc->id,
                    'authorization_id' => $authorization->id,
                ]);
                return;
            }

            // Get all authorized courses for this instructor and ACC
            $authorizedCourses = \App\Models\InstructorCourseAuthorization::where('instructor_id', $authorization->instructor_id)
                ->where('acc_id', $authorization->acc_id)
                ->where('status', 'active')
                ->with('course')
                ->get();

            if ($authorizedCourses->isEmpty()) {
                Log::info('No authorized courses found for instructor certificate generation', [
                    'instructor_id' => $authorization->instructor_id,
                    'acc_id' => $authorization->acc_id,
                ]);
                return;
            }

            // Load instructor
            $authorization->load('instructor');
            $instructor = $authorization->instructor;

            if (!$instructor) {
                Log::warning('Instructor not found for certificate generation', [
                    'instructor_id' => $authorization->instructor_id,
                ]);
                return;
            }

            // Generate certificate for each course
            $certificateService = new \App\Services\CertificateGenerationService();
            
            foreach ($authorizedCourses as $courseAuth) {
                if (!$courseAuth->course) {
                    continue;
                }

                try {
                    // Check if certificate already exists for this instructor and course
                    // If a certificate exists, it means the email was already sent before
                    $existingCertificate = \App\Models\Certificate::where('instructor_id', $instructor->id)
                        ->where('course_id', $courseAuth->course->id)
                        ->where('training_center_id', $instructor->training_center_id)
                        ->first();

                    if ($existingCertificate) {
                        Log::info('Instructor certificate already exists for this course, skipping email', [
                            'instructor_id' => $instructor->id,
                            'course_id' => $courseAuth->course->id,
                            'course_name' => $courseAuth->course->name,
                            'existing_certificate_id' => $existingCertificate->id,
                            'message' => 'Email was already sent for this instructor and course combination',
                        ]);
                        continue; // Skip this course - don't send email again
                    }

                    // Generate verification code for the certificate
                    $verificationCode = 'VERIFY-' . strtoupper(\Illuminate\Support\Str::random(10));
                    
                    // Ensure verification code is unique
                    while (\App\Models\Certificate::where('verification_code', $verificationCode)->exists()) {
                        $verificationCode = 'VERIFY-' . strtoupper(\Illuminate\Support\Str::random(10));
                    }

                    $result = $certificateService->generateInstructorCertificate(
                        $certificateTemplate,
                        $instructor,
                        $courseAuth->course,
                        $acc,
                        $verificationCode
                    );

                    if ($result['success'] && isset($result['file_path'])) {
                        $pdfPath = Storage::disk('public')->path($result['file_path']);
                        $pdfUrl = $result['file_url'] ?? Storage::disk('public')->url($result['file_path']);
                        
                        if (file_exists($pdfPath)) {
                            // Send email with certificate immediately (not queued)
                            try {
                                $mail = new \App\Mail\InstructorCertificateMail(
                                    trim($instructor->first_name . ' ' . $instructor->last_name),
                                    $courseAuth->course->name,
                                    $acc->name,
                                    $pdfPath
                                );
                                
                                // Force send immediately by setting connection to sync
                                // This bypasses the queue even if ShouldQueue is implemented
                                $mail->onConnection('sync');
                                Mail::to($instructor->email)->send($mail);

                                // Generate certificate number (same format as CertificateController)
                                do {
                                    $certificateNumber = 'CERT-' . date('Y') . '-' . strtoupper(Str::random(8));
                                } while (\App\Models\Certificate::where('certificate_number', $certificateNumber)->exists());

                                // Save certificate to database after successful email send
                                $instructorFullName = trim($instructor->first_name . ' ' . $instructor->last_name);
                                \App\Models\Certificate::create([
                                    'certificate_number' => $certificateNumber,
                                    'course_id' => $courseAuth->course->id,
                                    'training_center_id' => $instructor->training_center_id,
                                    'instructor_id' => $instructor->id,
                                    'type' => 'instructor', // This is always an instructor certificate
                                    'trainee_name' => $instructorFullName,
                                    'trainee_id_number' => $instructor->id_number,
                                    'issue_date' => now()->toDateString(),
                                    'expiry_date' => null, // Instructor certificates don't expire
                                    'template_id' => $certificateTemplate->id,
                                    'certificate_pdf_url' => $pdfUrl,
                                    'verification_code' => $verificationCode,
                                    'status' => 'valid',
                                ]);

                                Log::info('Instructor certificate generated, sent, and saved to database', [
                                    'instructor_id' => $instructor->id,
                                    'course_id' => $courseAuth->course->id,
                                    'course_name' => $courseAuth->course->name,
                                    'email' => $instructor->email,
                                    'pdf_path' => $pdfPath,
                                    'verification_code' => $verificationCode,
                                    'certificate_number' => $certificateNumber,
                                ]);
                            } catch (\Exception $mailException) {
                                Log::error('Failed to send instructor certificate email', [
                                    'instructor_id' => $instructor->id,
                                    'course_id' => $courseAuth->course->id,
                                    'email' => $instructor->email,
                                    'pdf_path' => $pdfPath,
                                    'error' => $mailException->getMessage(),
                                    'trace' => $mailException->getTraceAsString(),
                                ]);
                            }
                        } else {
                            Log::warning('Certificate PDF file does not exist', [
                                'instructor_id' => $instructor->id,
                                'course_id' => $courseAuth->course->id,
                                'pdf_path' => $pdfPath,
                            ]);
                        }
                    } else {
                        Log::warning('Failed to generate instructor certificate', [
                            'instructor_id' => $instructor->id,
                            'course_id' => $courseAuth->course->id,
                            'error' => $result['message'] ?? 'Unknown error',
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error generating/sending instructor certificate for course', [
                        'instructor_id' => $instructor->id,
                        'course_id' => $courseAuth->course->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate and send instructor certificates', [
                'authorization_id' => $authorization->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

