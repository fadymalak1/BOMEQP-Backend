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
use OpenApi\Attributes as OA;

class InstructorController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    #[OA\Get(
        path: "/training-center/instructors",
        summary: "List instructors",
        description: "Get all instructors for the authenticated training center.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Instructors retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "instructors", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
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

    #[OA\Post(
        path: "/training-center/instructors",
        summary: "Create instructor",
        description: "Create a new instructor. An email with login credentials will be sent to the instructor.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["first_name", "last_name", "email", "phone", "id_number"],
                    properties: [
                        new OA\Property(property: "first_name", type: "string", example: "John"),
                        new OA\Property(property: "last_name", type: "string", example: "Doe"),
                        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                        new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                        new OA\Property(property: "id_number", type: "string", example: "ID123456"),
                        new OA\Property(property: "cv", type: "string", format: "binary", description: "CV file (PDF, max 10MB)"),
                        new OA\Property(property: "certificates_json", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "specializations", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "is_assessor", type: "boolean", example: false)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Instructor created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Instructor created successfully"),
                        new OA\Property(property: "instructor", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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
            'is_assessor' => 'nullable|boolean',
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
            'is_assessor' => $request->boolean('is_assessor', false),
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

    #[OA\Get(
        path: "/training-center/instructors/{id}",
        summary: "Get instructor details",
        description: "Get detailed information about a specific instructor.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Instructor retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "instructor", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor not found")
        ]
    )]
    public function show($id)
    {
        $instructor = Instructor::with('trainingCenter')->findOrFail($id);
        return response()->json(['instructor' => $instructor]);
    }

    #[OA\Put(
        path: "/training-center/instructors/{id}",
        summary: "Update instructor",
        description: "Update instructor information.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "first_name", type: "string", nullable: true),
                        new OA\Property(property: "last_name", type: "string", nullable: true),
                        new OA\Property(property: "phone", type: "string", nullable: true),
                        new OA\Property(property: "cv", type: "string", format: "binary", nullable: true),
                        new OA\Property(property: "certificates_json", type: "array", nullable: true, items: new OA\Items(type: "object")),
                        new OA\Property(property: "specializations", type: "array", nullable: true, items: new OA\Items(type: "string")),
                        new OA\Property(property: "is_assessor", type: "boolean", nullable: true)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Instructor updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Instructor updated successfully"),
                        new OA\Property(property: "instructor", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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
            'is_assessor' => 'nullable|boolean',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'email', 'phone', 'id_number',
            'specializations', 'is_assessor'
        ]);
        
        // Handle boolean conversion for is_assessor
        if ($request->has('is_assessor')) {
            $updateData['is_assessor'] = $request->boolean('is_assessor');
        }
        
        // Handle CV file upload
        if ($request->hasFile('cv')) {
            try {
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
                $fileName = time() . '_' . $trainingCenter->id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $cvFile->getClientOriginalName());
                // Store file in public disk
                $cvPath = $cvFile->storeAs('instructors/cv', $fileName, 'public');
                
                if ($cvPath) {
                    // Generate URL using the API route (route is /storage/instructors/cv/{filename} in api.php, so it becomes /api/storage/instructors/cv/{filename})
                    $updateData['cv_url'] = url('/api/storage/instructors/cv/' . $fileName);
                } else {
                    \Log::error('Failed to store CV file', ['instructor_id' => $instructor->id, 'file_name' => $fileName]);
                }
            } catch (\Exception $e) {
                \Log::error('Error uploading CV file', [
                    'instructor_id' => $instructor->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'Failed to upload CV file',
                    'error' => config('app.debug') ? $e->getMessage() : 'File upload failed'
                ], 500);
            }
        }
        
        if ($request->has('certificates_json') || $request->has('certificates')) {
            $updateData['certificates_json'] = $request->certificates_json ?? $request->certificates;
        }
        
        $instructor->update($updateData);
        
        // Refresh the model to get the latest data
        $instructor->refresh();

        return response()->json(['message' => 'Instructor updated successfully', 'instructor' => $instructor->load('trainingCenter')]);
    }

    #[OA\Get(
        path: "/training-center/accs/{accId}/courses",
        summary: "Get courses by ACC and sub-category",
        description: "Get all active courses for a specific ACC, optionally filtered by sub-category.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accId", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "sub_category_id", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Courses retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "courses", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getAccCourses(Request $request, $accId)
    {
        $acc = \App\Models\ACC::findOrFail($accId);
        
        $query = \App\Models\Course::where('acc_id', $accId)
            ->where('status', 'active')
            ->with('subCategory');

        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        $courses = $query->get();

        return response()->json([
            'courses' => $courses,
            'acc' => [
                'id' => $acc->id,
                'name' => $acc->name,
            ]
        ]);
    }

    #[OA\Get(
        path: "/training-center/accs/{accId}/sub-categories",
        summary: "Get sub-categories with courses for an ACC",
        description: "Get all sub-categories that have courses in a specific ACC, with course counts.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accId", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Sub-categories retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "sub_categories", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getAccSubCategories($accId)
    {
        $acc = \App\Models\ACC::findOrFail($accId);
        
        $subCategories = \App\Models\SubCategory::whereHas('courses', function($query) use ($accId) {
            $query->where('acc_id', $accId)
                  ->where('status', 'active');
        })
        ->withCount(['courses' => function($query) use ($accId) {
            $query->where('acc_id', $accId)
                  ->where('status', 'active');
        }])
        ->get();

        return response()->json([
            'sub_categories' => $subCategories,
            'acc' => [
                'id' => $acc->id,
                'name' => $acc->name,
            ]
        ]);
    }

    #[OA\Post(
        path: "/training-center/instructors/{id}/request-authorization",
        summary: "Request instructor authorization",
        description: "Request authorization for an instructor to teach courses from an ACC. You can either select a sub-category (all courses in that sub-category) or select specific courses.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["acc_id"],
                properties: [
                    new OA\Property(property: "acc_id", type: "integer", example: 1, description: "ACC ID to request authorization from"),
                    new OA\Property(property: "sub_category_id", type: "integer", nullable: true, example: 5, description: "Sub-category ID - authorizes instructor for all courses in this sub-category"),
                    new OA\Property(property: "course_ids", type: "array", nullable: true, items: new OA\Items(type: "integer"), example: [1, 2, 3], description: "Array of specific course IDs to authorize"),
                    new OA\Property(property: "documents_json", type: "array", nullable: true, items: new OA\Items(type: "object"), description: "Optional documents array")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Authorization request submitted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Authorization request submitted successfully"),
                        new OA\Property(property: "authorization", type: "object"),
                        new OA\Property(property: "courses_count", type: "integer", example: 5, description: "Number of courses included in the authorization")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor or ACC not found"),
            new OA\Response(response: 422, description: "Validation error - either sub_category_id or course_ids must be provided, but not both")
        ]
    )]
    public function requestAuthorization(Request $request, $id)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'exists:courses,id',
            'documents_json' => 'nullable|array',
        ]);

        // Validate that either sub_category_id or course_ids is provided, but not both
        if (!$request->has('sub_category_id') && !$request->has('course_ids')) {
            return response()->json([
                'message' => 'Either sub_category_id or course_ids must be provided',
                'errors' => ['sub_category_id' => ['Either sub_category_id or course_ids is required']]
            ], 422);
        }

        if ($request->has('sub_category_id') && $request->has('course_ids')) {
            return response()->json([
                'message' => 'Cannot provide both sub_category_id and course_ids. Please provide only one.',
                'errors' => ['sub_category_id' => ['Cannot provide both sub_category_id and course_ids']]
            ], 422);
        }

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        // Get course IDs based on selection type
        $courseIds = [];
        if ($request->has('sub_category_id')) {
            // Get all courses in the sub-category that belong to the selected ACC
            $courseIds = \App\Models\Course::where('sub_category_id', $request->sub_category_id)
                ->where('acc_id', $request->acc_id)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            if (empty($courseIds)) {
                return response()->json([
                    'message' => 'No active courses found for the selected sub-category in this ACC'
                ], 422);
            }
        } else {
            // Validate that all course_ids belong to the selected ACC
            $accCourses = \App\Models\Course::where('acc_id', $request->acc_id)
                ->whereIn('id', $request->course_ids)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            if (count($accCourses) !== count($request->course_ids)) {
                return response()->json([
                    'message' => 'Some selected courses do not belong to the selected ACC or are not active'
                ], 422);
            }

            $courseIds = $request->course_ids;
        }

        $authorization = InstructorAccAuthorization::create([
            'instructor_id' => $instructor->id,
            'acc_id' => $request->acc_id,
            'sub_category_id' => $request->sub_category_id,
            'training_center_id' => $trainingCenter->id,
            'request_date' => now(),
            'status' => 'pending',
            'documents_json' => $request->documents_json ?? $request->documents,
        ]);

        // Store course IDs in documents_json for reference (or create a separate field)
        // For now, we'll add it to documents_json as metadata
        $documentsData = $request->documents_json ?? $request->documents ?? [];
        $documentsData['requested_course_ids'] = $courseIds;
        $authorization->update(['documents_json' => $documentsData]);

        // Send notification to ACC admin with enhanced details
        $acc = \App\Models\ACC::find($request->acc_id);
        if ($acc) {
            $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
            if ($accUser) {
                $notificationService = new NotificationService();
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                // Get sub-category name if applicable
                $subCategoryName = null;
                if ($request->sub_category_id) {
                    $subCategory = \App\Models\SubCategory::find($request->sub_category_id);
                    $subCategoryName = $subCategory?->name;
                }
                
                $notificationService->notifyInstructorAuthorizationRequested(
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

        return response()->json([
            'message' => 'Authorization request submitted successfully',
            'authorization' => $authorization->load('subCategory'),
            'courses_count' => count($courseIds),
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

