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
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["pending", "active", "suspended", "inactive"]), example: "active"),
            new OA\Parameter(name: "training_center_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), example: "John Doe"),
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

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('training_center_id')) {
            $query->where('training_center_id', $request->training_center_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        $instructors = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'instructors' => $instructors->items(),
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

        $authorization->update([
            'commission_percentage' => $request->commission_percentage,
            'group_admin_status' => 'commission_set',
            'group_commission_set_by' => $request->user()->id,
            'group_commission_set_at' => now(),
        ]);

        // Send notification to Training Center to complete payment with enhanced details
        $authorization->load(['instructor', 'trainingCenter', 'acc', 'subCategory']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = \App\Models\User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                // Get course count from documents_json
                $documentsData = $authorization->documents_json ?? [];
                $courseIds = $documentsData['requested_course_ids'] ?? [];
                $coursesCount = count($courseIds);
                
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
            'authorization' => $authorization->fresh()->load(['instructor', 'acc', 'trainingCenter'])
        ], 200);
    }

    #[OA\Get(
        path: "/admin/instructor-authorizations/pending-commission",
        summary: "Get pending commission requests",
        description: "Get instructor authorization requests that are approved by ACC Admin and waiting for commission setting by Group Admin, with pagination and search.",
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
        $query = InstructorAccAuthorization::where('status', 'approved')
            ->where('group_admin_status', 'pending')
            ->whereNotNull('authorization_price')
            ->with(['instructor', 'acc', 'trainingCenter']);

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
                    ->orWhereHas('acc', function ($accQuery) use ($searchTerm) {
                        $accQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                        $tcQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $authorizations = $query->orderBy('reviewed_at', 'desc')->paginate($perPage);

        return response()->json([
            'authorizations' => $authorizations->items(),
            'current_page' => $authorizations->currentPage(),
            'per_page' => $authorizations->perPage(),
            'total' => $authorizations->total(),
            'last_page' => $authorizations->lastPage(),
        ], 200);
    }
}

