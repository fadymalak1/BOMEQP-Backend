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
                        new OA\Property(property: "last_page", type: "integer")
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

        $query = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->with(['instructor', 'trainingCenter', 'subCategory.category', 'subCategory.courses']);

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

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'like', "%{$searchTerm}%")
                    ->orWhereHas('instructor', function ($instructorQuery) use ($searchTerm) {
                        $instructorQuery->where('first_name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                            ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$searchTerm}%"])
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                        $tcQuery->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->orderBy('request_date', 'desc')
            ->paginate($perPage)
            ->through(function ($authorization) {
                $data = $authorization->toArray();
                
                // Get requested course IDs from documents_json
                $documentsData = $authorization->documents_json ?? [];
                $requestedCourseIds = $documentsData['requested_course_ids'] ?? [];
                
                // If there is a sub_category, add sub_category name, category, and courses
                if ($authorization->sub_category_id && $authorization->subCategory) {
                    $subCategory = $authorization->subCategory;
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
                
                return $data;
            });

        return response()->json($requests);
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
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by instructor full name (first name, last name, or both), email, phone, or training center name"),
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

        // Apply search filter to instructor IDs if search is provided
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $instructorIdsQuery->whereHas('instructor', function ($instructorQuery) use ($searchTerm) {
                $instructorQuery->where('first_name', 'like', "%{$searchTerm}%")
                    ->orWhere('last_name', 'like', "%{$searchTerm}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                    ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$searchTerm}%"])
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    ->orWhere('phone', 'like', "%{$searchTerm}%");
            })
            ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
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

