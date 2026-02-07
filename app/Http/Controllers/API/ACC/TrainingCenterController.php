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
        
        // Get all requests with training center
        $allRequests = $baseQuery->with('trainingCenter')
            ->orderBy('request_date', 'desc')
            ->get();

        // Group by training_center_id and get latest request for each
        $grouped = $allRequests->groupBy('training_center_id');
        $latestRequests = $grouped->map(function ($requests) {
            // Get the latest request (first one since we ordered by request_date desc)
            $latest = $requests->first();
            
            // Get all previous requests (excluding the latest)
            $previous = $requests->slice(1)->values();
            
            // Add previous requests info to the latest request
            $latest->previous_requests = $previous->map(function ($req) {
                return [
                    'id' => $req->id,
                    'request_date' => $req->request_date,
                    'status' => $req->status,
                    'rejection_reason' => $req->rejection_reason,
                    'return_comment' => $req->return_comment,
                    'reviewed_by' => $req->reviewed_by,
                    'reviewed_at' => $req->reviewed_at,
                    'documents_count' => is_array($req->documents_json) ? count($req->documents_json) : 0,
                ];
            })->toArray();
            
            $latest->total_requests_count = $requests->count();
            
            return $latest;
        })->values();

        // Filter by status if provided (filter latest requests by their status)
        if ($request->has('status')) {
            $validStatuses = ['pending', 'approved', 'rejected', 'returned'];
            if (in_array($request->status, $validStatuses)) {
                $latestRequests = $latestRequests->where('status', $request->status)->values();
            }
        }

        // Search functionality (search in latest requests)
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $latestRequests = $latestRequests->filter(function ($req) use ($searchTerm) {
                return stripos((string)$req->id, $searchTerm) !== false
                    || stripos($req->trainingCenter->name ?? '', $searchTerm) !== false
                    || stripos($req->trainingCenter->email ?? '', $searchTerm) !== false
                    || stripos($req->trainingCenter->country ?? '', $searchTerm) !== false
                    || stripos($req->trainingCenter->city ?? '', $searchTerm) !== false;
            })->values();
        }

        // Paginate the grouped results
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $total = $latestRequests->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $latestRequests->slice($offset, $perPage)->values();

        // Calculate statistics based on latest requests only
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);
        
        $statistics = [
            'total' => $total,
            'pending' => $latestRequests->where('status', 'pending')->count(),
            'approved' => $latestRequests->where('status', 'approved')->count(),
            'rejected' => $latestRequests->where('status', 'rejected')->count(),
            'returned' => $latestRequests->where('status', 'returned')->count(),
            'last_7_days' => $latestRequests->filter(function ($req) use ($sevenDaysAgo) {
                return $req->request_date >= $sevenDaysAgo;
            })->count(),
            'last_30_days' => $latestRequests->filter(function ($req) use ($thirtyDaysAgo) {
                return $req->request_date >= $thirtyDaysAgo;
            })->count(),
            'pending_older_than_7_days' => $latestRequests->filter(function ($req) use ($sevenDaysAgo) {
                return $req->status === 'pending' && $req->request_date < $sevenDaysAgo;
            })->count(),
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
        path: "/acc/training-centers/requests/{id}/approve",
        summary: "Approve training center authorization request",
        description: "Approve a training center authorization request. Requires an active training center certificate template to be created first.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), description: "Authorization request ID")
        ],
        responses: [
            new OA\Response(response: 200, description: "Training center approved successfully"),
            new OA\Response(
                response: 422,
                description: "Validation error - Certificate template required",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Cannot approve training center. Please create an active training center certificate template first."),
                        new OA\Property(property: "errors", type: "object"),
                        new OA\Property(property: "required_action", type: "string", example: "create_training_center_template"),
                        new OA\Property(property: "template_type", type: "string", example: "training_center")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or authorization not found")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        // Validate that training center certificate template exists
        $certificateTemplate = \App\Models\CertificateTemplate::where('acc_id', $acc->id)
            ->where('template_type', 'training_center')
            ->where('status', 'active')
            ->first();

        if (!$certificateTemplate) {
            return response()->json([
                'message' => 'Cannot approve training center. Please create an active training center certificate template first.',
                'errors' => [
                    'certificate_template' => ['An active training center certificate template is required before approving training centers. Please create one in the certificate templates section.']
                ],
                'required_action' => 'create_training_center_template',
                'template_type' => 'training_center'
            ], 422);
        }

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

            // Generate and send certificate (template already validated above)
            if ($certificateTemplate) {
                try {
                    $certificateService = new \App\Services\CertificateGenerationService();
                    $result = $certificateService->generateTrainingCenterCertificate(
                        $certificateTemplate,
                        $trainingCenter,
                        $acc
                    );

                    if ($result['success'] && isset($result['file_path'])) {
                        $pdfPath = \Illuminate\Support\Facades\Storage::disk('public')->path($result['file_path']);
                        
                        if (file_exists($pdfPath)) {
                            // Send email with certificate immediately (not queued)
                            try {
                                $mail = new \App\Mail\TrainingCenterCertificateMail(
                                    $trainingCenter->name,
                                    $acc->name,
                                    $pdfPath
                                );
                                
                                // Force send immediately by setting connection to sync
                                // This bypasses the queue even if ShouldQueue is implemented
                                $mail->onConnection('sync');
                                \Illuminate\Support\Facades\Mail::to($trainingCenter->email)->send($mail);

                                \Illuminate\Support\Facades\Log::info('Training center certificate generated and sent', [
                                    'training_center_id' => $trainingCenter->id,
                                    'acc_id' => $acc->id,
                                    'email' => $trainingCenter->email,
                                    'pdf_path' => $pdfPath,
                                ]);
                            } catch (\Exception $mailException) {
                                \Illuminate\Support\Facades\Log::error('Failed to send training center certificate email', [
                                    'training_center_id' => $trainingCenter->id,
                                    'acc_id' => $acc->id,
                                    'email' => $trainingCenter->email,
                                    'pdf_path' => $pdfPath,
                                    'error' => $mailException->getMessage(),
                                    'trace' => $mailException->getTraceAsString(),
                                ]);
                            }
                        } else {
                            \Illuminate\Support\Facades\Log::warning('Certificate PDF file does not exist', [
                                'training_center_id' => $trainingCenter->id,
                                'acc_id' => $acc->id,
                                'pdf_path' => $pdfPath,
                            ]);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Failed to generate training center certificate', [
                            'training_center_id' => $trainingCenter->id,
                            'acc_id' => $acc->id,
                            'error' => $result['message'] ?? 'Unknown error',
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to generate/send training center certificate', [
                        'authorization_id' => $authorization->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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

