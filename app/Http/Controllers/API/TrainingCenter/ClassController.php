<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Imports\ClassGradesImport;
use App\Models\TrainingClass;
use App\Models\ClassCompletion;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

class ClassController extends Controller
{
    #[OA\Get(
        path: "/training-center/classes",
        summary: "List training classes",
        description: "Get all training classes for the authenticated training center with pagination and search.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by class name, course name, instructor name, or status"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["scheduled", "in_progress", "completed", "cancelled"]), description: "Filter by class status"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Classes retrieved successfully",
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

        // Base query for all classes (for total count)
        $baseQuery = TrainingClass::where('training_center_id', $trainingCenter->id);

        // Calculate total counts for each status (before any filters)
        $totalCount = $baseQuery->count();
        $completedCount = (clone $baseQuery)->where('status', 'completed')->count();
        $scheduledCount = (clone $baseQuery)->where('status', 'scheduled')->count();
        $inProgressCount = (clone $baseQuery)->where('status', 'in_progress')->count();
        $cancelledCount = (clone $baseQuery)->where('status', 'cancelled')->count();

        // Query for filtered results
        $query = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with(['course', 'instructor', 'trainees', 'createdBy']);

        // Filter by status if provided
        if ($request->has('status')) {
            $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
            if (in_array($request->status, $validStatuses)) {
                $query->where('status', $request->status);
            }
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('status', 'like', "%{$searchTerm}%")
                    ->orWhereHas('course', function ($courseQuery) use ($searchTerm) {
                        $courseQuery->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('instructor', function ($instructorQuery) use ($searchTerm) {
                        $instructorQuery->where('first_name', 'like', "%{$searchTerm}%")
                            ->orWhere('last_name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $classes = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(function ($class) {
                // Auto-update status based on start_date and end_date
                // Don't update if status is 'completed' or 'cancelled' - these are manually set
                if ($class->start_date && $class->end_date && 
                    $class->status !== 'cancelled' && $class->status !== 'completed') {
                    $today = now()->toDateString();
                    $startDate = $class->start_date;
                    $endDate = $class->end_date;
                    $currentStatus = $class->status;

                    // Determine the appropriate status based on dates
                    if ($today < $startDate) {
                        // Class hasn't started yet
                        $newStatus = 'scheduled';
                    } elseif ($today >= $startDate && $today <= $endDate) {
                        // Class is currently in progress
                        $newStatus = 'in_progress';
                    } else {
                        // Class has ended
                        $newStatus = 'completed';
                    }

                    // Update status if it has changed
                    if ($currentStatus !== $newStatus) {
                        $class->status = $newStatus;
                        $class->save();
                    }
                }

                // Preload certificates for this class to expose download info per trainee
                $certificatesByName = \App\Models\Certificate::where('training_class_id', $class->id)
                    ->get()
                    ->groupBy('trainee_name');

                $classData = $class->toArray();

                // Format trainees data with exam score, status, and certificate URLs
                $successGrade = $class->success_grade;
                $classData['trainees'] = $class->trainees->map(function ($trainee) use ($successGrade, $certificatesByName) {
                    $fullName = trim(($trainee->first_name ?? '') . ' ' . ($trainee->last_name ?? ''));
                    $examScore = $trainee->pivot->exam_score !== null ? (float) $trainee->pivot->exam_score : null;

                    $examStatus = null;
                    if ($examScore !== null && $successGrade !== null) {
                        $examStatus = $examScore >= (float) $successGrade ? 'success' : 'fail';
                    }

                    $certificate = null;
                    if ($fullName !== '' && $certificatesByName->has($fullName)) {
                        $certificate = $certificatesByName->get($fullName)->first();
                    }

                    return [
                        'id' => $trainee->id,
                        'first_name' => $trainee->first_name,
                        'last_name' => $trainee->last_name,
                        'full_name' => $fullName,
                        'email' => $trainee->email,
                        'phone' => $trainee->phone,
                        'id_number' => $trainee->id_number,
                        'status' => $trainee->pivot->status ?? null,
                        'exam_score' => $examScore,
                        'exam_status' => $examStatus,
                        'enrolled_at' => $trainee->pivot->enrolled_at ?? null,
                        'completed_at' => $trainee->pivot->completed_at ?? null,
                        'certificate' => $certificate ? [
                            'id' => $certificate->id,
                            'certificate_number' => $certificate->certificate_number,
                            'verification_code' => $certificate->verification_code,
                            'certificate_pdf_url' => $certificate->certificate_pdf_url,
                            'card_pdf_url' => $certificate->card_pdf_url,
                            'status' => $certificate->status,
                            'issue_date' => $certificate->issue_date,
                            'expiry_date' => $certificate->expiry_date,
                        ] : null,
                    ];
                })->values();

                // Convert exam_score/success_grade to int on class level if they exist
                if (isset($classData['exam_score']) && $classData['exam_score'] !== null) {
                    $classData['exam_score'] = (int) round($classData['exam_score']);
                }
                if (isset($classData['success_grade']) && $classData['success_grade'] !== null) {
                    $classData['success_grade'] = (int) round($classData['success_grade']);
                }

                // Keep enrolled_count for backward compatibility
                $classData['trainees_count'] = $class->trainees->count();

                return $classData;
            });

        // Add statistics to response and override total
        $response = $classes->toArray();
        
        // Override total with the actual total count (before filters)
        $response['total'] = $totalCount;
        
        // Add statistics
        $response['statistics'] = [
            'total' => $totalCount,
            'completed' => $completedCount,
            'scheduled' => $scheduledCount,
            'in_progress' => $inProgressCount,
            'cancelled' => $cancelledCount,
        ];

        return response()->json($response);
    }

    #[OA\Post(
        path: "/training-center/classes",
        summary: "Create training class",
        description: "Create a new training class. The course must belong to an ACC that has authorized the training center.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["course_id", "name", "instructor_id", "start_date", "end_date", "location"],
                properties: [
                    new OA\Property(property: "course_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Class A - January 2024", description: "Class name"),
                    new OA\Property(property: "instructor_id", type: "integer", example: 1),
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2024-01-15"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2024-01-20"),
                    new OA\Property(property: "exam_date", type: "string", format: "date", nullable: true, example: "2024-01-25"),
                    new OA\Property(property: "exam_score", type: "number", format: "float", nullable: true, example: 85.50),
                    new OA\Property(property: "success_grade", type: "number", format: "float", nullable: true, example: 60.00, description: "Minimum score required to pass the exam"),
                    new OA\Property(property: "schedule_json", type: "array", nullable: true, items: new OA\Items(type: "object")),
                    new OA\Property(property: "location", type: "string", enum: ["physical", "online"], example: "physical"),
                    new OA\Property(property: "location_details", type: "string", nullable: true, example: "Room 101"),
                    new OA\Property(property: "trainee_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3], nullable: true, description: "Array of trainee IDs to enroll in this training class")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Class created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "class", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Course not available - ACC authorization required, Instructor not authorized to teach this course, or Training center has not paid instructor authorization payment to ACC"),
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'instructor_id' => 'required|exists:instructors,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'exam_date' => 'nullable|date|after_or_equal:start_date',
            'exam_score' => 'nullable|numeric|min:0|max:100',
            'success_grade' => 'nullable|numeric|min:0|max:100|lte:exam_score',
            'schedule_json' => 'nullable|array',
            'location' => 'required|in:physical,online',
            'location_details' => 'nullable|string',
            'trainee_ids' => 'nullable|array',
            'trainee_ids.*' => 'exists:trainees,id',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Verify the course belongs to an approved ACC
        $approvedAccIds = \App\Models\TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('status', 'approved')
            ->pluck('acc_id');

        $course = \App\Models\Course::findOrFail($request->course_id);
        if (!$approvedAccIds->contains($course->acc_id)) {
            return response()->json(['message' => 'Course not available. ACC authorization required.'], 403);
        }

        // Verify the instructor is authorized to teach this course from the ACC
        $instructorAuthorization = \App\Models\InstructorCourseAuthorization::where('instructor_id', $request->instructor_id)
            ->where('course_id', $request->course_id)
            ->where('acc_id', $course->acc_id)
            ->where('status', 'active')
            ->first();

        if (!$instructorAuthorization) {
            return response()->json([
                'message' => 'Instructor is not authorized to teach this course from the ACC.'
            ], 403);
        }

        // Check if training center has paid for instructor authorization to ACC
        // Must be approved AND payment_status must be 'paid' (strict enforcement)
        $instructorAccAuthorization = \App\Models\InstructorAccAuthorization::where('instructor_id', $request->instructor_id)
            ->where('acc_id', $course->acc_id)
            ->where('training_center_id', $trainingCenter->id)
            ->where('status', 'approved')
            ->where('payment_status', 'paid') // STRICT: Only 'paid' status allowed
            ->whereNotNull('payment_status') // Additional safeguard: ensure not null
            ->first();

        if (!$instructorAccAuthorization) {
            // Get the authorization to provide better error message
            $authCheck = \App\Models\InstructorAccAuthorization::where('instructor_id', $request->instructor_id)
                ->where('acc_id', $course->acc_id)
                ->where('training_center_id', $trainingCenter->id)
                ->where('status', 'approved')
                ->first();
            
            if (!$authCheck) {
                return response()->json([
                    'message' => 'Instructor authorization to ACC is not approved or does not exist.'
                ], 403);
            }
            
            // Authorization exists but payment is not paid
            $paymentStatus = $authCheck->payment_status ?? 'not_set';
            $authorizationPrice = $authCheck->authorization_price ?? 0;
            
            // Build detailed error message
            $errorMessage = 'Instructor cannot be assigned to any class until the training center has paid the instructor authorization payment to the ACC.';
            
            if ($paymentStatus === 'pending') {
                $errorMessage = 'Instructor cannot be assigned to any class. Payment is still pending. Please complete the payment for instructor authorization.';
            } elseif ($paymentStatus === 'failed') {
                $errorMessage = 'Instructor cannot be assigned to any class. Payment has failed. Please retry the payment for instructor authorization.';
            } elseif ($paymentStatus === 'not_set' || !$paymentStatus) {
                $errorMessage = 'Instructor cannot be assigned to any class. Payment status is not set. Please complete the payment for instructor authorization.';
            }
            
            return response()->json([
                'message' => $errorMessage,
                'payment_status' => $paymentStatus,
                'authorization_price' => $authorizationPrice,
                'hint' => 'Please complete the payment for instructor authorization before assigning the instructor to a class.',
                'authorization_id' => $authCheck->id,
            ], 403);
        }
        
        // Additional validation: Ensure authorization_price is set (even if 0)
        // This ensures the payment process was completed
        if ($instructorAccAuthorization->authorization_price === null) {
            return response()->json([
                'message' => 'Instructor authorization price is not set. Please contact ACC to set the authorization price and complete payment.',
                'authorization_id' => $instructorAccAuthorization->id,
            ], 403);
        }

        // Validate trainees belong to the training center before creating the class
        if ($request->has('trainee_ids') && is_array($request->trainee_ids) && !empty($request->trainee_ids)) {
            $validTraineeIds = \App\Models\Trainee::where('training_center_id', $trainingCenter->id)
                ->whereIn('id', $request->trainee_ids)
                ->pluck('id')
                ->toArray();
            
            if (count($validTraineeIds) !== count($request->trainee_ids)) {
                return response()->json([
                    'message' => 'Some trainee IDs do not belong to your training center'
                ], 422);
            }
        }

        $class = TrainingClass::create([
            'training_center_id' => $trainingCenter->id,
            'course_id' => $request->course_id,
            'name' => $request->name,
            'created_by' => $user->id,
            'instructor_id' => $request->instructor_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'exam_date' => $request->exam_date,
            'exam_score' => $request->exam_score,
            'success_grade' => $request->success_grade,
            'schedule_json' => $request->schedule_json ?? $request->schedule,
            'enrolled_count' => 0,
            'status' => 'scheduled',
            'location' => $request->location,
            'location_details' => $request->location_details,
        ]);

        // Attach trainees if provided
        if ($request->has('trainee_ids') && is_array($request->trainee_ids) && !empty($request->trainee_ids)) {
            $pivotData = [];
            foreach ($request->trainee_ids as $traineeId) {
                $pivotData[$traineeId] = [
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ];
            }
            $class->trainees()->attach($pivotData);
            $class->increment('enrolled_count', count($pivotData));
        }

        return response()->json(['class' => $class->load(['course', 'instructor', 'trainees'])], 201);
    }

    #[OA\Get(
        path: "/training-center/classes/{id}",
        summary: "Get class details",
        description: "Get detailed information about a specific training class including trainees and their enrollment/exam status.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Class retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "class", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found")
        ]
    )]
    public function show($id)
    {
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with(['course', 'instructor', 'trainingCenter', 'completion', 'trainees', 'createdBy'])
            ->findOrFail($id);

        // Auto-update status based on start_date and end_date
        // Don't update if status is 'completed' or 'cancelled' - these are manually set
        if ($class->start_date && $class->end_date && 
            $class->status !== 'cancelled' && $class->status !== 'completed') {
            $today = now()->toDateString();
            $startDate = $class->start_date;
            $endDate = $class->end_date;
            $currentStatus = $class->status;

            // Determine the appropriate status based on dates
            if ($today < $startDate) {
                // Class hasn't started yet
                $newStatus = 'scheduled';
            } elseif ($today >= $startDate && $today <= $endDate) {
                // Class is currently in progress
                $newStatus = 'in_progress';
            } else {
                // Class has ended
                $newStatus = 'completed';
            }

            // Update status if it has changed
            if ($currentStatus !== $newStatus) {
                $class->status = $newStatus;
                $class->save();
            }
        }

        $class->load('trainees');

        // Preload certificates for this class to expose download info per trainee
        $certificatesByName = \App\Models\Certificate::where('training_class_id', $class->id)
            ->get()
            ->groupBy('trainee_name');

        // Map trainees with exam result (success/fail) based on success_grade if available
        $successGrade = $class->success_grade;
        $classArray = $class->toArray();
        $classArray['trainees'] = $class->trainees->map(function ($trainee) use ($successGrade, $certificatesByName) {
            $fullName = trim(($trainee->first_name ?? '') . ' ' . ($trainee->last_name ?? ''));
            $examScore = $trainee->pivot->exam_score !== null ? (float) $trainee->pivot->exam_score : null;

            // Derive exam_status field (success/fail/pending) for UI convenience
            $examStatus = null;
            if ($examScore !== null && $successGrade !== null) {
                $examStatus = $examScore >= (float) $successGrade ? 'success' : 'fail';
            }

            // Try to find a certificate for this trainee in this class (by full name)
            $certificate = null;
            if ($fullName !== '' && $certificatesByName->has($fullName)) {
                $certificate = $certificatesByName->get($fullName)->first();
            }

            return [
                'id' => $trainee->id,
                'first_name' => $trainee->first_name,
                'last_name' => $trainee->last_name,
                'full_name' => $fullName,
                'email' => $trainee->email,
                'phone' => $trainee->phone,
                'id_number' => $trainee->id_number,
                'status' => $trainee->pivot->status ?? null,
                'exam_score' => $examScore,
                'exam_status' => $examStatus,
                'enrolled_at' => $trainee->pivot->enrolled_at ?? null,
                'completed_at' => $trainee->pivot->completed_at ?? null,
                'certificate' => $certificate ? [
                    'id' => $certificate->id,
                    'certificate_number' => $certificate->certificate_number,
                    'verification_code' => $certificate->verification_code,
                    'certificate_pdf_url' => $certificate->certificate_pdf_url,
                    'card_pdf_url' => $certificate->card_pdf_url,
                    'status' => $certificate->status,
                    'issue_date' => $certificate->issue_date,
                    'expiry_date' => $certificate->expiry_date,
                ] : null,
            ];
        })->values();

        // Normalize numeric fields
        if (isset($classArray['exam_score']) && $classArray['exam_score'] !== null) {
            $classArray['exam_score'] = (int) round($classArray['exam_score']);
        }
        if (isset($classArray['success_grade']) && $classArray['success_grade'] !== null) {
            $classArray['success_grade'] = (int) round($classArray['success_grade']);
        }

        return response()->json(['class' => $classArray]);
    }

    #[OA\Put(
        path: "/training-center/classes/{id}",
        summary: "Update training class",
        description: "Update training class information.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "course_id", type: "integer", nullable: true),
                    new OA\Property(property: "instructor_id", type: "integer", nullable: true),
                    new OA\Property(property: "start_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "end_date", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "exam_date", type: "string", format: "date", nullable: true, example: "2024-01-25"),
                    new OA\Property(property: "exam_score", type: "number", format: "float", nullable: true, example: 85.50),
                    new OA\Property(property: "success_grade", type: "number", format: "float", nullable: true, example: 60.00, description: "Minimum score required to pass the exam"),
                    new OA\Property(property: "schedule_json", type: "array", nullable: true, items: new OA\Items(type: "object")),
                    new OA\Property(property: "location", type: "string", enum: ["physical", "online"], nullable: true),
                    new OA\Property(property: "location_details", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["scheduled", "in_progress", "completed", "cancelled"], nullable: true),
                    new OA\Property(property: "trainee_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3], nullable: true, description: "Array of trainee IDs to sync with this training class (replaces existing enrollments)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Class updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Class updated successfully"),
                        new OA\Property(property: "class", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Instructor not authorized to teach this course from the ACC, or Training center has not paid instructor authorization payment to ACC"),
            new OA\Response(response: 404, description: "Class not found"),
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

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'name' => 'sometimes|string|max:255',
            'instructor_id' => 'sometimes|exists:instructors,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'exam_date' => 'nullable|date|after_or_equal:start_date',
            'exam_score' => 'nullable|numeric|min:0|max:100',
            'success_grade' => 'nullable|numeric|min:0|max:100|lte:exam_score',
            'schedule_json' => 'nullable|array',
            'location' => 'sometimes|in:physical,online',
            'location_details' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'trainee_ids' => 'nullable|array',
            'trainee_ids.*' => 'exists:trainees,id',
        ]);

        // Determine which course and instructor to validate
        $courseId = $request->has('course_id') ? $request->course_id : $class->course_id;
        $instructorId = $request->has('instructor_id') ? $request->instructor_id : $class->instructor_id;

        // Always verify instructor authorization and payment status when updating class
        // This ensures payment is validated even if instructor/course hasn't changed
        $course = \App\Models\Course::findOrFail($courseId);
        
        // Verify the instructor is authorized to teach this course from the ACC
        $instructorAuthorization = \App\Models\InstructorCourseAuthorization::where('instructor_id', $instructorId)
            ->where('course_id', $courseId)
            ->where('acc_id', $course->acc_id)
            ->where('status', 'active')
            ->first();

        if (!$instructorAuthorization) {
            return response()->json([
                'message' => 'Instructor is not authorized to teach this course from the ACC.'
            ], 403);
        }

        // Check if training center has paid for instructor authorization to ACC
        // Must be approved AND payment_status must be 'paid' (strict enforcement)
        $instructorAccAuthorization = \App\Models\InstructorAccAuthorization::where('instructor_id', $instructorId)
            ->where('acc_id', $course->acc_id)
            ->where('training_center_id', $trainingCenter->id)
            ->where('status', 'approved')
            ->where('payment_status', 'paid') // STRICT: Only 'paid' status allowed
            ->whereNotNull('payment_status') // Additional safeguard: ensure not null
            ->first();

        if (!$instructorAccAuthorization) {
            // Get the authorization to provide better error message
            $authCheck = \App\Models\InstructorAccAuthorization::where('instructor_id', $instructorId)
                ->where('acc_id', $course->acc_id)
                ->where('training_center_id', $trainingCenter->id)
                ->where('status', 'approved')
                ->first();
            
            if (!$authCheck) {
                return response()->json([
                    'message' => 'Instructor authorization to ACC is not approved or does not exist.'
                ], 403);
            }
            
            // Authorization exists but payment is not paid
            $paymentStatus = $authCheck->payment_status ?? 'not_set';
            $authorizationPrice = $authCheck->authorization_price ?? 0;
            
            // Build detailed error message
            $errorMessage = 'Instructor cannot be assigned to any class until the training center has paid the instructor authorization payment to the ACC.';
            
            if ($paymentStatus === 'pending') {
                $errorMessage = 'Instructor cannot be assigned to any class. Payment is still pending. Please complete the payment for instructor authorization.';
            } elseif ($paymentStatus === 'failed') {
                $errorMessage = 'Instructor cannot be assigned to any class. Payment has failed. Please retry the payment for instructor authorization.';
            } elseif ($paymentStatus === 'not_set' || !$paymentStatus) {
                $errorMessage = 'Instructor cannot be assigned to any class. Payment status is not set. Please complete the payment for instructor authorization.';
            }
            
            return response()->json([
                'message' => $errorMessage,
                'payment_status' => $paymentStatus,
                'authorization_price' => $authorizationPrice,
                'hint' => 'Please complete the payment for instructor authorization before assigning the instructor to a class.',
                'authorization_id' => $authCheck->id,
            ], 403);
        }
        
        // Additional validation: Ensure authorization_price is set (even if 0)
        // This ensures the payment process was completed
        if ($instructorAccAuthorization->authorization_price === null) {
            return response()->json([
                'message' => 'Instructor authorization price is not set. Please contact ACC to set the authorization price and complete payment.',
                'authorization_id' => $instructorAccAuthorization->id,
            ], 403);
        }

        $updateData = $request->only([
            'course_id', 'name', 'instructor_id', 'start_date', 'end_date',
            'exam_date', 'exam_score', 'success_grade', 'location', 'location_details', 'status'
        ]);

        if ($request->has('schedule_json') || $request->has('schedule')) {
            $updateData['schedule_json'] = $request->schedule_json ?? $request->schedule;
        }

        $class->update($updateData);

        // Sync trainees if provided (validate they belong to the training center)
        if ($request->has('trainee_ids')) {
            $validTraineeIds = \App\Models\Trainee::where('training_center_id', $trainingCenter->id)
                ->whereIn('id', $request->trainee_ids)
                ->pluck('id')
                ->toArray();
            
            if (count($validTraineeIds) !== count($request->trainee_ids)) {
                return response()->json([
                    'message' => 'Some trainee IDs do not belong to your training center'
                ], 422);
            }

            $oldCount = $class->trainees()->count();
            $pivotData = [];
            foreach ($validTraineeIds as $traineeId) {
                $pivotData[$traineeId] = [
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ];
            }
            $class->trainees()->sync($pivotData);
            $newCount = count($pivotData);
            $class->enrolled_count = $newCount;
            $class->save();
        }

        return response()->json(['message' => 'Class updated successfully', 'class' => $class->load(['course', 'instructor', 'trainees'])]);
    }

    #[OA\Delete(
        path: "/training-center/classes/{id}",
        summary: "Delete training class",
        description: "Delete a training class. This action cannot be undone.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Class deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Class deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found")
        ]
    )]
    public function destroy($id)
    {
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)->findOrFail($id);
        $class->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }

    #[OA\Put(
        path: "/training-center/classes/{id}/complete",
        summary: "Mark class as completed",
        description: "Mark a training class as completed, update its status to 'completed', and create a completion record.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Class marked as completed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Class marked as completed"),
                        new OA\Property(property: "class", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found")
        ]
    )]
    public function complete(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        // Always update the status to 'completed' when marking as complete
        $wasAlreadyCompleted = $class->status === 'completed';
        
        // Update status in training_classes table explicitly
        $updated = TrainingClass::where('id', $class->id)
            ->update([
                'status' => 'completed',
                'end_date' => now()->toDateString(),
                'updated_at' => now(),
            ]);
        
        if (!$updated) {
            \Illuminate\Support\Facades\Log::error('Failed to update training class status in database', [
                'class_id' => $class->id,
            ]);
            
            return response()->json([
                'message' => 'Failed to update class status in database',
                'error' => 'Status update failed'
            ], 500);
        }
        
        // Refresh the model to get updated data
        $class->refresh();
        
        // Verify the status was updated successfully
        if ($class->status !== 'completed') {
            \Illuminate\Support\Facades\Log::error('Training class status verification failed', [
                'class_id' => $class->id,
                'expected_status' => 'completed',
                'actual_status' => $class->status
            ]);
            
            return response()->json([
                'message' => 'Class status was not updated correctly',
                'error' => 'Status verification failed',
                'current_status' => $class->status
            ], 500);
        }

        // Only create completion record if it doesn't exist
        $completion = ClassCompletion::firstOrCreate(
            ['training_class_id' => $class->id],
            [
                'completed_date' => now(),
                'completion_rate_percentage' => 100,
                'certificates_generated_count' => 0,
                'marked_by' => $user->id,
            ]
        );

        // Update completion record if it already existed
        if ($completion->wasRecentlyCreated === false) {
            $completion->update([
                'completed_date' => now(),
                'marked_by' => $user->id,
            ]);
        }

        // Send notification to instructor only if it wasn't already completed
        if (!$wasAlreadyCompleted) {
            $class->load(['instructor', 'course']);
            $instructor = $class->instructor;
            if ($instructor) {
                $instructorUser = \App\Models\User::where('email', $instructor->email)->first();
                if ($instructorUser) {
                    $notificationService = new \App\Services\NotificationService();
                    $className = $class->name ?? "Class #{$class->id}";
                    $courseName = $class->course ? $class->course->name : 'Unknown Course';
                    
                    $notificationService->notifyInstructorClassCompleted(
                        $instructorUser->id,
                        $class->id,
                        $className,
                        $courseName,
                        $trainingCenter->name,
                        100
                    );
                }
            }
        }

        // Reload class with relationships
        $class = $class->fresh(['course', 'instructor', 'trainees', 'completion']);

        return response()->json([
            'message' => 'Class marked as completed',
            'class' => $class
        ]);
    }

    #[OA\Post(
        path: "/training-center/classes/{id}/grades",
        summary: "Save trainee exam grades for a class",
        description: "Save or update exam scores for trainees in a class. Automatically sets each trainee status to 'completed' (success) or 'failed' based on the class success_grade.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["grades"],
                properties: [
                    new OA\Property(
                        property: "grades",
                        type: "array",
                        description: "Array of trainee exam grades",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "trainee_id", type: "integer", example: 1),
                                new OA\Property(property: "score", type: "number", format: "float", example: 75.5, description: "Exam score for this trainee (same scale as class exam_score)")
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Grades saved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Grades saved successfully"),
                        new OA\Property(property: "class", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found"),
            new OA\Response(response: 422, description: "Validation error or success_grade not configured for class")
        ]
    )]
    public function saveGrades(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with('trainees')
            ->findOrFail($id);

        // Ensure class has exam_score and success_grade configured
        if ($class->exam_score === null || $class->success_grade === null) {
            return response()->json([
                'message' => 'Exam configuration is missing for this class. Please set exam_score and success_grade first.',
            ], 422);
        }

        $request->validate([
            'grades' => 'required|array|min:1',
            'grades.*.trainee_id' => 'required|integer',
            'grades.*.score' => 'required|numeric|min:0|max:100',
        ]);

        $blockedTrainees = $this->getTraineesWithCertificatesBlockingGradeUpdate($class, $request->grades);
        if (!empty($blockedTrainees)) {
            return response()->json([
                'message' => 'Cannot change score for trainee(s) who already have a certificate. Score is locked once a certificate has been issued.',
                'errors' => [
                    'grades' => ['The following trainee(s) have certificates and cannot be updated: ' . implode(', ', $blockedTrainees) . '.'],
                ],
                'blocked_trainees' => $blockedTrainees,
            ], 422);
        }

        $this->updateTraineeGrades($class, $request->grades);

        $class->refresh();

        return response()->json([
            'message' => 'Grades saved successfully',
            'class' => $class->load(['course', 'instructor', 'trainees']),
        ]);
    }

    #[OA\Get(
        path: "/training-center/classes/{id}/grades/export",
        summary: "Download Excel-compatible grades template for a class",
        description: "Download a CSV/Excel-compatible file containing all trainees in the class with exam_score and certificate_pdf_url (for trainees who already have a certificate) columns.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(response: 200, description: "CSV template downloaded successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found")
        ]
    )]
    public function exportGradesTemplate(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with('trainees')
            ->findOrFail($id);

        // Preload certificates for this class to include certificate URL for trainees who have one
        $certificatesByName = \App\Models\Certificate::where('training_class_id', $class->id)
            ->get()
            ->keyBy('trainee_name');

        $rows = [];
        $rows[] = ['trainee_id', 'first_name', 'last_name', 'email', 'id_number', 'exam_score', 'certificate_pdf_url'];

        foreach ($class->trainees as $trainee) {
            $fullName = trim(($trainee->first_name ?? '') . ' ' . ($trainee->last_name ?? ''));
            $certificateUrl = $certificatesByName->has($fullName)
                ? ($certificatesByName->get($fullName)->certificate_pdf_url ?? '')
                : '';

            $rows[] = [
                $trainee->id,
                $trainee->first_name,
                $trainee->last_name,
                $trainee->email,
                $trainee->id_number,
                $trainee->pivot->exam_score !== null ? (float) $trainee->pivot->exam_score : '',
                $certificateUrl,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $fileName = 'class_' . $class->id . '_grades.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    #[OA\Post(
        path: "/training-center/classes/{id}/grades/import",
        summary: "Import trainee exam grades from Excel/CSV file",
        description: "Upload an Excel-compatible CSV file (exported from the class grades template) to bulk update exam scores and success/fail status for all trainees.",
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
                    required: ["file"],
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "CSV/Excel file exported from the grades template")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Grades imported successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Grades imported successfully"),
                        new OA\Property(property: "updated_count", type: "integer", example: 10),
                        new OA\Property(property: "skipped_count", type: "integer", example: 2)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid or missing file"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found"),
            new OA\Response(response: 422, description: "Exam configuration missing or file format invalid")
        ]
    )]
    public function importGradesFromFile(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with('trainees')
            ->findOrFail($id);

        // Ensure class has exam_score and success_grade configured
        if ($class->exam_score === null || $class->success_grade === null) {
            return response()->json([
                'message' => 'Exam configuration is missing for this class. Please set exam_score and success_grade first.',
            ], 422);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'No file uploaded'], 400);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $grades = [];
        $updated = 0;
        $skipped = 0;

        // CSV/TXT: use native fgetcsv for robustness
        if (in_array($extension, ['csv', 'txt'], true)) {
            $path = $file->getRealPath();

            if (!$path) {
                return response()->json(['message' => 'Unable to read uploaded file'], 400);
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                return response()->json(['message' => 'Unable to open uploaded file'], 400);
            }

            $header = fgetcsv($handle);
            if ($header === false) {
                fclose($handle);
                return response()->json(['message' => 'Empty or invalid CSV file'], 422);
            }

            $header = array_map('trim', $header);
            $traineeIdIndex = array_search('trainee_id', $header, true);
            $scoreIndex = array_search('exam_score', $header, true);

            if ($traineeIdIndex === false || $scoreIndex === false) {
                fclose($handle);
                return response()->json([
                    'message' => 'Invalid file format. Header must contain at least trainee_id and exam_score columns.',
                ], 422);
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[$traineeIdIndex]) || $row[$traineeIdIndex] === '') {
                    $skipped++;
                    continue;
                }

                $traineeId = (int) $row[$traineeIdIndex];
                $scoreRaw = $row[$scoreIndex] ?? '';

                if ($scoreRaw === '' || $scoreRaw === null) {
                    $skipped++;
                    continue;
                }

                if (!is_numeric($scoreRaw)) {
                    $skipped++;
                    continue;
                }

                $score = (float) $scoreRaw;

                if ($score < 0 || $score > 100) {
                    $skipped++;
                    continue;
                }

                $grades[] = [
                    'trainee_id' => $traineeId,
                    'score' => $score,
                ];
                $updated++;
            }

            fclose($handle);
        } else {
            // XLSX and other spreadsheet formats: use Laravel Excel
            try {
                $sheets = Excel::toArray(new ClassGradesImport(), $file);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Failed to read uploaded file',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 400);
            }

            $rows = $sheets[0] ?? [];
            if (empty($rows)) {
                return response()->json(['message' => 'Empty or invalid file'], 422);
            }

            $header = array_map(static fn ($v) => trim((string) $v), $rows[0]);
            $traineeIdIndex = array_search('trainee_id', $header, true);
            $scoreIndex = array_search('exam_score', $header, true);

            if ($traineeIdIndex === false || $scoreIndex === false) {
                return response()->json([
                    'message' => 'Invalid file format. Header must contain at least trainee_id and exam_score columns.',
                ], 422);
            }

            foreach (array_slice($rows, 1) as $row) {
                if (!is_array($row)) {
                    $skipped++;
                    continue;
                }

                $traineeIdRaw = $row[$traineeIdIndex] ?? null;
                if ($traineeIdRaw === null || $traineeIdRaw === '') {
                    $skipped++;
                    continue;
                }

                $traineeId = (int) $traineeIdRaw;
                $scoreRaw = $row[$scoreIndex] ?? '';

                if ($scoreRaw === '' || $scoreRaw === null) {
                    $skipped++;
                    continue;
                }

                if (!is_numeric($scoreRaw)) {
                    $skipped++;
                    continue;
                }

                $score = (float) $scoreRaw;

                if ($score < 0 || $score > 100) {
                    $skipped++;
                    continue;
                }

                $grades[] = [
                    'trainee_id' => $traineeId,
                    'score' => $score,
                ];
                $updated++;
            }
        }

        if (!empty($grades)) {
            $blockedTrainees = $this->getTraineesWithCertificatesBlockingGradeUpdate($class, $grades);
            if (!empty($blockedTrainees)) {
                return response()->json([
                    'message' => 'Cannot change score for trainee(s) who already have a certificate. Score is locked once a certificate has been issued.',
                    'errors' => [
                        'file' => ['The following trainee(s) in the file have certificates and cannot be updated: ' . implode(', ', $blockedTrainees) . '.'],
                    ],
                    'blocked_trainees' => $blockedTrainees,
                ], 422);
            }

            $this->updateTraineeGrades($class, $grades);
        }

        return response()->json([
            'message' => 'Grades imported successfully',
            'updated_count' => $updated,
            'skipped_count' => $skipped,
        ]);
    }

    /**
     * Get trainee names that have certificates for this class/course and therefore cannot have their score changed.
     *
     * @param TrainingClass $class
     * @param array<int, array{trainee_id:int, score:float}> $grades
     * @return array<int, string> List of full names of blocked trainees
     */
    private function getTraineesWithCertificatesBlockingGradeUpdate(TrainingClass $class, array $grades): array
    {
        $class->loadMissing('trainees');
        $enrolledIds = $class->trainees->pluck('id')->toArray();
        $blocked = [];

        foreach ($grades as $grade) {
            if (!isset($grade['trainee_id'])) {
                continue;
            }
            $traineeId = (int) $grade['trainee_id'];
            if (!in_array($traineeId, $enrolledIds, true)) {
                continue;
            }

            $trainee = $class->trainees->firstWhere('id', $traineeId);
            if (!$trainee) {
                continue;
            }

            $fullName = trim(($trainee->first_name ?? '') . ' ' . ($trainee->last_name ?? ''));
            if ($fullName === '') {
                continue;
            }

            $hasCertificate = \App\Models\Certificate::where('course_id', $class->course_id)
                ->where('training_center_id', $class->training_center_id)
                ->where('trainee_name', $fullName)
                ->whereIn('status', ['valid', 'expired'])
                ->exists();

            if ($hasCertificate && !in_array($fullName, $blocked, true)) {
                $blocked[] = $fullName;
            }
        }

        return $blocked;
    }

    /**
     * Apply an array of grades to a class, updating pivot exam_score and pass/fail status.
     *
     * @param TrainingClass $class
     * @param array<int, array{trainee_id:int, score:float}> $grades
     */
    private function updateTraineeGrades(TrainingClass $class, array $grades): void
    {
        $class->loadMissing('trainees');
        $enrolledIds = $class->trainees->pluck('id')->toArray();

        foreach ($grades as $grade) {
            if (!isset($grade['trainee_id'], $grade['score'])) {
                continue;
            }

            $traineeId = (int) $grade['trainee_id'];
            $score = (float) $grade['score'];

            if (!in_array($traineeId, $enrolledIds, true)) {
                continue;
            }

             // If a certificate already exists for this trainee & course, do NOT allow changing the score
             $trainee = $class->trainees->firstWhere('id', $traineeId);
             if ($trainee) {
                 $fullName = trim(($trainee->first_name ?? '') . ' ' . ($trainee->last_name ?? ''));

                 $existingCertificate = \App\Models\Certificate::where('course_id', $class->course_id)
                     ->where('training_center_id', $class->training_center_id)
                     ->where('trainee_name', $fullName)
                     ->whereIn('status', ['valid', 'expired'])
                     ->first();

                 if ($existingCertificate) {
                     // Skip updating this trainee's grade – score is locked once a certificate exists
                     continue;
                 }
             }

            $status = $score >= (float) $class->success_grade ? 'completed' : 'failed';

            $class->trainees()->updateExistingPivot($traineeId, [
                'exam_score' => $score,
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
        }
    }
}

