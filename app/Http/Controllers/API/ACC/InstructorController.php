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
        summary: "List instructor authorization requests",
        description: "Get all instructor authorization requests for the authenticated ACC with pagination and search.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by instructor full name (first name, last name, or both), email, training center name, or request ID"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "approved", "rejected", "returned"]), description: "Filter by request status"),
            new OA\Parameter(name: "payment_status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "paid", "failed"]), description: "Filter by payment status"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Requests retrieved successfully",
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
                                new OA\Property(property: "total", type: "integer", example: 50, description: "Total number of authorization requests for this ACC"),
                                new OA\Property(property: "pending", type: "integer", example: 10, description: "Number of pending requests"),
                                new OA\Property(property: "approved", type: "integer", example: 25, description: "Number of approved requests"),
                                new OA\Property(property: "rejected", type: "integer", example: 10, description: "Number of rejected requests"),
                                new OA\Property(property: "returned", type: "integer", example: 5, description: "Number of returned requests")
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

        // Get all requests with relationships
        $allRequests = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->with(['instructor', 'trainingCenter', 'subCategory.category', 'subCategory.courses'])
            ->orderBy('request_date', 'desc')
            ->get();

        // Group by instructor_id and get latest request for each
        $grouped = $allRequests->groupBy('instructor_id');
        $latestRequests = $grouped->map(function ($requests) {
            // Get the latest request (first one since we ordered by request_date desc)
            $latest = $requests->first();
            
            // Get all previous requests (excluding the latest)
            $previous = $requests->slice(1)->values();
            
            // Process latest request data
            $data = $latest->toArray();
            
            // Get requested course IDs from documents_json
            $documentsData = $latest->documents_json ?? [];
            $requestedCourseIds = $documentsData['requested_course_ids'] ?? [];
            
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
            
            // Get requested courses by IDs (even if sub_category is null)
            $requestedCourses = [];
            if (!empty($requestedCourseIds)) {
                $courses = \App\Models\Course::whereIn('id', $requestedCourseIds)->get();
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
            
            // Add previous requests info to the latest request
            $data['previous_requests'] = $previous->map(function ($req) {
                $documentsData = $req->documents_json ?? [];
                $requestedCourseIds = $documentsData['requested_course_ids'] ?? [];
                
                return [
                    'id' => $req->id,
                    'request_date' => $req->request_date,
                    'status' => $req->status,
                    'payment_status' => $req->payment_status,
                    'authorization_price' => $req->authorization_price,
                    'rejection_reason' => $req->rejection_reason,
                    'return_comment' => $req->return_comment,
                    'reviewed_by' => $req->reviewed_by,
                    'reviewed_at' => $req->reviewed_at,
                    'requested_courses_count' => count($requestedCourseIds),
                    'documents_count' => is_array($req->documents_json) ? count($req->documents_json) : 0,
                ];
            })->toArray();
            
            $data['total_requests_count'] = $requests->count();
            
            return $data;
        })->values();

        // Filter by status if provided (filter latest requests by their status)
        if ($request->has('status')) {
            $validStatuses = ['pending', 'approved', 'rejected', 'returned'];
            if (in_array($request->status, $validStatuses)) {
                $latestRequests = $latestRequests->where('status', $request->status)->values();
            }
        }

        // Filter by payment_status if provided (filter latest requests by their payment_status)
        if ($request->has('payment_status')) {
            $validPaymentStatuses = ['pending', 'paid', 'failed'];
            if (in_array($request->payment_status, $validPaymentStatuses)) {
                $latestRequests = $latestRequests->where('payment_status', $request->payment_status)->values();
            }
        }

        // Search functionality (search in latest requests)
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $latestRequests = $latestRequests->filter(function ($req) use ($searchTerm) {
                // Access instructor and trainingCenter from the array
                $instructor = $req['instructor'] ?? null;
                $trainingCenter = $req['training_center'] ?? null;
                
                return stripos((string)$req['id'], $searchTerm) !== false
                    || ($instructor && (
                        stripos($instructor['first_name'] ?? '', $searchTerm) !== false
                        || stripos($instructor['last_name'] ?? '', $searchTerm) !== false
                        || stripos($instructor['email'] ?? '', $searchTerm) !== false
                    ))
                    || ($trainingCenter && stripos($trainingCenter['name'] ?? '', $searchTerm) !== false);
            })->values();
        }

        // Paginate the grouped results
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $total = $latestRequests->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $latestRequests->slice($offset, $perPage)->values();

        // Calculate statistics for all requests for this ACC (not just paginated results)
        $statistics = [
            'total' => $allRequests->count(),
            'pending' => $allRequests->where('status', 'pending')->count(),
            'approved' => $allRequests->where('status', 'approved')->count(),
            'rejected' => $allRequests->where('status', 'rejected')->count(),
            'returned' => $allRequests->where('status', 'returned')->count(),
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

        $authorization->update([
            'status' => 'approved',
            'authorization_price' => $request->authorization_price,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'group_admin_status' => 'pending', // Waiting for Group Admin to set commission
        ]);

        // Update instructor status from pending to active if authorized by ACC
        $instructor = Instructor::find($authorization->instructor_id);
        if ($instructor && $instructor->status === 'pending') {
            $instructor->update(['status' => 'active']);
        }

        // Get course IDs from documents_json
        $documentsData = $authorization->documents_json ?? [];
        $courseIds = $documentsData['requested_course_ids'] ?? [];

        // Create InstructorCourseAuthorization records for all approved courses
        if (!empty($courseIds)) {
            foreach ($courseIds as $courseId) {
                \App\Models\InstructorCourseAuthorization::updateOrCreate(
                    [
                        'instructor_id' => $authorization->instructor_id,
                        'course_id' => $courseId,
                        'acc_id' => $authorization->acc_id,
                    ],
                    [
                        'authorized_at' => now(),
                        'authorized_by' => $user->id,
                        'status' => 'active',
                    ]
                );
            }
        }

        // Send notification to Group Admin to set commission percentage
        $authorization->load(['instructor', 'acc', 'subCategory']);
        $instructor = $authorization->instructor;
        $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
        
        $notificationService = new NotificationService();
        $notificationService->notifyAdminInstructorNeedsCommission(
            $authorization->id,
            $instructorName,
            $acc->name,
            $request->authorization_price
        );
        
        // Enhanced notification data includes course count
        $coursesCount = count($courseIds);
        $subCategoryName = $authorization->subCategory?->name;
        
        // Also notify training center with enhanced details
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService->notifyInstructorAuthorized(
                    $trainingCenterUser->id,
                    $authorization->id,
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
            'authorization' => $authorization->fresh(),
            'courses_authorized' => count($courseIds)
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

        $authorization->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
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

        return response()->json(['message' => 'Instructor rejected']);
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

        $authorization->update([
            'status' => 'returned',
            'return_comment' => $request->return_comment,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
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

        return response()->json(['message' => 'Request returned successfully']);
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

        // Get all approved authorizations for these instructors
        $allAuthorizations = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->whereIn('instructor_id', $instructors->pluck('id')->toArray())
            ->with(['subCategory.category', 'subCategory.courses', 'trainingCenter'])
            ->get()
            ->groupBy('instructor_id');

        // Transform the data to include instructor details with aggregated authorization info
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

            // Aggregate authorization information
            $authorizationsList = $instructorAuthorizations->map(function ($authorization) {
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
            })->values();

            // Get the latest authorization for summary
            $latestAuthorization = $instructorAuthorizations->sortByDesc('reviewed_at')->first();

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
                'latest_authorization' => $latestAuthorization ? [
                    'id' => $latestAuthorization->id,
                    'request_date' => $latestAuthorization->request_date?->toISOString(),
                    'reviewed_at' => $latestAuthorization->reviewed_at?->toISOString(),
                    'reviewed_by' => $latestAuthorization->reviewed_by,
                    'commission_percentage' => $latestAuthorization->commission_percentage,
                    'authorization_price' => $latestAuthorization->authorization_price,
                    'payment_status' => $latestAuthorization->payment_status,
                    'payment_date' => $latestAuthorization->payment_date?->toISOString(),
                    'group_admin_status' => $latestAuthorization->group_admin_status,
                ] : null,
                'authorizations' => $authorizationsList,
                'authorized_courses' => $authorizedCourses,
                'authorizations_count' => $instructorAuthorizations->count(),
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

