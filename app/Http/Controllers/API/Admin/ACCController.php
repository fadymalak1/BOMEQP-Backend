<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Category;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ACCController extends Controller
{
    #[OA\Get(
        path: "/admin/accs/applications",
        summary: "Get ACC applications",
        description: "Get all pending ACC applications for review.",
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
        $applications = ACC::where('status', 'pending')->with('documents')->get();
        return response()->json(['applications' => $applications]);
    }

    #[OA\Get(
        path: "/admin/accs/applications/{id}",
        summary: "Get ACC application details",
        description: "Get detailed information about a specific ACC application.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Application retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "application", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Application not found")
        ]
    )]
    public function showApplication($id)
    {
        $application = ACC::with('documents')->findOrFail($id);
        return response()->json(['application' => $application]);
    }

    #[OA\Put(
        path: "/admin/accs/applications/{id}/approve",
        summary: "Approve ACC application",
        description: "Approve an ACC application and activate the associated user account.",
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
                        new OA\Property(property: "message", type: "string", example: "ACC application approved"),
                        new OA\Property(property: "acc", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Application not found")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $acc = ACC::findOrFail($id);
        $acc->update([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        // Also activate the user account associated with this ACC
        $user = User::where('email', $acc->email)->first();
        if ($user && $user->role === 'acc_admin') {
            $user->update(['status' => 'active']);
            
            // Send notification to ACC admin
            $notificationService = new NotificationService();
            $notificationService->notifyAccApproved($user->id, $acc->id, $acc->name);
        }

        return response()->json(['message' => 'ACC application approved', 'acc' => $acc]);
    }

    #[OA\Put(
        path: "/admin/accs/applications/{id}/reject",
        summary: "Reject ACC application",
        description: "Reject an ACC application with a reason.",
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
                        new OA\Property(property: "message", type: "string", example: "ACC application rejected"),
                        new OA\Property(property: "acc", type: "object")
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

        $acc = ACC::findOrFail($id);
        $acc->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'approved_by' => $request->user()->id,
        ]);

        // Send notification to ACC admin
        $user = User::where('email', $acc->email)->first();
        if ($user && $user->role === 'acc_admin') {
            $notificationService = new NotificationService();
            $notificationService->notifyAccRejected($user->id, $acc->id, $acc->name, $request->rejection_reason);
        }

        return response()->json([
            'message' => 'ACC application rejected',
            'acc' => $acc->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/admin/accs/{id}/create-space",
        summary: "Create ACC space",
        description: "Create a space for an ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Space created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "ACC space created successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function createSpace($id)
    {
        $acc = ACC::findOrFail($id);
        // Implementation for creating ACC space
        return response()->json(['message' => 'ACC space created successfully']);
    }

    #[OA\Post(
        path: "/admin/accs/{id}/generate-credentials",
        summary: "Generate ACC credentials",
        description: "Generate credentials for an ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Credentials generated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Credentials generated successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function generateCredentials($id)
    {
        $acc = ACC::findOrFail($id);
        // Implementation for generating credentials
        return response()->json(['message' => 'Credentials generated successfully']);
    }

    #[OA\Get(
        path: "/admin/accs",
        summary: "List all ACCs",
        description: "Get all ACCs with their subscription information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "ACCs retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "accs", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index()
    {
        $accs = ACC::with('subscriptions')->get();
        return response()->json(['accs' => $accs]);
    }

    #[OA\Get(
        path: "/admin/accs/{id}",
        summary: "Get ACC details",
        description: "Get detailed information about a specific ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "ACC retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "acc", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function show($id)
    {
        $acc = ACC::with('subscriptions', 'documents', 'categories')->findOrFail($id);
        return response()->json(['acc' => $acc]);
    }

    #[OA\Get(
        path: "/admin/accs/{id}/categories",
        summary: "Get ACC assigned categories",
        description: "Get all categories assigned to an ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Categories retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "acc", type: "object"),
                        new OA\Property(property: "categories", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total", type: "integer", example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getAssignedCategories($id)
    {
        $acc = ACC::findOrFail($id);
        
        $categories = $acc->categories()
            ->with(['subCategories', 'createdBy:id,name,email'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'acc' => [
                'id' => $acc->id,
                'name' => $acc->name,
                'email' => $acc->email,
            ],
            'categories' => $categories,
            'total' => $categories->count()
        ], 200);
    }

    #[OA\Put(
        path: "/admin/accs/{id}/commission-percentage",
        summary: "Set ACC commission percentage",
        description: "Set the commission percentage for an ACC.",
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
                description: "Commission percentage set successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Commission percentage set successfully"),
                        new OA\Property(property: "acc", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function setCommissionPercentage(Request $request, $id)
    {
        $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $acc = ACC::findOrFail($id);
        $acc->update([
            'commission_percentage' => $request->commission_percentage,
        ]);

        return response()->json([
            'message' => 'Commission percentage set successfully',
            'acc' => $acc->fresh(),
        ]);
    }

    #[OA\Get(
        path: "/admin/accs/{id}/transactions",
        summary: "Get ACC transactions",
        description: "Get all transactions for a specific ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Transactions retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "transactions", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function transactions($id)
    {
        $acc = ACC::findOrFail($id);
        // Implementation for getting transactions
        return response()->json(['transactions' => []]);
    }

    /**
     * Assign category to ACC
     */
    #[OA\Post(
        path: "/admin/accs/{id}/assign-category",
        summary: "Assign category to ACC",
        description: "Assign a category to an ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Category assigned successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Category assigned successfully"),
                        new OA\Property(property: "acc", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Category already assigned"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or category not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function assignCategory(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $acc = ACC::findOrFail($id);
        $category = Category::findOrFail($request->category_id);

        // Check if already assigned
        if ($acc->categories()->where('category_id', $request->category_id)->exists()) {
            return response()->json([
                'message' => 'Category is already assigned to this ACC'
            ], 400);
        }

        $acc->categories()->attach($request->category_id);

        return response()->json([
            'message' => 'Category assigned successfully',
            'acc' => $acc->fresh()->load('categories')
        ], 200);
    }

    /**
     * Remove category from ACC
     */
    #[OA\Delete(
        path: "/admin/accs/{id}/remove-category",
        summary: "Remove category from ACC",
        description: "Remove a category assignment from an ACC.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Category removed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Category removed successfully"),
                        new OA\Property(property: "acc", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or category not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function removeCategory(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $acc = ACC::findOrFail($id);
        $acc->categories()->detach($request->category_id);

        return response()->json([
            'message' => 'Category removed successfully',
            'acc' => $acc->fresh()->load('categories')
        ], 200);
    }

    #[OA\Put(
        path: "/admin/accs/{id}",
        summary: "Update ACC",
        description: "Update ACC information.",
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
                    new OA\Property(property: "address", type: "string", nullable: true),
                    new OA\Property(property: "phone", type: "string", nullable: true),
                    new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                    new OA\Property(property: "website", type: "string", nullable: true),
                    new OA\Property(property: "logo_url", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["pending", "active", "suspended", "expired", "rejected"], nullable: true),
                    new OA\Property(property: "registration_fee_paid", type: "boolean", nullable: true),
                    new OA\Property(property: "registration_fee_amount", type: "number", nullable: true),
                    new OA\Property(property: "commission_percentage", type: "number", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "ACC updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "ACC updated successfully"),
                        new OA\Property(property: "acc", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $acc = ACC::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:255|unique:accs,registration_number,' . $id,
            'country' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:accs,email,' . $id,
            'website' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,active,suspended,expired,rejected',
            'registration_fee_paid' => 'sometimes|boolean',
            'registration_fee_amount' => 'nullable|numeric|min:0',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $acc->update($request->only([
            'name',
            'legal_name',
            'registration_number',
            'country',
            'address',
            'phone',
            'email',
            'website',
            'logo_url',
            'status',
            'registration_fee_paid',
            'registration_fee_amount',
            'commission_percentage',
        ]));

        return response()->json([
            'message' => 'ACC updated successfully',
            'acc' => $acc->fresh()
        ], 200);
    }
}

