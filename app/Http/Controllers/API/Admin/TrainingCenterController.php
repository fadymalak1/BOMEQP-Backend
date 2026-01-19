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
            // Company Information
            'name' => 'sometimes|string|max:255',
            'website' => 'nullable|string|url|max:255',
            'email' => 'sometimes|email|max:255|unique:training_centers,email,' . $id,
            'phone' => 'sometimes|string|max:255',
            'fax' => 'nullable|string|max:255',
            'training_provider_type' => 'sometimes|in:Training Center,Institute,University',
            // Physical Address
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'physical_postal_code' => 'sometimes|string|max:255',
            // Mailing Address
            'mailing_same_as_physical' => 'sometimes|boolean',
            'mailing_address' => 'nullable|string|required_if:mailing_same_as_physical,false',
            'mailing_city' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
            'mailing_country' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
            'mailing_postal_code' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
            // Primary Contact
            'primary_contact_title' => 'sometimes|in:Mr.,Mrs.,Eng.,Prof.',
            'primary_contact_first_name' => 'sometimes|string|max:255',
            'primary_contact_last_name' => 'sometimes|string|max:255',
            'primary_contact_email' => 'sometimes|email|max:255',
            'primary_contact_country' => 'sometimes|string|max:255',
            'primary_contact_mobile' => 'sometimes|string|max:255',
            // Secondary Contact
            'has_secondary_contact' => 'sometimes|boolean',
            'secondary_contact_title' => 'nullable|in:Mr.,Mrs.,Eng.,Prof.|required_if:has_secondary_contact,true',
            'secondary_contact_first_name' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_last_name' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_email' => 'nullable|email|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_country' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            'secondary_contact_mobile' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
            // Additional Information
            'company_gov_registry_number' => 'sometimes|string|max:255',
            'company_registration_certificate_url' => 'nullable|string|url|max:500',
            'facility_floorplan_url' => 'nullable|string|url|max:500',
            'interested_fields' => 'nullable|array',
            'interested_fields.*' => 'string|in:QHSE,Food Safety,Management',
            'how_did_you_hear_about_us' => 'nullable|string',
            // Legacy fields
            'legal_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:255|unique:training_centers,registration_number,' . $id,
            'logo_url' => 'nullable|string|url|max:500',
            'referred_by_group' => 'sometimes|boolean',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
        ]);

        $oldStatus = $trainingCenter->status;
        
        // Handle mailing address - if same as physical, copy physical address fields
        $updateData = [];
        if ($request->has('mailing_same_as_physical') && $request->mailing_same_as_physical) {
            $updateData['mailing_same_as_physical'] = true;
            $updateData['mailing_address'] = $request->input('address', $trainingCenter->address);
            $updateData['mailing_city'] = $request->input('city', $trainingCenter->city);
            $updateData['mailing_country'] = $request->input('country', $trainingCenter->country);
            $updateData['mailing_postal_code'] = $request->input('physical_postal_code', $trainingCenter->physical_postal_code);
        }

        // Get all fillable fields from request
        $fillableFields = [
            'name', 'legal_name', 'registration_number', 'country', 'city', 'address',
            'phone', 'email', 'website', 'fax', 'training_provider_type',
            'physical_postal_code',
            'mailing_same_as_physical', 'mailing_address', 'mailing_city', 'mailing_country', 'mailing_postal_code',
            'primary_contact_title', 'primary_contact_first_name', 'primary_contact_last_name',
            'primary_contact_email', 'primary_contact_country', 'primary_contact_mobile',
            'has_secondary_contact', 'secondary_contact_title', 'secondary_contact_first_name',
            'secondary_contact_last_name', 'secondary_contact_email', 'secondary_contact_country', 'secondary_contact_mobile',
            'company_gov_registry_number', 'company_registration_certificate_url', 'facility_floorplan_url',
            'interested_fields', 'how_did_you_hear_about_us',
            'logo_url', 'referred_by_group', 'status',
        ];

        foreach ($fillableFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

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

