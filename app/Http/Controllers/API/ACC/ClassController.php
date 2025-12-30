<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingClass;
use App\Models\TrainingCenterAccAuthorization;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClassController extends Controller
{
    #[OA\Get(
        path: "/acc/classes",
        summary: "List ACC classes",
        description: "Get all classes from training centers that have authorization from this ACC. Only shows classes for courses that belong to the ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["scheduled", "in_progress", "completed", "cancelled"]), example: "in_progress"),
            new OA\Parameter(name: "training_center_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "date_from", in: "query", schema: new OA\Schema(type: "string", format: "date"), example: "2024-01-01"),
            new OA\Parameter(name: "date_to", in: "query", schema: new OA\Schema(type: "string", format: "date"), example: "2024-12-31"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Classes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 50)
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

        // Get training center IDs that have approved authorization from this ACC
        $authorizedTrainingCenterIds = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->pluck('training_center_id')
            ->toArray();

        // Build query: classes from authorized training centers for ACC's courses
        $query = TrainingClass::whereHas('course', function ($q) use ($acc) {
                $q->where('acc_id', $acc->id);
            })
            ->whereIn('training_center_id', $authorizedTrainingCenterIds)
            ->with(['course', 'trainingCenter', 'instructor']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('training_center_id')) {
            $query->where('training_center_id', $request->training_center_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('start_date', '<=', $request->date_to);
        }

        // Order by start date (upcoming first)
        $query->orderBy('start_date', 'desc');

        // Paginate
        $perPage = $request->get('per_page', 15);
        $classes = $query->paginate($perPage);

        return response()->json($classes);
    }

    #[OA\Get(
        path: "/acc/classes/{id}",
        summary: "Get class details",
        description: "Get detailed information about a specific class. Only shows classes from authorized training centers.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Class retrieved successfully",
                content: new OA\JsonContent(type: "object")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Class not found or not authorized")
        ]
    )]
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Get training center IDs that have approved authorization from this ACC
        $authorizedTrainingCenterIds = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->pluck('training_center_id')
            ->toArray();

        $class = TrainingClass::whereHas('course', function ($q) use ($acc) {
                $q->where('acc_id', $acc->id);
            })
            ->whereIn('training_center_id', $authorizedTrainingCenterIds)
            ->with(['course', 'trainingCenter', 'instructor'])
            ->find($id);

        if (!$class) {
            return response()->json(['message' => 'Class not found or not authorized'], 404);
        }

        return response()->json($class);
    }
}

