<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClassController extends Controller
{
    #[OA\Get(
        path: "/admin/classes",
        summary: "List all classes",
        description: "Get all classes in the system with optional filtering by course.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
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
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = ClassModel::with('course');

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $classes = $query->get();
        return response()->json(['classes' => $classes]);
    }

    #[OA\Post(
        path: "/admin/classes",
        summary: "Create class",
        description: "Create a new class model.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["course_id", "name", "status"],
                properties: [
                    new OA\Property(property: "course_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Class A"),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
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
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255|unique:classes,name',
            'status' => 'required|in:active,inactive',
        ]);

        $class = ClassModel::create([
            'course_id' => $request->course_id,
            'name' => $request->name,
            'status' => $request->status,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['class' => $class], 201);
    }

    #[OA\Get(
        path: "/admin/classes/{id}",
        summary: "Get class details",
        description: "Get detailed information about a specific class.",
        tags: ["Admin"],
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
        $class = ClassModel::with('course')->findOrFail($id);
        return response()->json(['class' => $class]);
    }

    #[OA\Put(
        path: "/admin/classes/{id}",
        summary: "Update class",
        description: "Update class information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "course_id", type: "integer", nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], nullable: true)
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
        $class = ClassModel::findOrFail($id);

        $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'name' => 'sometimes|string|max:255|unique:classes,name,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ]);

        $class->update($request->only(['course_id', 'name', 'status']));

        return response()->json(['message' => 'Class updated successfully', 'class' => $class]);
    }

    #[OA\Delete(
        path: "/admin/classes/{id}",
        summary: "Delete class",
        description: "Delete a class. This action cannot be undone.",
        tags: ["Admin"],
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
        $class = ClassModel::findOrFail($id);
        $class->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }
}

