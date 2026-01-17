<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Trainee;
use App\Models\TrainingCenter;
use App\Models\TrainingClass;
use App\Services\TraineeManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class TraineeController extends Controller
{
    protected TraineeManagementService $traineeService;

    public function __construct(TraineeManagementService $traineeService)
    {
        $this->traineeService = $traineeService;
    }

    #[OA\Get(
        path: "/training-center/trainees",
        summary: "List trainees",
        description: "Get all trainees for the authenticated training center with optional filtering and pagination.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string"), example: "active"),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), example: "John", description: "Search by trainee full name (first name, last name, or both), email, phone, or ID number"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Trainees retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "trainees", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = Trainee::where('training_center_id', $trainingCenter->id)
            ->with('trainingClasses.course', 'trainingClasses.instructor');

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
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

        $trainees = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'trainees' => $trainees->items(),
            'pagination' => [
                'current_page' => $trainees->currentPage(),
                'last_page' => $trainees->lastPage(),
                'per_page' => $trainees->perPage(),
                'total' => $trainees->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/training-center/trainees/{id}",
        summary: "Get trainee details",
        description: "Get detailed information about a specific trainee.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Trainee retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "trainee", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Trainee not found")
        ]
    )]
    public function show($id)
    {
        $user = request()->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainee = Trainee::where('training_center_id', $trainingCenter->id)
            ->with(['trainingClasses.course', 'trainingClasses.instructor', 'trainingClasses.createdBy'])
            ->findOrFail($id);

        return response()->json(['trainee' => $trainee]);
    }

    #[OA\Post(
        path: "/training-center/trainees",
        summary: "Create trainee",
        description: "Create a new trainee with ID and card images. Can optionally enroll in classes.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["first_name", "last_name", "email", "phone", "id_number", "id_image", "card_image"],
                    properties: [
                        new OA\Property(property: "first_name", type: "string", example: "John"),
                        new OA\Property(property: "last_name", type: "string", example: "Doe"),
                        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                        new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                        new OA\Property(property: "id_number", type: "string", example: "ID123456"),
                        new OA\Property(property: "id_image", type: "string", format: "binary", description: "ID image (JPEG, PNG, PDF, max 10MB)"),
                        new OA\Property(property: "card_image", type: "string", format: "binary", description: "Card image (JPEG, PNG, PDF, max 10MB)"),
                        new OA\Property(property: "enrolled_classes", type: "array", nullable: true, items: new OA\Items(type: "integer"), example: [1, 2]),
                        new OA\Property(property: "status", type: "string", enum: ["active", "inactive", "suspended"], nullable: true, example: "active")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Trainee created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Trainee created successfully"),
                        new OA\Property(property: "trainee", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:trainees,email',
            'phone' => 'required|string|max:255',
            'id_number' => 'required|string|unique:trainees,id_number',
            'id_image' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
            'card_image' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
            'enrolled_classes' => 'nullable|array',
            'enrolled_classes.*' => 'exists:training_classes,id',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        try {
            $result = $this->traineeService->createTrainee($request, $trainingCenter);

            return response()->json([
                'message' => $result['message'],
                'trainee' => $result['trainee']
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create trainee', [
                'training_center_id' => $trainingCenter->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to create trainee: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Post(
        path: "/training-center/trainees/{id}",
        summary: "Update trainee",
        description: "Update trainee information. Use POST method for file uploads. Can update personal details, images, and enrolled classes. Laravel's method spoofing with _method=PUT is supported for compatibility.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "_method", type: "string", example: "PUT", nullable: true, description: "HTTP method override (optional, for compatibility with PUT endpoints)"),
                        new OA\Property(property: "first_name", type: "string", nullable: true),
                        new OA\Property(property: "last_name", type: "string", nullable: true),
                        new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                        new OA\Property(property: "phone", type: "string", nullable: true),
                        new OA\Property(property: "id_number", type: "string", nullable: true),
                        new OA\Property(property: "id_image", type: "string", format: "binary", nullable: true, description: "ID image (JPEG, PNG, PDF, max 10MB)"),
                        new OA\Property(property: "card_image", type: "string", format: "binary", nullable: true, description: "Card image (JPEG, PNG, PDF, max 10MB)"),
                        new OA\Property(property: "enrolled_classes", type: "array", nullable: true, items: new OA\Items(type: "integer")),
                        new OA\Property(property: "status", type: "string", enum: ["active", "inactive", "suspended"], nullable: true)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Trainee updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Trainee updated successfully"),
                        new OA\Property(property: "trainee", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Trainee not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainee = Trainee::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:trainees,email,' . $id,
            'phone' => 'sometimes|string|max:255',
            'id_number' => 'sometimes|string|unique:trainees,id_number,' . $id,
            'id_image' => 'sometimes|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'card_image' => 'sometimes|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'enrolled_classes' => 'nullable|array',
            'enrolled_classes.*' => 'exists:training_classes,id',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        try {
            $result = $this->traineeService->updateTrainee($request, $trainee, $trainingCenter);

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message']
                ], $result['code'] ?? 500);
            }

            return response()->json([
                'message' => $result['message'],
                'trainee' => $result['trainee']
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update trainee', [
                'trainee_id' => $trainee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update trainee: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Delete(
        path: "/training-center/trainees/{id}",
        summary: "Delete trainee",
        description: "Delete a trainee and associated files. This action cannot be undone.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Trainee deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Trainee deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Trainee not found")
        ]
    )]
    public function destroy($id)
    {
        $user = request()->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainee = Trainee::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        // Delete associated files
        if ($trainee->id_image_url) {
            // Extract path from URL - remove the storage URL base
            $storageUrl = Storage::disk('public')->url('');
            $idImagePath = str_replace($storageUrl, '', $trainee->id_image_url);
            // Remove leading slash if present
            $idImagePath = ltrim($idImagePath, '/');
            Storage::disk('public')->delete($idImagePath);
        }

        if ($trainee->card_image_url) {
            // Extract path from URL - remove the storage URL base
            $storageUrl = Storage::disk('public')->url('');
            $cardImagePath = str_replace($storageUrl, '', $trainee->card_image_url);
            // Remove leading slash if present
            $cardImagePath = ltrim($cardImagePath, '/');
            Storage::disk('public')->delete($cardImagePath);
        }

        // Decrement enrolled_count for enrolled classes
        $enrolledClasses = $trainee->trainingClasses()->pluck('training_classes.id')->toArray();
        foreach ($enrolledClasses as $classId) {
            $class = TrainingClass::find($classId);
            if ($class && $class->enrolled_count > 0) {
                $class->decrement('enrolled_count');
            }
        }

        $trainee->delete();

        return response()->json(['message' => 'Trainee deleted successfully'], 200);
    }
}

