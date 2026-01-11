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
        description: "Get all training classes for the authenticated training center with their trainees list.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Classes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "classes",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "trainees", type: "array", items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "first_name", type: "string"),
                                            new OA\Property(property: "last_name", type: "string"),
                                            new OA\Property(property: "email", type: "string"),
                                            new OA\Property(property: "phone", type: "string", nullable: true),
                                            new OA\Property(property: "id_number", type: "string", nullable: true),
                                            new OA\Property(property: "status", type: "string", nullable: true),
                                            new OA\Property(property: "enrolled_at", type: "string", format: "date-time", nullable: true),
                                            new OA\Property(property: "completed_at", type: "string", format: "date-time", nullable: true),
                                        ]
                                    )),
                                    new OA\Property(property: "trainees_count", type: "integer", description: "Total number of trainees in the class")
                                ]
                            )
                        )
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

        $classes = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with(['course', 'instructor', 'classModel', 'trainees'])
            ->get()
            ->map(function ($class) {
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
                // Keep enrolled_count for backward compatibility
                $classData['trainees_count'] = $class->trainees->count();
                return $classData;
            });

        return response()->json(['classes' => $classes]);
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
                required: ["course_id", "class_id", "instructor_id", "start_date", "end_date", "location"],
                properties: [
                    new OA\Property(property: "course_id", type: "integer", example: 1),
                    new OA\Property(property: "class_id", type: "integer", example: 1),
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
            new OA\Response(response: 403, description: "Course not available - ACC authorization required"),
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'class_id' => 'required|exists:classes,id',
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
            'class_id' => $request->class_id,
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
            ->with(['course', 'instructor', 'trainingCenter', 'classModel', 'completion', 'trainees'])
            ->findOrFail($id);
        return response()->json(['class' => $class]);
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
            'class_id' => 'sometimes|exists:classes,id',
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

        $updateData = $request->only([
            'course_id', 'class_id', 'instructor_id', 'start_date', 'end_date',
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

    #[OA\Post(
        path: "/training-center/classes/{id}/complete",
        summary: "Mark class as completed",
        description: "Mark a training class as completed and create a completion record.",
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
                        new OA\Property(property: "message", type: "string", example: "Class marked as completed")
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

        if ($class->status !== 'completed') {
            $class->update(['status' => 'completed']);

            ClassCompletion::create([
                'training_class_id' => $class->id,
                'completed_date' => now(),
                'completion_rate_percentage' => 100,
                'certificates_generated_count' => 0,
                'marked_by' => $user->id,
            ]);

            // Send notification to instructor
            $class->load(['instructor', 'course']);
            $instructor = $class->instructor;
            if ($instructor) {
                $instructorUser = \App\Models\User::where('email', $instructor->email)->first();
                if ($instructorUser) {
                    $notificationService = new \App\Services\NotificationService();
                    $classModel = $class->classModel;
                    $className = $classModel ? $classModel->name : "Class #{$class->id}";
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

        return response()->json(['message' => 'Class marked as completed']);
    }
}

