<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrainingCenterController extends Controller
{
    #[OA\Get(
        path: "/acc/training-centers/requests",
        summary: "List training center authorization requests",
        description: "Get all training center authorization requests for the authenticated ACC with pagination, search, and statistics.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by training center name, email, country, city, or request ID"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "approved", "rejected", "returned"]), description: "Filter by request status"),
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
                                new OA\Property(property: "total", type: "integer", description: "Total number of requests"),
                                new OA\Property(property: "pending", type: "integer", description: "Number of pending requests"),
                                new OA\Property(property: "approved", type: "integer", description: "Number of approved requests"),
                                new OA\Property(property: "rejected", type: "integer", description: "Number of rejected requests"),
                                new OA\Property(property: "returned", type: "integer", description: "Number of returned requests"),
                                new OA\Property(property: "last_7_days", type: "integer", description: "Number of requests in the last 7 days"),
                                new OA\Property(property: "last_30_days", type: "integer", description: "Number of requests in the last 30 days"),
                                new OA\Property(property: "pending_older_than_7_days", type: "integer", description: "Number of pending requests older than 7 days")
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

        $baseQuery = TrainingCenterAccAuthorization::where('acc_id', $acc->id);
        $query = clone $baseQuery;
        $query->with('trainingCenter');

        // Filter by status if provided
        if ($request->has('status')) {
            $validStatuses = ['pending', 'approved', 'rejected', 'returned'];
            if (in_array($request->status, $validStatuses)) {
                $query->where('status', $request->status);
            }
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'like', "%{$searchTerm}%")
                    ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                        $tcQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%")
                            ->orWhere('country', 'like', "%{$searchTerm}%")
                            ->orWhere('city', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->orderBy('request_date', 'desc')->paginate($perPage);

        // Calculate statistics
        $statistics = [
            'total' => $baseQuery->count(),
            'pending' => $baseQuery->where('status', 'pending')->count(),
            'approved' => $baseQuery->where('status', 'approved')->count(),
            'rejected' => $baseQuery->where('status', 'rejected')->count(),
            'returned' => $baseQuery->where('status', 'returned')->count(),
            'last_7_days' => $baseQuery->where('request_date', '>=', now()->subDays(7))->count(),
            'last_30_days' => $baseQuery->where('request_date', '>=', now()->subDays(30))->count(),
            'pending_older_than_7_days' => $baseQuery->where('status', 'pending')
                ->where('request_date', '<', now()->subDays(7))
                ->count(),
        ];

        return response()->json([
            'data' => $requests->items(),
            'current_page' => $requests->currentPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
            'last_page' => $requests->lastPage(),
            'from' => $requests->firstItem(),
            'to' => $requests->lastItem(),
            'statistics' => $statistics,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notifications
        $authorization->load(['trainingCenter', 'acc']);
        $notificationService = new NotificationService();
        
        // Notify Training Center
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService->notifyTrainingCenterAuthorized(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $acc->name
                );
            }
        }

        return response()->json(['message' => 'Training center approved successfully']);
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

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
        $authorization->load(['trainingCenter', 'acc']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterAuthorizationRejected(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $acc->name,
                    $request->rejection_reason
                );
            }
        }

        return response()->json(['message' => 'Training center rejected']);
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

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'returned',
            'return_comment' => $request->return_comment,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
        $authorization->load(['trainingCenter', 'acc']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterAuthorizationReturned(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $acc->name,
                    $request->return_comment
                );
            }
        }

        return response()->json(['message' => 'Request returned successfully']);
    }

    #[OA\Get(
        path: "/acc/training-centers",
        summary: "List approved training centers",
        description: "Get all approved training centers for the authenticated ACC with pagination and search.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by training center name, email, country, or city"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training centers retrieved successfully",
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
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $query = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->with('trainingCenter');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->whereHas('trainingCenter', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    ->orWhere('country', 'like', "%{$searchTerm}%")
                    ->orWhere('city', 'like', "%{$searchTerm}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $authorizations = $query->orderBy('reviewed_at', 'desc')->paginate($perPage);

        // Transform to return training centers directly
        $trainingCenters = $authorizations->getCollection()->map(function ($authorization) {
            return $authorization->trainingCenter;
        });

        return response()->json([
            'data' => $trainingCenters,
            'current_page' => $authorizations->currentPage(),
            'per_page' => $authorizations->perPage(),
            'total' => $authorizations->total(),
            'last_page' => $authorizations->lastPage(),
            'from' => $authorizations->firstItem(),
            'to' => $authorizations->lastItem(),
        ]);
    }
}

