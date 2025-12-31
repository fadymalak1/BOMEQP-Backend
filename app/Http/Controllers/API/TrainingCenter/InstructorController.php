<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\Transaction;
use App\Models\User;
use App\Mail\InstructorCredentialsMail;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class InstructorController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    #[OA\Get(
        path: "/training-center/instructors",
        summary: "List instructors",
        description: "Get all instructors for the authenticated training center.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Instructors retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "instructors", type: "array", items: new OA\Items(type: "object"))
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
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructors = Instructor::where('training_center_id', $trainingCenter->id)->get();
        return response()->json(['instructors' => $instructors]);
    }

    #[OA\Post(
        path: "/training-center/instructors",
        summary: "Create instructor",
        description: "Create a new instructor. An email with login credentials will be sent to the instructor.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["first_name", "last_name", "email", "phone", "id_number"],
                    properties: [
                        new OA\Property(property: "first_name", type: "string", example: "John"),
                        new OA\Property(property: "last_name", type: "string", example: "Doe"),
                        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                        new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                        new OA\Property(property: "id_number", type: "string", example: "ID123456"),
                        new OA\Property(property: "cv", type: "string", format: "binary", description: "CV file (PDF, max 10MB)"),
                        new OA\Property(property: "certificates_json", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "specializations", type: "array", items: new OA\Items(type: "string")),
                        new OA\Property(property: "is_assessor", type: "boolean", example: false)
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Instructor created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Instructor created successfully"),
                        new OA\Property(property: "instructor", type: "object")
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
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:instructors,email|unique:users,email',
            'phone' => 'required|string',
            'id_number' => 'required|string|unique:instructors,id_number',
            'cv' => 'nullable|file|mimes:pdf|max:10240', // PDF file, max 10MB
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
            'is_assessor' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $cvUrl = null;
        if ($request->hasFile('cv')) {
            $cvFile = $request->file('cv');
            $fileName = time() . '_' . $trainingCenter->id . '_' . $cvFile->getClientOriginalName();
            // Store file in public disk
            $cvPath = $cvFile->storeAs('instructors/cv', $fileName, 'public');
            // Generate URL using the API route
            $cvUrl = url('/api/storage/instructors/cv/' . $fileName);
        }

        // Generate a random password for the instructor
        $password = Str::random(12);
        $instructorName = $request->first_name . ' ' . $request->last_name;

        // Create instructor record
        $instructor = Instructor::create([
            'training_center_id' => $trainingCenter->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'id_number' => $request->id_number,
            'cv_url' => $cvUrl,
            'certificates_json' => $request->certificates_json ?? $request->certificates,
            'specializations' => $request->specializations,
            'is_assessor' => $request->boolean('is_assessor', false),
            'status' => 'pending',
        ]);

        // Create user account for the instructor
        $user = User::create([
            'name' => $instructorName,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => 'instructor',
            'status' => 'active', // Instructors are active immediately
        ]);

        // Send email with credentials
        try {
            Mail::to($request->email)->send(new InstructorCredentialsMail(
                $request->email,
                $password,
                $instructorName,
                $trainingCenter->name
            ));
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Failed to send instructor credentials email: ' . $e->getMessage());
            // You can optionally return a warning in the response
        }

        return response()->json([
            'message' => 'Instructor created successfully. Credentials have been sent to the instructor\'s email.',
            'instructor' => $instructor,
        ], 201);
    }

    #[OA\Get(
        path: "/training-center/instructors/{id}",
        summary: "Get instructor details",
        description: "Get detailed information about a specific instructor.",
        tags: ["Training Center"],
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
        $instructor = Instructor::with('trainingCenter')->findOrFail($id);
        return response()->json(['instructor' => $instructor]);
    }

    #[OA\Put(
        path: "/training-center/instructors/{id}",
        summary: "Update instructor",
        description: "Update instructor information.",
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
                        new OA\Property(property: "first_name", type: "string", nullable: true),
                        new OA\Property(property: "last_name", type: "string", nullable: true),
                        new OA\Property(property: "phone", type: "string", nullable: true),
                        new OA\Property(property: "cv", type: "string", format: "binary", nullable: true),
                        new OA\Property(property: "certificates_json", type: "array", nullable: true, items: new OA\Items(type: "object")),
                        new OA\Property(property: "specializations", type: "array", nullable: true, items: new OA\Items(type: "string")),
                        new OA\Property(property: "is_assessor", type: "boolean", nullable: true)
                    ]
                )
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
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:instructors,email,' . $id,
            'phone' => 'sometimes|string',
            'id_number' => 'sometimes|string|unique:instructors,id_number,' . $id,
            'cv' => 'nullable|file|mimes:pdf|max:10240', // PDF file, max 10MB
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
            'is_assessor' => 'nullable|boolean',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'email', 'phone', 'id_number',
            'specializations', 'is_assessor'
        ]);
        
        // Handle boolean conversion for is_assessor
        if ($request->has('is_assessor')) {
            $updateData['is_assessor'] = $request->boolean('is_assessor');
        }
        
        // Handle CV file upload
        if ($request->hasFile('cv')) {
            try {
                // Delete old CV file if exists
                if ($instructor->cv_url) {
                    // Extract filename from URL (format: /api/storage/instructors/cv/{filename} or full URL)
                    $urlParts = parse_url($instructor->cv_url);
                    $path = ltrim($urlParts['path'] ?? '', '/');
                    // Try multiple patterns to extract filename
                    $oldFileName = null;
                    if (preg_match('#instructors/cv/(.+)$#', $path, $matches)) {
                        $oldFileName = $matches[1];
                    } elseif (preg_match('#storage/instructors/cv/(.+)$#', $path, $matches)) {
                        $oldFileName = $matches[1];
                    } elseif (preg_match('#api/storage/instructors/cv/(.+)$#', $path, $matches)) {
                        $oldFileName = $matches[1];
                    }
                    
                    if ($oldFileName) {
                        $oldFilePath = 'instructors/cv/' . $oldFileName;
                        if (Storage::disk('public')->exists($oldFilePath)) {
                            Storage::disk('public')->delete($oldFilePath);
                            \Log::info('Deleted old CV file', ['instructor_id' => $instructor->id, 'file' => $oldFilePath]);
                        }
                    }
                }

                // Upload new CV file
                $cvFile = $request->file('cv');
                $originalName = $cvFile->getClientOriginalName();
                $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $fileName = time() . '_' . $trainingCenter->id . '_' . $sanitizedName;
                
                // Ensure the directory exists
                $directory = 'instructors/cv';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                
                // Store file in public disk
                $cvPath = $cvFile->storeAs($directory, $fileName, 'public');
                
                // Verify file was actually stored
                $fullPath = Storage::disk('public')->path($cvPath);
                $fileExists = file_exists($fullPath);
                $fileSize = $fileExists ? filesize($fullPath) : 0;
                
                if ($cvPath && $fileExists && $fileSize > 0) {
                    // Generate URL using the API route (route is /storage/instructors/cv/{filename} in api.php, so it becomes /api/storage/instructors/cv/{filename})
                    $newCvUrl = url('/api/storage/instructors/cv/' . $fileName);
                    $updateData['cv_url'] = $newCvUrl;
                    \Log::info('CV file uploaded successfully', [
                        'instructor_id' => $instructor->id,
                        'original_name' => $originalName,
                        'file_name' => $fileName,
                        'cv_url' => $newCvUrl,
                        'storage_path' => $cvPath,
                        'full_path' => $fullPath,
                        'file_size' => $fileSize,
                        'file_exists' => $fileExists
                    ]);
                } else {
                    \Log::error('Failed to store CV file', [
                        'instructor_id' => $instructor->id,
                        'file_name' => $fileName,
                        'cv_path' => $cvPath,
                        'file_exists' => $fileExists,
                        'file_size' => $fileSize,
                        'full_path' => $fullPath ?? 'N/A'
                    ]);
                    return response()->json([
                        'message' => 'Failed to store CV file',
                        'error' => 'File storage failed or file not found after upload'
                    ], 500);
                }
            } catch (\Exception $e) {
                \Log::error('Error uploading CV file', [
                    'instructor_id' => $instructor->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'Failed to upload CV file',
                    'error' => config('app.debug') ? $e->getMessage() : 'File upload failed'
                ], 500);
            }
        }
        
        if ($request->has('certificates_json') || $request->has('certificates')) {
            $updateData['certificates_json'] = $request->certificates_json ?? $request->certificates;
        }
        
        // Log update data before saving
        \Log::info('Updating instructor', [
            'instructor_id' => $instructor->id,
            'update_data' => $updateData,
            'has_cv_url' => isset($updateData['cv_url']),
            'cv_url_value' => $updateData['cv_url'] ?? 'NOT SET',
            'old_cv_url' => $instructor->cv_url
        ]);
        
        // Use direct DB update to ensure cv_url is saved correctly
        if (isset($updateData['cv_url'])) {
            // Update cv_url directly in database first
            DB::table('instructors')
                ->where('id', $instructor->id)
                ->update(['cv_url' => $updateData['cv_url']]);
            \Log::info('CV URL updated directly in DB', [
                'instructor_id' => $instructor->id,
                'new_cv_url' => $updateData['cv_url']
            ]);
        }
        
        // Update other fields using Eloquent
        if (!empty($updateData)) {
            // Remove cv_url from updateData since we already updated it directly
            $otherUpdateData = $updateData;
            unset($otherUpdateData['cv_url']);
            
            if (!empty($otherUpdateData)) {
                $instructor->update($otherUpdateData);
            }
        }
        
        // Refresh the model to get the latest data
        $instructor->refresh();
        
        // Verify cv_url was actually saved
        $instructorFromDb = Instructor::find($instructor->id);
        $actualCvUrl = $instructorFromDb ? $instructorFromDb->cv_url : null;
        
        // Log after update to verify
        \Log::info('Instructor updated', [
            'instructor_id' => $instructor->id,
            'cv_url_in_model' => $instructor->cv_url,
            'cv_url_from_db' => $actualCvUrl,
            'expected_cv_url' => $updateData['cv_url'] ?? 'NOT SET'
        ]);
        
        // Final verification - if still not updated, try one more time
        if (isset($updateData['cv_url']) && $actualCvUrl !== $updateData['cv_url']) {
            \Log::warning('CV URL still not updated, forcing update', [
                'expected' => $updateData['cv_url'],
                'actual' => $actualCvUrl
            ]);
            DB::table('instructors')
                ->where('id', $instructor->id)
                ->update(['cv_url' => $updateData['cv_url'], 'updated_at' => now()]);
            $instructor->refresh();
        }

        return response()->json(['message' => 'Instructor updated successfully', 'instructor' => $instructor->load('trainingCenter')]);
    }

    #[OA\Get(
        path: "/training-center/accs/{accId}/courses",
        summary: "Get courses by ACC and sub-category",
        description: "Get all active courses for a specific ACC, optionally filtered by sub-category.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accId", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "sub_category_id", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Courses retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "courses", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getAccCourses(Request $request, $accId)
    {
        $acc = \App\Models\ACC::findOrFail($accId);
        
        $query = \App\Models\Course::where('acc_id', $accId)
            ->where('status', 'active')
            ->with('subCategory');

        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        $courses = $query->get();

        return response()->json([
            'courses' => $courses,
            'acc' => [
                'id' => $acc->id,
                'name' => $acc->name,
            ]
        ]);
    }

    #[OA\Get(
        path: "/training-center/accs/{accId}/sub-categories",
        summary: "Get sub-categories with courses for an ACC",
        description: "Get all sub-categories that have courses in a specific ACC, with course counts.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accId", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Sub-categories retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "sub_categories", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getAccSubCategories($accId)
    {
        $acc = \App\Models\ACC::findOrFail($accId);
        
        $subCategories = \App\Models\SubCategory::whereHas('courses', function($query) use ($accId) {
            $query->where('acc_id', $accId)
                  ->where('status', 'active');
        })
        ->withCount(['courses' => function($query) use ($accId) {
            $query->where('acc_id', $accId)
                  ->where('status', 'active');
        }])
        ->get();

        return response()->json([
            'sub_categories' => $subCategories,
            'acc' => [
                'id' => $acc->id,
                'name' => $acc->name,
            ]
        ]);
    }

    #[OA\Post(
        path: "/training-center/instructors/{id}/request-authorization",
        summary: "Request instructor authorization",
        description: "Request authorization for an instructor to teach courses from an ACC. You can either select a sub-category (all courses in that sub-category) or select specific courses.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["acc_id"],
                properties: [
                    new OA\Property(property: "acc_id", type: "integer", example: 1, description: "ACC ID to request authorization from"),
                    new OA\Property(property: "sub_category_id", type: "integer", nullable: true, example: 5, description: "Sub-category ID - authorizes instructor for all courses in this sub-category"),
                    new OA\Property(property: "course_ids", type: "array", nullable: true, items: new OA\Items(type: "integer"), example: [1, 2, 3], description: "Array of specific course IDs to authorize"),
                    new OA\Property(property: "documents_json", type: "array", nullable: true, items: new OA\Items(type: "object"), description: "Optional documents array")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Authorization request submitted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Authorization request submitted successfully"),
                        new OA\Property(property: "authorization", type: "object"),
                        new OA\Property(property: "courses_count", type: "integer", example: 5, description: "Number of courses included in the authorization")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor or ACC not found"),
            new OA\Response(response: 422, description: "Validation error - either sub_category_id or course_ids must be provided, but not both")
        ]
    )]
    public function requestAuthorization(Request $request, $id)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'exists:courses,id',
            'documents_json' => 'nullable|array',
        ]);

        // Validate that either sub_category_id or course_ids is provided, but not both
        if (!$request->has('sub_category_id') && !$request->has('course_ids')) {
            return response()->json([
                'message' => 'Either sub_category_id or course_ids must be provided',
                'errors' => ['sub_category_id' => ['Either sub_category_id or course_ids is required']]
            ], 422);
        }

        if ($request->has('sub_category_id') && $request->has('course_ids')) {
            return response()->json([
                'message' => 'Cannot provide both sub_category_id and course_ids. Please provide only one.',
                'errors' => ['sub_category_id' => ['Cannot provide both sub_category_id and course_ids']]
            ], 422);
        }

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        // Get course IDs based on selection type
        $courseIds = [];
        if ($request->has('sub_category_id')) {
            // Get all courses in the sub-category that belong to the selected ACC
            $courseIds = \App\Models\Course::where('sub_category_id', $request->sub_category_id)
                ->where('acc_id', $request->acc_id)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            if (empty($courseIds)) {
                return response()->json([
                    'message' => 'No active courses found for the selected sub-category in this ACC'
                ], 422);
            }
        } else {
            // Validate that all course_ids belong to the selected ACC
            $accCourses = \App\Models\Course::where('acc_id', $request->acc_id)
                ->whereIn('id', $request->course_ids)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            if (count($accCourses) !== count($request->course_ids)) {
                return response()->json([
                    'message' => 'Some selected courses do not belong to the selected ACC or are not active'
                ], 422);
            }

            $courseIds = $request->course_ids;
        }

        $authorization = InstructorAccAuthorization::create([
            'instructor_id' => $instructor->id,
            'acc_id' => $request->acc_id,
            'sub_category_id' => $request->sub_category_id,
            'training_center_id' => $trainingCenter->id,
            'request_date' => now(),
            'status' => 'pending',
            'documents_json' => $request->documents_json ?? $request->documents,
        ]);

        // Store course IDs in documents_json for reference (or create a separate field)
        // For now, we'll add it to documents_json as metadata
        $documentsData = $request->documents_json ?? $request->documents ?? [];
        $documentsData['requested_course_ids'] = $courseIds;
        $authorization->update(['documents_json' => $documentsData]);

        // Send notification to ACC admin with enhanced details
        $acc = \App\Models\ACC::find($request->acc_id);
        if ($acc) {
            $accUser = User::where('email', $acc->email)->where('role', 'acc_admin')->first();
            if ($accUser) {
                $notificationService = new NotificationService();
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                
                // Get sub-category name if applicable
                $subCategoryName = null;
                if ($request->sub_category_id) {
                    $subCategory = \App\Models\SubCategory::find($request->sub_category_id);
                    $subCategoryName = $subCategory?->name;
                }
                
                $notificationService->notifyInstructorAuthorizationRequested(
                    $accUser->id,
                    $authorization->id,
                    $instructorName,
                    $trainingCenter->name,
                    $request->sub_category_id,
                    $courseIds,
                    $subCategoryName,
                    count($courseIds)
                );
            }
        }

        return response()->json([
            'message' => 'Authorization request submitted successfully',
            'authorization' => $authorization->load('subCategory'),
            'courses_count' => count($courseIds),
        ], 201);
    }

    public function destroy($id)
    {
        $instructor = Instructor::findOrFail($id);
        
        // Check if instructor belongs to the training center
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();
        
        if (!$trainingCenter || $instructor->training_center_id !== $trainingCenter->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $instructor->delete();
        
        return response()->json(['message' => 'Instructor deleted successfully']);
    }

    /**
     * Create payment intent for instructor authorization payment
     */
    public function createAuthorizationPaymentIntent(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorization = InstructorAccAuthorization::with(['instructor', 'acc'])
            ->where('id', $id)
            ->where('training_center_id', $trainingCenter->id)
            ->firstOrFail();

        // Verify authorization is approved and commission is set
        if ($authorization->status !== 'approved') {
            return response()->json([
                'message' => 'Authorization must be approved by ACC Admin first'
            ], 400);
        }

        if ($authorization->group_admin_status !== 'commission_set') {
            return response()->json([
                'message' => 'Group Admin must set commission percentage first'
            ], 400);
        }

        if ($authorization->payment_status === 'paid') {
            return response()->json([
                'message' => 'Authorization already paid'
            ], 400);
        }

        if (!$authorization->authorization_price || $authorization->authorization_price <= 0) {
            return response()->json([
                'message' => 'Authorization price not set'
            ], 400);
        }

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
        }

        // Calculate commission amounts
        $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
        $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;

        // Get ACC to check for Stripe account
        $acc = $authorization->acc;

        // Prepare metadata
        $metadata = [
            'authorization_id' => (string)$authorization->id,
            'training_center_id' => (string)$trainingCenter->id,
            'acc_id' => (string)$authorization->acc_id,
            'instructor_id' => (string)$authorization->instructor_id,
            'type' => 'instructor_authorization',
            'amount' => (string)$authorization->authorization_price,
            'group_commission_percentage' => (string)$groupCommissionPercentage,
            'group_commission_amount' => (string)$groupCommissionAmount,
        ];

        try {
            // Use destination charges if ACC has Stripe account ID
            if (!empty($acc->stripe_account_id) && $groupCommissionAmount > 0) {
                // Destination charge: money goes to ACC, commission goes to platform
                $result = $this->stripeService->createDestinationChargePaymentIntent(
                    $authorization->authorization_price,
                    $acc->stripe_account_id,
                    $groupCommissionAmount,
                    'usd',
                    $metadata
                );
                
                // If destination charge fails, fallback to standard payment
                if (!$result['success']) {
                    Log::warning('Destination charge failed, falling back to standard payment', [
                        'acc_id' => $acc->id,
                        'stripe_account_id' => $acc->stripe_account_id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                    
                    // Fallback to standard payment
                    $result = $this->stripeService->createPaymentIntent(
                        $authorization->authorization_price,
                        'USD',
                        $metadata
                    );
                }
            } else {
                // Regular payment intent (fallback if no Stripe account or no commission)
                $result = $this->stripeService->createPaymentIntent(
                    $authorization->authorization_price,
                    'USD',
                    $metadata
                );
            }

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error',
                    'error_code' => $result['error_code'] ?? 'unknown_error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $authorization->authorization_price,
                'currency' => $result['currency'] ?? 'USD',
                'commission_amount' => isset($result['commission_amount']) ? number_format($result['commission_amount'], 2, '.', '') : number_format($groupCommissionAmount, 2, '.', ''),
                'provider_amount' => isset($result['provider_amount']) ? number_format($result['provider_amount'], 2, '.', '') : null,
                'payment_type' => !empty($acc->stripe_account_id) && $groupCommissionAmount > 0 ? 'destination_charge' : 'standard',
                'authorization' => $authorization,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pay for instructor authorization
     * Called after Group Admin sets commission percentage
     */
    public function payAuthorization(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->findOrFail($id);

        // Verify authorization is ready for payment
        if ($authorization->status !== 'approved') {
            return response()->json([
                'message' => 'Authorization must be approved by ACC Admin first'
            ], 400);
        }

        if ($authorization->group_admin_status !== 'commission_set') {
            return response()->json([
                'message' => 'Group Admin must set commission percentage first'
            ], 400);
        }

        if ($authorization->payment_status === 'paid') {
            return response()->json([
                'message' => 'Authorization already paid'
            ], 400);
        }

        if (!$authorization->authorization_price || $authorization->authorization_price <= 0) {
            return response()->json([
                'message' => 'Authorization price not set'
            ], 400);
        }

        // Verify Stripe payment intent
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $authorization->authorization_price,
                    [
                        'authorization_id' => (string)$authorization->id,
                        'training_center_id' => (string)$trainingCenter->id,
                        'type' => 'instructor_authorization',
                    ]
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate commission amounts
            $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
            $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
            $accCommissionAmount = $authorization->authorization_price - $groupCommissionAmount;
            
            // Determine payment type and amounts
            $paymentType = 'standard';
            $commissionAmount = null;
            $providerAmount = null;
            
            // Check if destination charge was used (check payment intent metadata)
            try {
                $paymentIntent = $this->stripeService->retrievePaymentIntent($request->payment_intent_id);
                if ($paymentIntent && isset($paymentIntent->metadata->payment_type) && $paymentIntent->metadata->payment_type === 'destination_charge') {
                    $paymentType = 'destination_charge';
                    $commissionAmount = $groupCommissionAmount;
                    $providerAmount = $authorization->authorization_price - $groupCommissionAmount;
                } else {
                    // Standard payment - commission handled through ledger
                    $commissionAmount = $groupCommissionAmount;
                    $providerAmount = $accCommissionAmount;
                }
            } catch (\Exception $e) {
                // If can't retrieve payment intent, use calculated amounts
                $commissionAmount = $groupCommissionAmount;
                $providerAmount = $accCommissionAmount;
            }
            
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'instructor_authorization',
                'payer_type' => 'training_center',
                'payer_id' => $trainingCenter->id,
                'payee_type' => 'acc',
                'payee_id' => $authorization->acc_id,
                'amount' => $authorization->authorization_price,
                'commission_amount' => $commissionAmount,
                'provider_amount' => $providerAmount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_type' => $paymentType,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
                'description' => 'Instructor authorization payment for ' . ($authorization->instructor->first_name ?? '') . ' ' . ($authorization->instructor->last_name ?? ''),
                'reference_type' => 'InstructorAccAuthorization',
                'reference_id' => $authorization->id,
            ]);

            // Update authorization payment status
            $authorization->update([
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_transaction_id' => $transaction->id,
                'group_admin_status' => 'completed',
            ]);

            // Calculate and create commission ledger entries
            // Use commission_percentage from authorization (set by Group Admin)
            $groupCommissionPercentage = $authorization->commission_percentage ?? 0;
            $accCommissionPercentage = 100 - $groupCommissionPercentage;

            $groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
            $accCommissionAmount = ($authorization->authorization_price * $accCommissionPercentage) / 100;

            \App\Models\CommissionLedger::create([
                'transaction_id' => $transaction->id,
                'acc_id' => $authorization->acc_id,
                'training_center_id' => $trainingCenter->id,
                'instructor_id' => $authorization->instructor_id,
                'group_commission_amount' => $groupCommissionAmount,
                'group_commission_percentage' => $groupCommissionPercentage,
                'acc_commission_amount' => $accCommissionAmount,
                'acc_commission_percentage' => $accCommissionPercentage,
                'settlement_status' => 'pending',
            ]);

            DB::commit();

            // Send notifications
            $authorization->load(['instructor', 'acc', 'trainingCenter']);
            $notificationService = new NotificationService();
            $instructor = $authorization->instructor;
            $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
            
            // Notify Training Center about successful payment
            $notificationService->notifyInstructorAuthorizationPaymentSuccess(
                $user->id,
                $authorization->id,
                $instructorName,
                $authorization->authorization_price
            );
            
            // Notify Admin
            $notificationService->notifyInstructorAuthorizationPaid(
                $authorization->id,
                $instructorName,
                $authorization->authorization_price
            );

            return response()->json([
                'message' => 'Payment successful. Instructor is now officially authorized.',
                'authorization' => $authorization->fresh()->load(['instructor', 'acc', 'trainingCenter']),
                'transaction' => $transaction
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Payment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authorization requests with payment status
     * GET /api/training-center/instructors/authorizations
     */
    public function authorizations(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = InstructorAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->with([
                'instructor:id,first_name,last_name',
                'acc:id,name'
            ]);

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

        $authorizations = $query->orderBy('request_date', 'desc')->get();

        return response()->json(['authorizations' => $authorizations]);
    }
}

