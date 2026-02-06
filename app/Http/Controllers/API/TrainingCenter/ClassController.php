<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\TrainingClass;
use App\Models\ClassCompletion;
use App\Models\User;
use Illuminate\Http\Request;
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
                if ($class->start_date && $class->end_date && $class->status !== 'cancelled') {
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

                $classData = $class->toArray();
                // Format trainees data
                $classData['trainees'] = $class->trainees->map(function ($trainee) {
                    return [
                        'id' => $trainee->id,
                        'first_name' => $trainee->first_name,
                        'last_name' => $trainee->last_name,
                        'email' => $trainee->email,
                        'phone' => $trainee->phone,
                        'id_number' => $trainee->id_number,
                        'status' => $trainee->pivot->status ?? null,
                        'enrolled_at' => $trainee->pivot->enrolled_at ?? null,
                        'completed_at' => $trainee->pivot->completed_at ?? null,
                    ];
                });
                // Convert exam_score to int if it exists
                if (isset($classData['exam_score']) && $classData['exam_score'] !== null) {
                    $classData['exam_score'] = (int) round($classData['exam_score']);
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
        description: "Get detailed information about a specific training class.",
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
        if ($class->start_date && $class->end_date && $class->status !== 'cancelled') {
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

        return response()->json(['class' => $class->fresh()]);
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
            'exam_date', 'exam_score', 'location', 'location_details', 'status'
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
}

