<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClassController extends Controller
{
    #[OA\Get(
        path: "/admin/classes",
        summary: "List all training classes",
        description: "Get all training classes created by training centers in the system. Admin can only view classes, not create, update, or delete them.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "training_center_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training classes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "classes", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = \App\Models\TrainingClass::with(['course', 'trainingCenter', 'instructor', 'trainees', 'createdBy']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('training_center_id')) {
            $query->where('training_center_id', $request->training_center_id);
        }

        $classes = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['classes' => $classes]);
    }


    #[OA\Get(
        path: "/admin/classes/{id}",
        summary: "Get training class details",
        description: "Get detailed information about a specific training class created by a training center, including enrolled trainees.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Training class retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "class", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training class not found")
        ]
    )]
    public function show($id)
    {
        $class = \App\Models\TrainingClass::with(['course', 'trainingCenter', 'instructor', 'trainees', 'createdBy', 'completion'])->findOrFail($id);
        return response()->json(['class' => $class]);
    }

}

