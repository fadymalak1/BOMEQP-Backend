<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class InstructorController extends Controller
{
    #[OA\Get(
        path: "/admin/instructors",
        summary: "List all instructors",
        description: "Get all instructors in the system with optional filtering and pagination.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["pending", "active", "suspended", "inactive"]), example: "active", description: "Filter by instructor status"),
            new OA\Parameter(name: "training_center_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1, description: "Filter by training center ID"),
            new OA\Parameter(name: "country", in: "query", schema: new OA\Schema(type: "string"), example: "USA", description: "Filter by country"),
            new OA\Parameter(name: "city", in: "query", schema: new OA\Schema(type: "string"), example: "New York", description: "Filter by city"),
            new OA\Parameter(name: "is_assessor", in: "query", schema: new OA\Schema(type: "boolean"), example: false, description: "Filter by assessor status (true for Assessor, false for Instructor)"),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), example: "John Doe", description: "Search by name, email, phone, ID number, country, city, or content in certificates/specializations"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Instructors retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "instructors", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "statistics", type: "object", properties: [
                            new OA\Property(property: "total", type: "integer", example: 200),
                            new OA\Property(property: "pending", type: "integer", example: 20),
                            new OA\Property(property: "active", type: "integer", example: 150),
                            new OA\Property(property: "suspended", type: "integer", example: 15),
                            new OA\Property(property: "inactive", type: "integer", example: 15)
                        ]),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = Instructor::with(['trainingCenter', 'authorizations', 'courseAuthorizations']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by training center
        if ($request->has('training_center_id')) {
            $query->where('training_center_id', $request->training_center_id);
        }

        // Filter by country
        if ($request->has('country') && !empty($request->country)) {
            $query->where('country', 'like', "%{$request->country}%");
        }

        // Filter by city
        if ($request->has('city') && !empty($request->city)) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        // Filter by assessor status
        if ($request->has('is_assessor')) {
            $query->where('is_assessor', $request->boolean('is_assessor'));
        }

        // Comprehensive search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Search in basic fields
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  // Search in JSON fields (certificates_json and specializations)
                  ->orWhereRaw("JSON_SEARCH(certificates_json, 'one', ?, NULL, '$[*].*') IS NOT NULL", ["%{$search}%"])
                  ->orWhereRaw("JSON_SEARCH(specializations, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$search}%"]);
            });
        }

        $instructors = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        // Get statistics (total counts regardless of filters)
        $statistics = [
            'total' => Instructor::count(),
            'pending' => Instructor::where('status', 'pending')->count(),
            'active' => Instructor::where('status', 'active')->count(),
            'suspended' => Instructor::where('status', 'suspended')->count(),
            'inactive' => Instructor::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'instructors' => $instructors->items(),
            'statistics' => $statistics,
            'pagination' => [
                'current_page' => $instructors->currentPage(),
                'last_page' => $instructors->lastPage(),
                'per_page' => $instructors->perPage(),
                'total' => $instructors->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/admin/instructors/{id}",
        summary: "Get instructor details",
        description: "Get detailed information about a specific instructor including training center, authorizations, course authorizations, classes, and certificates.",
        tags: ["Admin"],
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
        $instructor = Instructor::with([
            'trainingCenter',
            'authorizations.acc',
            'courseAuthorizations.course',
            'trainingClasses.course',
            'certificates'
        ])->findOrFail($id);

        return response()->json(['instructor' => $instructor]);
    }

    #[OA\Put(
        path: "/admin/instructors/{id}",
        summary: "Update instructor",
        description: "Update instructor information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "first_name", type: "string", nullable: true),
                    new OA\Property(property: "last_name", type: "string", nullable: true),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                    new OA\Property(property: "phone", type: "string", nullable: true),
                    new OA\Property(property: "id_number", type: "string", nullable: true),
                    new OA\Property(property: "cv_url", type: "string", nullable: true),
                    new OA\Property(property: "certificates_json", type: "array", nullable: true, items: new OA\Items(type: "object")),
                    new OA\Property(property: "specializations", type: "array", nullable: true, items: new OA\Items(type: "string")),
                    new OA\Property(property: "is_assessor", type: "boolean", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["pending", "active", "suspended", "inactive"], nullable: true)
                ]
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
        $instructor = Instructor::findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:instructors,email,' . $id,
            'phone' => 'sometimes|string|max:255',
            'id_number' => 'sometimes|string|max:255|unique:instructors,id_number,' . $id,
            'cv_url' => 'nullable|string|max:255',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
            'is_assessor' => 'nullable|boolean',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
        ]);

        $updateData = $request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'id_number',
            'cv_url',
            'certificates_json',
            'specializations',
            'status',
        ]);
        
        // Handle boolean conversion for is_assessor
        if ($request->has('is_assessor')) {
            $updateData['is_assessor'] = $request->boolean('is_assessor');
        }
        
        $oldStatus = $instructor->status;
        $instructor->update($updateData);
        $newStatus = $instructor->status;

        // Notify instructor if status changed
        if ($oldStatus !== $newStatus && in_array($newStatus, ['suspended', 'active', 'inactive'])) {
            $instructorUser = \App\Models\User::where('email', $instructor->email)->first();
            if ($instructorUser) {
                $instructor->load('trainingCenter');
                $trainingCenterName = $instructor->trainingCenter?->name ?? 'Unknown';
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                $notificationService = new NotificationService();
                $notificationService->notifyInstructorStatusChanged(
                    $instructorUser->id,
                    $instructor->id,
                    $instructorName,
                    $trainingCenterName,
                    $oldStatus,
                    $newStatus,
                    $request->status_change_reason ?? null
                );
            }
        }

        return response()->json([
            'message' => 'Instructor updated successfully',
            'instructor' => $instructor->fresh()->load(['trainingCenter', 'authorizations', 'courseAuthorizations'])
        ], 200);
    }

    #[OA\Put(
        path: "/admin/instructors/authorizations/{id}/set-commission",
        summary: "Set instructor commission",
        description: "Set commission percentage for instructor authorization. Called after ACC Admin approves and sets authorization price.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["commission_percentage"],
                properties: [
                    new OA\Property(property: "commission_percentage", type: "number", format: "float", example: 10.0, minimum: 0, maximum: 100)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Commission set successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Commission set successfully"),
                        new OA\Property(property: "authorization", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Authorization must be approved by ACC Admin first"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Authorization not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function setInstructorCommission(Request $request, $id)
    {
        $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $authorization = InstructorAccAuthorization::findOrFail($id);

        // Verify authorization is approved by ACC and waiting for commission
        if ($authorization->status !== 'approved' || $authorization->group_admin_status !== 'pending') {
            return response()->json([
                'message' => 'Authorization must be approved by ACC Admin first and waiting for commission setting'
            ], 400);
        }

        // Get all approved requests for this instructor waiting for commission (merged requests)
        $allPendingCommissionRequests = InstructorAccAuthorization::where('instructor_id', $authorization->instructor_id)
            ->where('status', 'approved')
            ->where('group_admin_status', 'pending')
            ->whereNotNull('authorization_price')
            ->get();

        // Set commission for all merged requests
        $allPendingCommissionRequests->each(function ($req) use ($request) {
            $req->update([
                'commission_percentage' => $request->commission_percentage,
                'group_admin_status' => 'commission_set',
                'group_commission_set_by' => $request->user()->id,
                'group_commission_set_at' => now(),
            ]);
        });

        // Collect all course IDs from all merged requests
        $allCourseIds = [];
        foreach ($allPendingCommissionRequests as $req) {
            $documentsData = $req->documents_json ?? [];
            $courseIds = $documentsData['requested_course_ids'] ?? [];
            $allCourseIds = array_unique(array_merge($allCourseIds, $courseIds));
        }

        // Send notification to Training Center to complete payment with enhanced details (use main authorization)
        $authorization->refresh();
        $authorization->load(['instructor', 'trainingCenter', 'acc', 'subCategory']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = \App\Models\User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                // Use merged course count
                $coursesCount = count($allCourseIds);
                
                // Use authorization_price from the main authorization (not sum) since all merged requests have the same price
                $notificationService->notifyInstructorCommissionSet(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $instructorName,
                    $authorization->acc->name,
                    $authorization->authorization_price ?? 0,
                    $request->commission_percentage,
                    $coursesCount
                );
            }
        }

        return response()->json([
            'message' => 'Commission percentage set successfully. Training Center can now complete payment.',
            'authorization' => $authorization->fresh()->load(['instructor', 'acc', 'trainingCenter']),
            'merged_requests_commission_set' => $allPendingCommissionRequests->count()
        ], 200);
    }

    #[OA\Get(
        path: "/admin/instructor-authorizations/pending-commission",
        summary: "Get pending commission requests",
        description: "Get instructor authorization requests that are approved by ACC Admin and waiting for commission setting by Group Admin. Multiple approved requests for the same instructor are automatically merged into one request with combined course IDs. The authorization price and commission remain the same as the individual requests (not summed).",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by authorization ID, instructor name (first, last, or full name), ACC name, or training center name"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15), example: 15),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Pending commission requests retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "authorizations", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 5),
                        new OA\Property(property: "last_page", type: "integer", example: 1)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function pendingCommissionRequests(Request $request)
    {
        // Get all approved requests waiting for commission
        $allRequests = InstructorAccAuthorization::where('status', 'approved')
            ->where('group_admin_status', 'pending')
            ->whereNotNull('authorization_price')
            ->with(['instructor', 'acc', 'trainingCenter', 'subCategory.category', 'subCategory.courses'])
            ->orderBy('reviewed_at', 'desc')
            ->get();

        // Group by instructor_id and merge multiple requests for the same instructor
        $grouped = $allRequests->groupBy('instructor_id');
        $mergedRequests = $grouped->map(function ($requests) {
            // Get the latest request (for most recent data like relationships)
            $latest = $requests->first();
            
            // Collect all requested course IDs from all requests for this instructor
            $allRequestedCourseIds = [];
            $requestIds = [];
            
            foreach ($requests as $req) {
                $documentsData = $req->documents_json ?? [];
                $requestedCourseIds = $documentsData['requested_course_ids'] ?? [];
                
                // Merge course IDs (avoid duplicates)
                $allRequestedCourseIds = array_unique(array_merge($allRequestedCourseIds, $requestedCourseIds));
                
                // Track all request IDs
                $requestIds[] = $req->id;
            }
            
            // Process merged request data using latest request as base
            // Use authorization_price from latest (not sum) since all merged requests have the same price
            $data = $latest->toArray();
            
            // Update documents_json to include merged course IDs
            $mergedDocumentsJson = $latest->documents_json ?? [];
            $mergedDocumentsJson['requested_course_ids'] = array_values($allRequestedCourseIds);
            $data['documents_json'] = $mergedDocumentsJson;
            
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

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $mergedRequests = $mergedRequests->filter(function ($req) use ($searchTerm) {
                $instructor = $req['instructor'] ?? null;
                $acc = $req['acc'] ?? null;
                $trainingCenter = $req['training_center'] ?? null;
                
                return stripos((string)$req['id'], $searchTerm) !== false
                    || ($instructor && (
                        stripos($instructor['first_name'] ?? '', $searchTerm) !== false
                        || stripos($instructor['last_name'] ?? '', $searchTerm) !== false
                        || stripos($instructor['email'] ?? '', $searchTerm) !== false
                    ))
                    || ($acc && (
                        stripos($acc['name'] ?? '', $searchTerm) !== false
                        || stripos($acc['email'] ?? '', $searchTerm) !== false
                    ))
                    || ($trainingCenter && (
                        stripos($trainingCenter['name'] ?? '', $searchTerm) !== false
                        || stripos($trainingCenter['email'] ?? '', $searchTerm) !== false
                    ));
            })->values();
        }

        // Paginate the merged results
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $total = $mergedRequests->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $mergedRequests->slice($offset, $perPage)->values();

        return response()->json([
            'authorizations' => $paginated,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ], 200);
    }
}

