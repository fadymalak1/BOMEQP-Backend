<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\InstructorAccAuthorization;
use App\Models\Instructor;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class InstructorController extends Controller
{
    #[OA\Get(
        path: "/acc/instructors/requests",
        summary: "List pending instructor authorization requests",
        description: "Get all pending instructor authorization requests for the authenticated ACC. Multiple pending requests for the same instructor are automatically merged into one request with combined course IDs. Only pending requests are returned - approved, rejected, and returned requests are excluded.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by instructor name, email, phone, ID number, country, city, training center name, request ID, or content in certificates/specializations"),
            new OA\Parameter(name: "country", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "USA", description: "Filter by instructor country"),
            new OA\Parameter(name: "city", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "New York", description: "Filter by instructor city"),
            new OA\Parameter(name: "is_assessor", in: "query", required: false, schema: new OA\Schema(type: "boolean"), example: false, description: "Filter by assessor status (true for Assessor, false for Instructor)"),
            new OA\Parameter(name: "payment_status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "paid", "failed"]), description: "Filter by payment status"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Pending requests retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "last_page", type: "integer"),
                        new OA\Property(
                            property: "statistics",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total", type: "integer", example: 10, description: "Total number of distinct pending instructor requests (merged)"),
                                new OA\Property(property: "pending", type: "integer", example: 10, description: "Number of pending requests (same as total, since only pending are returned)"),
                                new OA\Property(property: "approved", type: "integer", example: 0, description: "Always 0, as only pending requests are returned"),
                                new OA\Property(property: "rejected", type: "integer", example: 0, description: "Always 0, as only pending requests are returned"),
                                new OA\Property(property: "returned", type: "integer", example: 0, description: "Always 0, as only pending requests are returned")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function requests(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Get only pending requests with relationships
        $allPendingRequestsQuery = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'pending')
            ->with(['instructor', 'trainingCenter', 'subCategory.category', 'subCategory.courses']);

        // Apply filters before merging (filter by instructor attributes)
        $allPendingRequestsQuery->whereHas('instructor', function ($instructorQuery) use ($request) {
            // Filter by country
            if ($request->has('country') && !empty($request->country)) {
                $instructorQuery->where('country', 'like', "%{$request->country}%");
            }

            // Filter by city
            if ($request->has('city') && !empty($request->city)) {
                $instructorQuery->where('city', 'like', "%{$request->city}%");
            }

            // Filter by assessor status
            if ($request->has('is_assessor')) {
                $instructorQuery->where('is_assessor', $request->boolean('is_assessor'));
            }

            // Comprehensive search in instructor fields
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $instructorQuery->where(function($q) use ($searchTerm) {
                    $q->where('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('phone', 'like', "%{$searchTerm}%")
                        ->orWhere('id_number', 'like', "%{$searchTerm}%")
                        ->orWhere('country', 'like', "%{$searchTerm}%")
                        ->orWhere('city', 'like', "%{$searchTerm}%")
                        // Search in JSON fields
                        ->orWhereRaw("JSON_SEARCH(certificates_json, 'one', ?, NULL, '$[*].*') IS NOT NULL", ["%{$searchTerm}%"])
                        ->orWhereRaw("JSON_SEARCH(specializations, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$searchTerm}%"]);
                });
            }
        });

        // Also search in training center name
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $allPendingRequestsQuery->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                $tcQuery->where('name', 'like', "%{$searchTerm}%");
            });
        }

        // Also search in request ID
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $allPendingRequestsQuery->orWhere('id', 'like', "%{$searchTerm}%");
        }

        $allPendingRequests = $allPendingRequestsQuery
            ->orderBy('request_date', 'desc') // Order by desc to get latest first for merging
            ->get();

        // Group by instructor_id and merge multiple pending requests for the same instructor
        $grouped = $allPendingRequests->groupBy('instructor_id');
        $mergedRequests = $grouped->map(function ($requests) {
            // Get the latest request (for most recent data like relationships)
            $latest = $requests->last();
            
            // Collect all requested course IDs from all pending requests for this instructor
            $allRequestedCourseIds = [];
            $allDocuments = [];
            $earliestRequestDate = null;
            $requestIds = [];
            
            foreach ($requests as $req) {
                $documentsData = $req->documents_json ?? [];
                $requestedCourseIds = $documentsData['requested_course_ids'] ?? [];
                
                // Merge course IDs (avoid duplicates)
                $allRequestedCourseIds = array_unique(array_merge($allRequestedCourseIds, $requestedCourseIds));
                
                // Collect all documents
                if (!empty($documentsData)) {
                    $allDocuments[] = $documentsData;
                }
                
                // Track earliest request date
                if (!$earliestRequestDate || $req->request_date < $earliestRequestDate) {
                    $earliestRequestDate = $req->request_date;
                }
                
                // Track all request IDs
                $requestIds[] = $req->id;
            }
            
            // Process merged request data using latest request as base
            $data = $latest->toArray();
            
            // Update request_date to earliest
            $data['request_date'] = $earliestRequestDate;
            
            // Update documents_json to include merged course IDs
            $mergedDocumentsJson = $latest->documents_json ?? [];
            $mergedDocumentsJson['requested_course_ids'] = array_values($allRequestedCourseIds);
            $data['documents_json'] = $mergedDocumentsJson;
            
            // If there is a sub_category, add sub_category name, category, and courses
            if ($latest->sub_category_id && $latest->subCategory) {
                $subCategory = $latest->subCategory;
                $category = $subCategory->category;
                
                $data['category'] = $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                    'name_ar' => $category->name_ar ?? null,
                ] : null;
                
                $data['sub_category'] = [
                    'id' => $subCategory->id,
                    'name' => $subCategory->name,
                    'name_ar' => $subCategory->name_ar,
                    'courses' => $subCategory->courses->map(function ($course) {
                        return [
                            'id' => $course->id,
                            'name' => $course->name,
                            'name_ar' => $course->name_ar,
                            'code' => $course->code,
                        ];
                    })
                ];
            } else {
                $data['category'] = null;
                $data['sub_category'] = null;
            }
            
            // Get requested courses by merged IDs
            $requestedCourses = [];
            if (!empty($allRequestedCourseIds)) {
                $courses = \App\Models\Course::whereIn('id', $allRequestedCourseIds)->get();
                $requestedCourses = $courses->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'name' => $course->name,
                        'name_ar' => $course->name_ar,
                        'code' => $course->code,
                    ];
                })->toArray();
            }
            
            $data['requested_courses'] = $requestedCourses;
            
            // Add info about merged requests
            $data['merged_requests_count'] = $requests->count();
            $data['merged_request_ids'] = $requestIds;
            
            return $data;
        })->values();

        // Filter by payment_status if provided (after merging)
        if ($request->has('payment_status')) {
            $validPaymentStatuses = ['pending', 'paid', 'failed'];
            if (in_array($request->payment_status, $validPaymentStatuses)) {
                $mergedRequests = $mergedRequests->where('payment_status', $request->payment_status)->values();
            }
        }

        // Paginate the merged results
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $total = $mergedRequests->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $mergedRequests->slice($offset, $perPage)->values();

        // Calculate statistics - only pending requests (distinct instructors)
        $statistics = [
            'total' => $grouped->count(),
            'pending' => $grouped->count(),
            'approved' => 0,
            'rejected' => 0,
            'returned' => 0,
        ];

        return response()->json([
            'data' => $paginated,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            'statistics' => $statistics,
        ]);
    }

    #[OA\Put(
        path: "/acc/instructors/requests/{id}/approve",
        summary: "Approve instructor authorization request",
        description: "Approve an instructor authorization request. Requires an active instructor certificate template to be created first. Multiple pending requests for the same instructor are automatically merged.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), description: "Authorization request ID")
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["authorization_price"],
                properties: [
                    new OA\Property(property: "authorization_price", type: "number", format: "float", example: 100.00, description: "Authorization price to be paid by training center")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Instructor approved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "authorization", type: "object"),
                        new OA\Property(property: "courses_authorized", type: "integer"),
                        new OA\Property(property: "merged_requests_approved", type: "integer")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation error - Certificate template required or invalid authorization price",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Cannot approve instructor. Please create an active instructor certificate template first."),
                        new OA\Property(property: "errors", type: "object"),
                        new OA\Property(property: "required_action", type: "string", example: "create_instructor_template"),
                        new OA\Property(property: "template_type", type: "string", example: "instructor")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or authorization not found")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $request->validate([
            'authorization_price' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        // Validate that instructor certificate template exists
        $certificateTemplate = \App\Models\CertificateTemplate::where('acc_id', $acc->id)
            ->where('template_type', 'instructor')
            ->where('status', 'active')
            ->first();

        if (!$certificateTemplate) {
            return response()->json([
                'message' => 'Cannot approve instructor. Please create an active instructor certificate template first.',
                'errors' => [
                    'certificate_template' => ['An active instructor certificate template is required before approving instructors. Please create one in the certificate templates section.']
                ],
                'required_action' => 'create_instructor_template',
                'template_type' => 'instructor'
            ], 422);
        }

        // Get all pending requests for this instructor and ACC (merged requests)
        $allPendingRequests = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('instructor_id', $authorization->instructor_id)
            ->where('status', 'pending')
            ->orderBy('request_date', 'desc')
            ->get();

        // Collect all course IDs from all pending requests
        $allCourseIds = [];
        foreach ($allPendingRequests as $pendingReq) {
            $documentsData = $pendingReq->documents_json ?? [];
            $requestedCourseIds = $documentsData['requested_course_ids'] ?? [];
            $allCourseIds = array_unique(array_merge($allCourseIds, $requestedCourseIds));
        }

        // Get the main authorization (the one being approved or the latest pending)
        $mainAuthorization = $allPendingRequests->firstWhere('id', $authorization->id) 
            ?? $allPendingRequests->first();

        // Merge all course IDs into the main authorization's documents_json
        $mainDocumentsJson = $mainAuthorization->documents_json ?? [];
        $mainDocumentsJson['requested_course_ids'] = array_values($allCourseIds);
        
        // Approve only the main authorization request (this will be the one for commission)
        $mainAuthorization->update([
            'status' => 'approved',
            'authorization_price' => $request->authorization_price,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'group_admin_status' => 'pending', // Waiting for Group Admin to set commission
            'documents_json' => $mainDocumentsJson, // Include all merged course IDs
        ]);

        // Delete other pending requests to prevent duplicate commission requests
        // The main authorization contains all merged course IDs, so we don't need the others
        $otherPendingRequests = $allPendingRequests->where('id', '!=', $mainAuthorization->id);
        $otherPendingRequests->each(function ($pendingReq) {
            $pendingReq->delete();
        });

        // Update instructor status from pending to active if authorized by ACC
        $instructor = Instructor::find($mainAuthorization->instructor_id);
        if ($instructor && $instructor->status === 'pending') {
            $instructor->update(['status' => 'active']);
        }

        // Create InstructorCourseAuthorization records for all approved courses (merged from all requests)
        if (!empty($allCourseIds)) {
            foreach ($allCourseIds as $courseId) {
                \App\Models\InstructorCourseAuthorization::updateOrCreate(
                    [
                        'instructor_id' => $mainAuthorization->instructor_id,
                        'course_id' => $courseId,
                        'acc_id' => $mainAuthorization->acc_id,
                    ],
                    [
                        'authorized_at' => now(),
                        'authorized_by' => $user->id,
                        'status' => 'active',
                    ]
                );
            }
        }

        // Send notification to Group Admin to set commission percentage (use the main authorization)
        $mainAuthorization->refresh();
        $mainAuthorization->load(['instructor', 'acc', 'subCategory']);
        $instructor = $mainAuthorization->instructor;
        $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
        
        $notificationService = new NotificationService();
        $notificationService->notifyAdminInstructorNeedsCommission(
            $mainAuthorization->id,
            $instructorName,
            $acc->name,
            $request->authorization_price
        );
        
        // Enhanced notification data includes course count (merged from all requests)
        $coursesCount = count($allCourseIds);
        $subCategoryName = $mainAuthorization->subCategory?->name;
        
        // Also notify training center with enhanced details
        $trainingCenter = $mainAuthorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService->notifyInstructorAuthorized(
                    $trainingCenterUser->id,
                    $mainAuthorization->id,
                    $instructorName,
                    $acc->name,
                    $request->authorization_price,
                    null,
                    $coursesCount
                );
            }
        }

        return response()->json([
            'message' => 'Instructor approved successfully. Waiting for Group Admin to set commission percentage.',
            'authorization' => $mainAuthorization->fresh(),
            'courses_authorized' => count($allCourseIds),
            'merged_requests_approved' => $allPendingRequests->count()
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        // Get all pending requests for this instructor and ACC (merged requests)
        $allPendingRequests = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('instructor_id', $authorization->instructor_id)
            ->where('status', 'pending')
            ->get();

        // Reject all pending requests for this instructor
        $allPendingRequests->each(function ($pendingReq) use ($user, $request) {
            $pendingReq->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
        });

        // Send notification to Training Center
        $authorization->refresh();
        $authorization->load(['instructor', 'trainingCenter']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                $notificationService->notifyInstructorAuthorizationRejected(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $instructorName,
                    $request->rejection_reason
                );
            }
        }

        return response()->json([
            'message' => 'Instructor rejected',
            'merged_requests_rejected' => $allPendingRequests->count()
        ]);
    }

    public function return(Request $request, $id)
    {
        $request->validate([
            'return_comment' => 'required|string',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        // Get all pending requests for this instructor and ACC (merged requests)
        $allPendingRequests = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('instructor_id', $authorization->instructor_id)
            ->where('status', 'pending')
            ->get();

        // Return all pending requests for this instructor
        $allPendingRequests->each(function ($pendingReq) use ($user, $request) {
            $pendingReq->update([
                'status' => 'returned',
                'return_comment' => $request->return_comment,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
        });

        // Send notification to Training Center
        $authorization->refresh();
        $authorization->load(['instructor', 'trainingCenter', 'acc']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                $notificationService->notifyInstructorAuthorizationReturned(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $instructorName,
                    $acc->name,
                    $request->return_comment
                );
            }
        }

        return response()->json([
            'message' => 'Request returned successfully',
            'merged_requests_returned' => $allPendingRequests->count()
        ]);
    }

    #[OA\Get(
        path: "/acc/instructors",
        summary: "List approved authorized instructors",
        description: "Get all approved authorized instructors for the authenticated ACC with optional filtering, search, and pagination.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by instructor name, email, phone, ID number, country, city, or content in certificates/specializations"),
            new OA\Parameter(name: "country", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "USA", description: "Filter by country"),
            new OA\Parameter(name: "city", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "New York", description: "Filter by city"),
            new OA\Parameter(name: "is_assessor", in: "query", required: false, schema: new OA\Schema(type: "boolean"), example: false, description: "Filter by assessor status (true for Assessor, false for Instructor)"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Approved instructors retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "instructors", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(
                            property: "pagination",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer"),
                                new OA\Property(property: "last_page", type: "integer"),
                                new OA\Property(property: "per_page", type: "integer"),
                                new OA\Property(property: "total", type: "integer")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Get unique instructor IDs from approved authorizations
        $instructorIdsQuery = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->select('instructor_id')
            ->distinct();

        // Apply filters and search to instructor IDs
        $instructorIdsQuery->whereHas('instructor', function ($instructorQuery) use ($request) {
            // Filter by country
            if ($request->has('country') && !empty($request->country)) {
                $instructorQuery->where('country', 'like', "%{$request->country}%");
            }

            // Filter by city
            if ($request->has('city') && !empty($request->city)) {
                $instructorQuery->where('city', 'like', "%{$request->city}%");
            }

            // Filter by assessor status
            if ($request->has('is_assessor')) {
                $instructorQuery->where('is_assessor', $request->boolean('is_assessor'));
            }

            // Comprehensive search
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $instructorQuery->where(function($q) use ($searchTerm) {
                    $q->where('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('phone', 'like', "%{$searchTerm}%")
                        ->orWhere('id_number', 'like', "%{$searchTerm}%")
                        ->orWhere('country', 'like', "%{$searchTerm}%")
                        ->orWhere('city', 'like', "%{$searchTerm}%")
                        // Search in JSON fields
                        ->orWhereRaw("JSON_SEARCH(certificates_json, 'one', ?, NULL, '$[*].*') IS NOT NULL", ["%{$searchTerm}%"])
                        ->orWhereRaw("JSON_SEARCH(specializations, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$searchTerm}%"]);
                });
            }
        });

        // Also search in training center name
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $instructorIdsQuery->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                $tcQuery->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $instructorIds = $instructorIdsQuery->pluck('instructor_id')->toArray();

        if (empty($instructorIds)) {
            return response()->json([
                'instructors' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $request->get('per_page', 15),
                    'total' => 0,
                ],
            ]);
        }

        // Query unique instructors
        $instructorsQuery = Instructor::whereIn('id', $instructorIds)
            ->with([
                'trainingCenter',
                'courseAuthorizations' => function($q) use ($acc) {
                    $q->where('acc_id', $acc->id)->where('status', 'active');
                },
                'courseAuthorizations.course'
            ]);

        $perPage = $request->get('per_page', 15);
        $instructors = $instructorsQuery->orderBy('created_at', 'desc')->paginate($perPage);

        // Get ALL authorizations for these instructors (not just approved) to check for pending requests
        $allAuthorizations = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->whereIn('instructor_id', $instructors->pluck('id')->toArray())
            ->with(['subCategory.category', 'subCategory.courses', 'trainingCenter'])
            ->get()
            ->groupBy('instructor_id');

        // Transform the data to include instructor details with authorization info
        $instructorsData = $instructors->through(function ($instructor) use ($allAuthorizations, $acc) {
            $instructorAuthorizations = $allAuthorizations->get($instructor->id, collect());

            // Get all authorized courses for this instructor and ACC
            $authorizedCourses = $instructor->courseAuthorizations
                ->where('acc_id', $acc->id)
                ->where('status', 'active')
                ->map(function ($courseAuth) {
                    return [
                        'id' => $courseAuth->course_id,
                        'name' => $courseAuth->course->name ?? null,
                        'name_ar' => $courseAuth->course->name_ar ?? null,
                        'code' => $courseAuth->course->code ?? null,
                        'authorized_at' => $courseAuth->authorized_at?->toISOString(),
                    ];
                })
                ->values()
                ->unique('id')
                ->values();

            // Get approved authorizations only for the list
            $approvedAuthorizations = $instructorAuthorizations->where('status', 'approved');
            
            // Check if there are any pending requests - if yes, payment status should be pending
            $pendingAuthorizations = $instructorAuthorizations->where('status', 'pending');
            $hasPendingRequests = $pendingAuthorizations->isNotEmpty();
            
            // Determine payment status and get latest authorization details
            // Priority: If there's a pending request, use its status and show pending
            // Otherwise, use the latest approved authorization's details
            $overallPaymentStatus = 'pending';
            $latestPaymentDate = null;
            $overallCommissionPercentage = null;
            $overallAuthorizationPrice = null;
            $latestAuthorization = null;
            
            if ($hasPendingRequests) {
                // If there are pending requests, status is pending
                // Get the latest pending request
                $latestPending = $pendingAuthorizations->sortByDesc('request_date')->first();
                $overallPaymentStatus = 'pending';
                $latestAuthorization = $latestPending;
                // Pending requests don't have commission or payment yet
                $overallCommissionPercentage = null;
                $overallAuthorizationPrice = $latestPending->authorization_price;
            } else {
                // Otherwise, use the latest approved authorization's payment status and details
                $latestApproved = $approvedAuthorizations->sortByDesc('reviewed_at')->first();
                if ($latestApproved) {
                    $overallPaymentStatus = $latestApproved->payment_status ?? 'pending';
                    $latestPaymentDate = $latestApproved->payment_date;
                    $overallCommissionPercentage = $latestApproved->commission_percentage;
                    $overallAuthorizationPrice = $latestApproved->authorization_price;
                    $latestAuthorization = $latestApproved;
                }
            }

            // Build authorizations list - show all approved authorizations separately
            $authorizationsList = $approvedAuthorizations->map(function ($authorization) {
                $subCategoryData = null;
                $categoryData = null;
                
                if ($authorization->sub_category_id && $authorization->subCategory) {
                    $subCategory = $authorization->subCategory;
                    $category = $subCategory->category;
                    
                    $categoryData = $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'name_ar' => $category->name_ar ?? null,
                    ] : null;
                    
                    $subCategoryData = [
                        'id' => $subCategory->id,
                        'name' => $subCategory->name,
                        'name_ar' => $subCategory->name_ar,
                    ];
                }

                return [
                    'id' => $authorization->id,
                    'request_date' => $authorization->request_date?->toISOString(),
                    'reviewed_at' => $authorization->reviewed_at?->toISOString(),
                    'reviewed_by' => $authorization->reviewed_by,
                    'commission_percentage' => $authorization->commission_percentage,
                    'authorization_price' => $authorization->authorization_price,
                    'payment_status' => $authorization->payment_status,
                    'payment_date' => $authorization->payment_date?->toISOString(),
                    'group_admin_status' => $authorization->group_admin_status,
                    'category' => $categoryData,
                    'sub_category' => $subCategoryData,
                ];
            })->values()->reverse()->values();

            // Build latest authorization summary
            $latestAuthSummary = null;
            if ($latestAuthorization) {
                $subCategoryData = null;
                $categoryData = null;
                
                if ($latestAuthorization->sub_category_id && $latestAuthorization->subCategory) {
                    $subCategory = $latestAuthorization->subCategory;
                    $category = $subCategory->category;
                    
                    $categoryData = $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'name_ar' => $category->name_ar ?? null,
                    ] : null;
                    
                    $subCategoryData = [
                        'id' => $subCategory->id,
                        'name' => $subCategory->name,
                        'name_ar' => $subCategory->name_ar,
                    ];
                }

                $latestAuthSummary = [
                    'id' => $latestAuthorization->id,
                    'request_date' => $latestAuthorization->request_date?->toISOString(),
                    'reviewed_at' => $latestAuthorization->reviewed_at?->toISOString(),
                    'reviewed_by' => $latestAuthorization->reviewed_by,
                    'commission_percentage' => $overallCommissionPercentage ?? $latestAuthorization->commission_percentage,
                    'authorization_price' => $overallAuthorizationPrice ?? $latestAuthorization->authorization_price,
                    'payment_status' => $overallPaymentStatus,
                    'payment_date' => $latestPaymentDate?->toISOString(),
                    'group_admin_status' => $latestAuthorization->group_admin_status,
                ];
            }

            return [
                'id' => $instructor->id,
                'first_name' => $instructor->first_name,
                'last_name' => $instructor->last_name,
                'email' => $instructor->email,
                'phone' => $instructor->phone,
                'date_of_birth' => $instructor->date_of_birth?->format('Y-m-d'),
                'id_number' => $instructor->id_number,
                'country' => $instructor->country,
                'city' => $instructor->city,
                'cv_url' => $instructor->cv_url,
                'passport_image_url' => $instructor->passport_image_url,
                'photo_url' => $instructor->photo_url,
                'certificates_json' => $instructor->certificates_json,
                'specializations' => $instructor->specializations,
                'status' => $instructor->status,
                'is_assessor' => $instructor->is_assessor,
                'training_center' => $instructor->trainingCenter ? [
                    'id' => $instructor->trainingCenter->id,
                    'name' => $instructor->trainingCenter->name,
                    'email' => $instructor->trainingCenter->email,
                ] : null,
                'latest_authorization' => $latestAuthSummary,
                'authorizations' => $authorizationsList,
                'authorized_courses' => $authorizedCourses,
                'authorizations_count' => $authorizationsList->count(),
            ];
        });

        return response()->json([
            'instructors' => $instructorsData->values(),
            'pagination' => [
                'current_page' => $instructors->currentPage(),
                'last_page' => $instructors->lastPage(),
                'per_page' => $instructors->perPage(),
                'total' => $instructors->total(),
            ],
        ]);
    }
}

