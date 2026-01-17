<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrainingCenterController extends Controller
{
    #[OA\Get(
        path: "/admin/training-centers",
        summary: "Get all training centers",
        description: "Get a list of all training centers with optional filters.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "active", "suspended", "inactive"])),
            new OA\Parameter(name: "country", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training centers retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "training_centers", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "statistics", type: "object", properties: [
                            new OA\Property(property: "total", type: "integer", example: 100),
                            new OA\Property(property: "pending", type: "integer", example: 10),
                            new OA\Property(property: "active", type: "integer", example: 70),
                            new OA\Property(property: "suspended", type: "integer", example: 10),
                            new OA\Property(property: "inactive", type: "integer", example: 10)
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
        $query = TrainingCenter::with(['wallet', 'instructors']);

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $trainingCenters = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        // Get statistics (total counts regardless of filters)
        $statistics = [
            'total' => TrainingCenter::count(),
            'pending' => TrainingCenter::where('status', 'pending')->count(),
            'active' => TrainingCenter::where('status', 'active')->count(),
            'suspended' => TrainingCenter::where('status', 'suspended')->count(),
            'inactive' => TrainingCenter::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'training_centers' => $trainingCenters->items(),
            'statistics' => $statistics,
            'pagination' => [
                'current_page' => $trainingCenters->currentPage(),
                'last_page' => $trainingCenters->lastPage(),
                'per_page' => $trainingCenters->perPage(),
                'total' => $trainingCenters->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/admin/training-centers/{id}",
        summary: "Get training center details",
        description: "Get detailed information about a specific training center including wallet, instructors, authorizations, certificates, and classes.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training center retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function show($id)
    {
        $trainingCenter = TrainingCenter::with([
            'wallet',
            'instructors',
            'authorizations.acc',
            'certificates',
            'trainingClasses'
        ])->findOrFail($id);

        return response()->json(['training_center' => $trainingCenter]);
    }

    #[OA\Put(
        path: "/admin/training-centers/{id}",
        summary: "Update training center",
        description: "Update training center information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "legal_name", type: "string", nullable: true),
                    new OA\Property(property: "registration_number", type: "string", nullable: true),
                    new OA\Property(property: "country", type: "string", nullable: true),
                    new OA\Property(property: "city", type: "string", nullable: true),
                    new OA\Property(property: "address", type: "string", nullable: true),
                    new OA\Property(property: "phone", type: "string", nullable: true),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                    new OA\Property(property: "website", type: "string", nullable: true),
                    new OA\Property(property: "logo_url", type: "string", nullable: true),
                    new OA\Property(property: "referred_by_group", type: "boolean", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["pending", "active", "suspended", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Training center updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Training center updated successfully"),
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $trainingCenter = TrainingCenter::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:255|unique:training_centers,registration_number,' . $id,
            'country' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:training_centers,email,' . $id,
            'website' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:255',
            'referred_by_group' => 'sometimes|boolean',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
        ]);

        $oldStatus = $trainingCenter->status;
        $updateData = $request->only([
            'name',
            'legal_name',
            'registration_number',
            'country',
            'city',
            'address',
            'phone',
            'email',
            'website',
            'logo_url',
            'referred_by_group',
            'status',
        ]);

        $trainingCenter->update($updateData);
        $newStatus = $trainingCenter->status;

        // Notify Training Center admin if status changed
        if ($oldStatus !== $newStatus && in_array($newStatus, ['suspended', 'active', 'inactive'])) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->where('role', 'training_center_admin')->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterStatusChanged(
                    $trainingCenterUser->id,
                    $trainingCenter->id,
                    $trainingCenter->name,
                    $oldStatus,
                    $newStatus,
                    $request->status_change_reason ?? null
                );
            }
        }

        return response()->json([
            'message' => 'Training center updated successfully',
            'training_center' => $trainingCenter->fresh()
        ], 200);
    }

    #[OA\Get(
        path: "/admin/training-centers/applications",
        summary: "Get training center applications",
        description: "Get all pending training center applications for review.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Applications retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "applications", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function applications()
    {
        $applications = TrainingCenter::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['applications' => $applications]);
    }

    #[OA\Put(
        path: "/admin/training-centers/applications/{id}/approve",
        summary: "Approve training center application",
        description: "Approve a training center application.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Application approved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Training center application approved"),
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Application not found")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $trainingCenter = TrainingCenter::findOrFail($id);
        
        if ($trainingCenter->status !== 'pending') {
            return response()->json([
                'message' => 'Training center application is not pending',
            ], 400);
        }

        $trainingCenter->update([
            'status' => 'active',
        ]);

        // Also activate the user account associated with this training center
        $user = User::where('email', $trainingCenter->email)->first();
        if ($user && $user->role === 'training_center_admin') {
            $user->update(['status' => 'active']);
            
            // Send notification to training center admin
            $notificationService = new NotificationService();
            $notificationService->notifyTrainingCenterApproved($user->id, $trainingCenter->id, $trainingCenter->name);
        }

        return response()->json([
            'message' => 'Training center application approved',
            'training_center' => $trainingCenter->fresh()
        ]);
    }

    #[OA\Put(
        path: "/admin/training-centers/applications/{id}/reject",
        summary: "Reject training center application",
        description: "Reject a training center application with a reason.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["rejection_reason"],
                properties: [
                    new OA\Property(property: "rejection_reason", type: "string", example: "Incomplete documentation")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Application rejected successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Training center application rejected"),
                        new OA\Property(property: "training_center", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Application not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $trainingCenter = TrainingCenter::findOrFail($id);
        
        if ($trainingCenter->status !== 'pending') {
            return response()->json([
                'message' => 'Training center application is not pending',
            ], 400);
        }

        $trainingCenter->update([
            'status' => 'inactive',
        ]);

        // Send notification to training center admin
        $user = User::where('email', $trainingCenter->email)->first();
        if ($user && $user->role === 'training_center_admin') {
            $notificationService = new NotificationService();
            $notificationService->notifyTrainingCenterRejected($user->id, $trainingCenter->id, $trainingCenter->name, $request->rejection_reason);
        }

        return response()->json([
            'message' => 'Training center application rejected',
            'training_center' => $trainingCenter->fresh(),
        ]);
    }
}

