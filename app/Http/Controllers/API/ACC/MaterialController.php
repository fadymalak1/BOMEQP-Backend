<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCMaterial;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MaterialController extends Controller
{
    #[OA\Get(
        path: "/acc/materials",
        summary: "List ACC materials",
        description: "Get all materials created by the authenticated ACC with optional filtering.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "material_type", in: "query", schema: new OA\Schema(type: "string", enum: ["pdf", "video", "presentation", "package"]), example: "pdf")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Materials retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "materials", type: "array", items: new OA\Items(type: "object"))
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

        $query = ACCMaterial::where('acc_id', $acc->id);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('material_type')) {
            $query->where('material_type', $request->material_type);
        }

        $materials = $query->get();
        return response()->json(['materials' => $materials]);
    }

    #[OA\Post(
        path: "/acc/materials",
        summary: "Create material",
        description: "Create a new material (PDF, video, presentation, or package) for the marketplace.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["material_type", "name", "price", "file_url", "status"],
                properties: [
                    new OA\Property(property: "course_id", type: "integer", nullable: true, example: 1),
                    new OA\Property(property: "material_type", type: "string", enum: ["pdf", "video", "presentation", "package"], example: "pdf"),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Guide"),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "price", type: "number", format: "float", example: 50.00, minimum: 0),
                    new OA\Property(property: "file_url", type: "string", example: "https://example.com/file.pdf"),
                    new OA\Property(property: "preview_url", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Material created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "material", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'material_type' => 'required|in:pdf,video,presentation,package',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'file_url' => 'required|string',
            'preview_url' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $material = ACCMaterial::create([
            'acc_id' => $acc->id,
            'course_id' => $request->course_id,
            'material_type' => $request->material_type,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'file_url' => $request->file_url,
            'preview_url' => $request->preview_url,
            'status' => $request->status,
        ]);

        return response()->json(['material' => $material], 201);
    }

    #[OA\Get(
        path: "/acc/materials/{id}",
        summary: "Get material details",
        description: "Get detailed information about a specific material.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Material retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "material", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Material not found")
        ]
    )]
    public function show($id)
    {
        $material = ACCMaterial::with('course')->findOrFail($id);
        return response()->json(['material' => $material]);
    }

    #[OA\Put(
        path: "/acc/materials/{id}",
        summary: "Update material",
        description: "Update material information. Only materials created by the ACC can be updated.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "course_id", type: "integer", nullable: true),
                    new OA\Property(property: "material_type", type: "string", enum: ["pdf", "video", "presentation", "package"], nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "price", type: "number", format: "float", nullable: true, minimum: 0),
                    new OA\Property(property: "file_url", type: "string", nullable: true),
                    new OA\Property(property: "preview_url", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Material updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Material updated successfully"),
                        new OA\Property(property: "material", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Material not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $material = ACCMaterial::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'material_type' => 'sometimes|in:pdf,video,presentation,package',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'file_url' => 'sometimes|string',
            'preview_url' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $material->update($request->only([
            'course_id', 'material_type', 'name', 'description',
            'price', 'file_url', 'preview_url', 'status'
        ]));

        return response()->json(['message' => 'Material updated successfully', 'material' => $material]);
    }

    #[OA\Delete(
        path: "/acc/materials/{id}",
        summary: "Delete material",
        description: "Delete a material. Only materials created by the ACC can be deleted.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Material deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Material deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Material not found")
        ]
    )]
    public function destroy($id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $material = ACCMaterial::where('acc_id', $acc->id)->findOrFail($id);
        $material->delete();

        return response()->json(['message' => 'Material deleted successfully']);
    }
}

