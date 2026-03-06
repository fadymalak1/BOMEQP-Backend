<?php

namespace App\Http\Controllers\API\Admin;

use App\Exports\CategoryTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\CategoryImport;
use App\Models\Category;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: "/admin/categories",
        summary: "List all categories",
        description: "Get all categories with their subcategories, pagination, and search.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by category name (English or Arabic), description, or status"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["active", "inactive"]), example: "active"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15), example: 15),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Categories retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "categories", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 50),
                        new OA\Property(property: "last_page", type: "integer", example: 4)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = Category::with('subCategories');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('name_ar', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('status', 'like', "%{$searchTerm}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $categories = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'categories' => $categories->items(),
            'current_page' => $categories->currentPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(),
            'last_page' => $categories->lastPage(),
        ]);
    }

    #[OA\Post(
        path: "/admin/categories",
        summary: "Create category",
        description: "Create a new category.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "status"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Fire Safety"),
                    new OA\Property(property: "name_ar", type: "string", nullable: true, example: "السلامة من الحرائق"),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "icon_url", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Category created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "category", type: "object")
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
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'description' => $request->description,
            'icon_url' => $request->icon_url,
            'status' => $request->status,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['category' => $category], 201);
    }

    #[OA\Get(
        path: "/admin/categories/{id}",
        summary: "Get category details",
        description: "Get detailed information about a specific category.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Category retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "category", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Category not found")
        ]
    )]
    public function show($id)
    {
        $category = Category::with('subCategories')->findOrFail($id);
        return response()->json(['category' => $category]);
    }

    #[OA\Put(
        path: "/admin/categories/{id}",
        summary: "Update category",
        description: "Update category information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "name_ar", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "icon_url", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Category updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Category updated successfully"),
                        new OA\Property(property: "category", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Category not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $category->update($request->only(['name', 'name_ar', 'description', 'icon_url', 'status']));

        return response()->json(['message' => 'Category updated successfully', 'category' => $category]);
    }

    #[OA\Delete(
        path: "/admin/categories/{id}",
        summary: "Delete category",
        description: "Delete a category. This action cannot be undone.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Category deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Category deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Category not found")
        ]
    )]
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    #[OA\Get(
        path: "/admin/categories/template/download",
        summary: "Download categories Excel/CSV template",
        description: "Download an Excel or CSV template for bulk category import. Use format=xlsx for Excel (recommended) or format=csv for CSV. Available to group_admin and acc_admin.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "format", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["xlsx", "csv"], default: "xlsx"), example: "xlsx")
        ],
        responses: [
            new OA\Response(response: 200, description: "Template file downloaded"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function downloadTemplate(Request $request)
    {
        $format = strtolower($request->get('format', 'xlsx'));
        if (!in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        $fileName = 'categories_template.' . $format;

        return Excel::download(new CategoryTemplateExport(), $fileName, $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    #[OA\Post(
        path: "/admin/categories/import",
        summary: "Import categories from Excel/CSV file",
        description: "Upload an Excel or CSV file to bulk create/update categories. Columns: name (required), name_ar, description, icon_url, status (active|inactive).",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["file"],
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "Excel (.xlsx) or CSV file")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Import completed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "created_count", type: "integer"),
                        new OA\Property(property: "updated_count", type: "integer"),
                        new OA\Property(property: "errors", type: "array", items: new OA\Items(type: "string"))
                    ]
                )
            ),
            new OA\Response(response: 400, description: "No file uploaded"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Invalid file format")
        ]
    )]
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        $file = $request->file('file');
        $import = new CategoryImport($request->user()->id);

        try {
            Excel::import($import, $file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }
            return response()->json([
                'message' => 'Validation failed',
                'errors' => array_merge($errors, $import->getErrors()),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }

        return response()->json([
            'message' => 'Categories imported successfully',
            'created_count' => $import->getCreatedCount(),
            'updated_count' => $import->getUpdatedCount(),
            'errors' => $import->getErrors(),
        ]);
    }
}

