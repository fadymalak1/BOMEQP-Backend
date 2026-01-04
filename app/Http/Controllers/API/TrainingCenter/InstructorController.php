<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\TrainingCenter;
use App\Services\InstructorManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class InstructorController extends Controller
{
    protected InstructorManagementService $instructorService;

    public function __construct(InstructorManagementService $instructorService)
    {
        $this->instructorService = $instructorService;
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
            'cv' => 'nullable|file|mimes:pdf|max:10240',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
            'is_assessor' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        try {
            $result = $this->instructorService->createInstructor($request, $trainingCenter);
            return response()->json([
                'message' => $result['message'],
                'instructor' => $result['instructor'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create instructor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to create instructor',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

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
            'cv' => 'nullable|file|mimes:pdf|max:10240',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
            'is_assessor' => 'nullable|boolean',
        ]);

        try {
            $result = $this->instructorService->updateInstructor($request, $instructor, $trainingCenter);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json([
                'message' => $result['message'],
                'instructor' => $result['instructor']->load('trainingCenter')
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update instructor', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to update instructor',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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

        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        try {
            $result = $this->instructorService->requestAuthorization($request, $instructor, $trainingCenter);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json([
                'message' => $result['message'],
                'authorization' => $result['authorization'],
                'courses_count' => $result['courses_count'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create authorization request', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to create authorization request',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorization = InstructorAccAuthorization::with(['instructor', 'acc'])
            ->where('id', $id)
            ->where('training_center_id', $trainingCenter->id)
            ->firstOrFail();

        try {
            $result = $this->instructorService->createAuthorizationPaymentIntent($authorization, $trainingCenter);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null
                ], $result['code']);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'commission_amount' => number_format($result['commission_amount'], 2, '.', ''),
                'provider_amount' => $result['provider_amount'] ? number_format($result['provider_amount'], 2, '.', '') : null,
                'payment_type' => $result['payment_type'],
                'authorization' => $authorization,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to create payment intent', [
                'authorization_id' => $authorization->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->findOrFail($id);

        try {
            $result = $this->instructorService->processAuthorizationPayment($request, $authorization, $trainingCenter);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            // Send notifications
            try {
                $authorization->load(['instructor', 'acc', 'trainingCenter']);
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
                $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
                
                $this->instructorService->notificationService->notifyInstructorAuthorizationPaymentSuccess(
                    $user->id,
                    $authorization->id,
                    $instructorName,
                    $authorization->authorization_price
                );
                
                $this->instructorService->notificationService->notifyInstructorAuthorizationPaid(
                    $authorization->id,
                    $instructorName,
                    $authorization->authorization_price,
                    $groupCommissionAmount
                );
                
                if ($groupCommissionAmount > 0) {
                    $acc = $authorization->acc;
                    $this->instructorService->notificationService->notifyAdminCommissionReceived(
                        $result['transaction']->id,
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

            return response()->json([
                'message' => 'Payment successful. Instructor is now officially authorized.',
                'authorization' => $authorization->fresh()->load(['instructor', 'acc', 'trainingCenter']),
                'transaction' => $result['transaction']
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to process authorization payment', [
                'authorization_id' => $authorization->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Payment failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

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

