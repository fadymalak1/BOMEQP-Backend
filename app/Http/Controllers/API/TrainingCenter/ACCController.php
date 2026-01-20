<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ACCController extends Controller
{
    #[OA\Get(
        path: "/training-center/accs",
        summary: "List active ACCs",
        description: "Get all active ACCs available for authorization requests with pagination and search.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by ACC name, legal name, email, or country"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "ACCs retrieved successfully",
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
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = ACC::where('status', 'active');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('legal_name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%")
                    ->orWhere('country', 'like', "%{$searchTerm}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $accs = $query->orderBy('name', 'asc')->paginate($perPage);

        return response()->json($accs);
    }

    #[OA\Post(
        path: "/training-center/accs/{id}/request-authorization",
        summary: "Request ACC authorization",
        description: "Request authorization from an ACC. Upload required documents.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["documents"],
                    properties: [
                        new OA\Property(
                            property: "documents",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "file", type: "string", format: "binary", description: "Document file (PDF, DOC, DOCX, JPG, PNG, max 10MB)"),
                                    new OA\Property(property: "type", type: "string", enum: ["license", "certificate", "registration", "other"], example: "license")
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Authorization request created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Authorization request submitted successfully"),
                        new OA\Property(property: "authorization", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Authorization request already exists"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center or ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function requestAuthorization(Request $request, $id)
    {
        try {
            $user = $request->user();
            $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

            if (!$trainingCenter) {
                return response()->json(['message' => 'Training center not found'], 404);
            }

            $acc = ACC::findOrFail($id);

            // Check if authorization already exists
            $existing = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
                ->where('acc_id', $acc->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Authorization request already exists',
                    'authorization_id' => $existing->id
                ], 400);
            }

            // Get all uploaded files
            $allFiles = $request->allFiles();
            $documents = [];
            
            // Handle files - support both documents[0][file] and documents.0.file format
            if (isset($allFiles['documents']) && is_array($allFiles['documents'])) {
                foreach ($allFiles['documents'] as $index => $fileData) {
                    $file = null;
                    $documentType = null;
                    
                    // Handle nested structure: documents[0][file]
                    if (is_array($fileData) && isset($fileData['file'])) {
                        $file = $fileData['file'];
                        // Get type from request input
                        $documentType = $request->input("documents.{$index}.type");
                    } 
                    // Handle flat structure or direct file
                    elseif ($fileData instanceof \Illuminate\Http\UploadedFile) {
                        $file = $fileData;
                        $documentType = $request->input("documents.{$index}.type");
                    }
                    
                    if ($file && $file->isValid()) {
                        // Validate file
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['file' => $file],
                            [
                                'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                            ]
                        );
                        
                        if ($validator->fails()) {
                            return response()->json([
                                'message' => 'File validation failed',
                                'errors' => $validator->errors()
                            ], 422);
                        }
                        
                        // Validate document type
                        if (!$documentType || !in_array($documentType, ['license', 'certificate', 'registration', 'other'])) {
                            return response()->json([
                                'message' => "Invalid document type for document at index {$index}. Must be one of: license, certificate, registration, other"
                            ], 422);
                        }
                        
                        // Create directory path: authorization/{training_center_id}/{acc_id}/
                        $directory = 'authorization/' . $trainingCenter->id . '/' . $acc->id;
                        $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                        
                        // Store file in public storage
                        $path = $file->storeAs($directory, $fileName, 'public');
                        $url = Storage::disk('public')->url($path);
                        
                        $documents[] = [
                            'type' => $documentType,
                            'url' => $url,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }

            // Also try alternative format: documents.0.file
            if (empty($documents)) {
                $documentsInput = $request->input('documents', []);
                foreach ($documentsInput as $index => $docData) {
                    $fileKey = "documents.{$index}.file";
                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);
                        if ($file && $file->isValid()) {
                            $documentType = $docData['type'] ?? null;
                            
                            if (!$documentType || !in_array($documentType, ['license', 'certificate', 'registration', 'other'])) {
                                continue;
                            }
                            
                            $directory = 'authorization/' . $trainingCenter->id . '/' . $acc->id;
                            $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                            $path = $file->storeAs($directory, $fileName, 'public');
                            $url = Storage::disk('public')->url($path);
                            
                            $documents[] = [
                                'type' => $documentType,
                                'url' => $url,
                                'original_name' => $file->getClientOriginalName(),
                                'mime_type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                            ];
                        }
                    }
                }
            }

            if (empty($documents)) {
                return response()->json([
                    'message' => 'No valid documents uploaded. Please ensure files are uploaded correctly.',
                    'hint' => 'Use FormData with structure: documents[0][type]=license&documents[0][file]=<file>'
                ], 422);
            }

            $authorization = TrainingCenterAccAuthorization::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $acc->id,
                'request_date' => now(),
                'status' => 'pending',
                'documents_json' => $documents,
            ]);

            // Send notification to ACC admin with enhanced details
            $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
            if ($accUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterAuthorizationRequested(
                    $accUser->id,
                    $authorization->id,
                    $trainingCenter->name,
                    $trainingCenter->country,
                    $trainingCenter->city
                );
            }

            return response()->json([
                'message' => 'Authorization request submitted successfully',
                'authorization' => $authorization,
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Authorization request error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Server error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Please contact support'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/training-center/accs/authorizations",
        summary: "Get authorization requests",
        description: "Get all authorization requests for the authenticated training center.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["pending", "approved", "rejected"]), example: "pending")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Authorizations retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "authorizations", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function authorizations(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorizations = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->with('acc')
            ->orderBy('request_date', 'desc')
            ->get();

        return response()->json(['authorizations' => $authorizations]);
    }

    #[OA\Post(
        path: "/training-center/authorizations",
        summary: "Create authorization request",
        description: "Create a new authorization request for an ACC. This endpoint accepts ACC ID in the request body along with documents.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["acc_id", "documents"],
                    properties: [
                        new OA\Property(property: "acc_id", type: "integer", example: 1, description: "ACC ID to request authorization from"),
                        new OA\Property(
                            property: "documents",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "file", type: "string", format: "binary", description: "Document file (PDF, DOC, DOCX, JPG, PNG, max 10MB)"),
                                    new OA\Property(property: "type", type: "string", enum: ["license", "certificate", "registration", "other"], example: "license")
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Authorization request created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Authorization request submitted successfully"),
                        new OA\Property(property: "authorization", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Authorization request already exists"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center or ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

            if (!$trainingCenter) {
                return response()->json(['message' => 'Training center not found'], 404);
            }

            // Validate acc_id
            $request->validate([
                'acc_id' => 'required|integer|exists:accs,id',
            ]);

            $accId = $request->input('acc_id');
            $acc = ACC::findOrFail($accId);

            // Check if authorization already exists
            $existing = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
                ->where('acc_id', $acc->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Authorization request already exists',
                    'authorization_id' => $existing->id,
                    'authorization' => $existing
                ], 400);
            }

            // Get all uploaded files
            $allFiles = $request->allFiles();
            $documents = [];
            
            // Handle files - support both documents[0][file] and documents.0.file format
            if (isset($allFiles['documents']) && is_array($allFiles['documents'])) {
                foreach ($allFiles['documents'] as $index => $fileData) {
                    $file = null;
                    $documentType = null;
                    
                    // Handle nested structure: documents[0][file]
                    if (is_array($fileData) && isset($fileData['file'])) {
                        $file = $fileData['file'];
                        // Get type from request input
                        $documentType = $request->input("documents.{$index}.type");
                    } 
                    // Handle flat structure or direct file
                    elseif ($fileData instanceof \Illuminate\Http\UploadedFile) {
                        $file = $fileData;
                        $documentType = $request->input("documents.{$index}.type");
                    }
                    
                    if ($file && $file->isValid()) {
                        // Validate file
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['file' => $file],
                            [
                                'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                            ]
                        );
                        
                        if ($validator->fails()) {
                            return response()->json([
                                'message' => 'File validation failed',
                                'errors' => $validator->errors()
                            ], 422);
                        }
                        
                        // Validate document type
                        if (!$documentType || !in_array($documentType, ['license', 'certificate', 'registration', 'other'])) {
                            return response()->json([
                                'message' => "Invalid document type for document at index {$index}. Must be one of: license, certificate, registration, other"
                            ], 422);
                        }
                        
                        // Create directory path: authorization/{training_center_id}/{acc_id}/
                        $directory = 'authorization/' . $trainingCenter->id . '/' . $acc->id;
                        $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                        
                        // Store file in public storage
                        $path = $file->storeAs($directory, $fileName, 'public');
                        $url = Storage::disk('public')->url($path);
                        
                        $documents[] = [
                            'type' => $documentType,
                            'url' => $url,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }

            // Also try alternative format: documents.0.file
            if (empty($documents)) {
                $documentsInput = $request->input('documents', []);
                foreach ($documentsInput as $index => $docData) {
                    $fileKey = "documents.{$index}.file";
                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);
                        if ($file && $file->isValid()) {
                            $documentType = $docData['type'] ?? null;
                            
                            if (!$documentType || !in_array($documentType, ['license', 'certificate', 'registration', 'other'])) {
                                continue;
                            }
                            
                            $directory = 'authorization/' . $trainingCenter->id . '/' . $acc->id;
                            $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                            $path = $file->storeAs($directory, $fileName, 'public');
                            $url = Storage::disk('public')->url($path);
                            
                            $documents[] = [
                                'type' => $documentType,
                                'url' => $url,
                                'original_name' => $file->getClientOriginalName(),
                                'mime_type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                            ];
                        }
                    }
                }
            }

            if (empty($documents)) {
                return response()->json([
                    'message' => 'No valid documents uploaded. Please ensure files are uploaded correctly.',
                    'hint' => 'Use FormData with structure: acc_id=1&documents[0][type]=license&documents[0][file]=<file>'
                ], 422);
            }

            $authorization = TrainingCenterAccAuthorization::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $acc->id,
                'request_date' => now(),
                'status' => 'pending',
                'documents_json' => $documents,
            ]);

            // Send notification to ACC admin
            $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
            if ($accUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterAuthorizationRequested(
                    $accUser->id,
                    $authorization->id,
                    $trainingCenter->name,
                    $trainingCenter->country,
                    $trainingCenter->city
                );
            }

            return response()->json([
                'message' => 'Authorization request submitted successfully',
                'authorization' => $authorization->load('acc'),
            ], 201);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'ACC not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Authorization request error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Server error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Please contact support'
            ], 500);
        }
    }
}

