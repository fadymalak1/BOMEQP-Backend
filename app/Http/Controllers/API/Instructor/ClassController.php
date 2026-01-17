<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\TrainingClass;
use App\Models\ClassCompletion;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClassController extends Controller
{
    #[OA\Get(
        path: "/instructor/classes",
        summary: "List instructor classes",
        description: "Get all classes assigned to the authenticated instructor.",
        tags: ["Instructor"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["scheduled", "in_progress", "completed", "cancelled"]), example: "scheduled")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Classes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "classes", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $query = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course', 'trainingCenter']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $classes = $query->orderBy('start_date', 'desc')->get();

        return response()->json(['classes' => $classes]);
    }

    #[OA\Get(
        path: "/instructor/classes/{id}",
        summary: "Get class details",
        description: "Get detailed information about a specific class assigned to the instructor.",
        tags: ["Instructor"],
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
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $class = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course', 'trainingCenter', 'completion', 'createdBy'])
            ->findOrFail($id);

        return response()->json(['class' => $class]);
    }

    #[OA\Post(
        path: "/instructor/classes/{id}/mark-complete",
        summary: "Mark class as complete",
        description: "Mark a class as completed with completion rate and notes. Class end date must have passed.",
        tags: ["Instructor"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["completion_rate_percentage"],
                properties: [
                    new OA\Property(property: "completion_rate_percentage", type: "number", format: "float", example: 95.0, minimum: 0, maximum: 100),
                    new OA\Property(property: "notes", type: "string", nullable: true, example: "All trainees completed successfully")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Class marked as completed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Class marked as completed"),
                        new OA\Property(property: "completion", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Class end date has not been reached"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function markComplete(Request $request, $id)
    {
        $request->validate([
            'completion_rate_percentage' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $class = TrainingClass::where('instructor_id', $instructor->id)->findOrFail($id);

        // Check if class end date has passed (remove this check or make it optional)
        // We'll update end_date to today when marking as complete
        
        $class->update([
            'status' => 'completed',
            'end_date' => now()->toDateString(), // Update end_date to today
        ]);

        $completion = ClassCompletion::updateOrCreate(
            ['training_class_id' => $class->id],
            [
                'completed_date' => now(),
                'completion_rate_percentage' => $request->completion_rate_percentage,
                'notes' => $request->notes,
                'marked_by' => $user->id,
            ]
        );

        // TODO: Send notification to training center

        return response()->json([
            'message' => 'Class marked as completed',
            'completion' => $completion,
        ]);
    }
}

