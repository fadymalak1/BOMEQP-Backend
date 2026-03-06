<?php

namespace App\Http\Controllers\API\Admin;

use App\Exports\SubCategoryTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\SubCategoryImport;
use App\Models\ACC;
use App\Models\SubCategory;
use App\Services\CategoryManagementService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;

class SubCategoryController extends Controller
{
    #[OA\Get(
        path: "/admin/sub-categories",
        summary: "List all subcategories",
        description: "Get all subcategories with optional filtering by category.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Subcategories retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "sub_categories", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = SubCategory::with('category');
        
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $subCategories = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['sub_categories' => $subCategories]);
    }

    #[OA\Post(
        path: "/admin/sub-categories",
        summary: "Create subcategory",
        description: "Create a new subcategory.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id", "name", "status"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Basic Fire Safety"),
                    new OA\Property(property: "name_ar", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Subcategory created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "sub_category", type: "object")
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
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $subCategory = SubCategory::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'description' => $request->description,
            'status' => $request->status,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['sub_category' => $subCategory], 201);
    }

    #[OA\Get(
        path: "/admin/sub-categories/{id}",
        summary: "Get subcategory details",
        description: "Get detailed information about a specific subcategory.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Subcategory retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "sub_category", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Subcategory not found")
        ]
    )]
    public function show($id)
    {
        $subCategory = SubCategory::with('category')->findOrFail($id);
        return response()->json(['sub_category' => $subCategory]);
    }

    #[OA\Put(
        path: "/admin/sub-categories/{id}",
        summary: "Update subcategory",
        description: "Update subcategory information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "category_id", type: "integer", nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "name_ar", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Subcategory updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Sub category updated successfully"),
                        new OA\Property(property: "sub_category", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Subcategory not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $subCategory = SubCategory::findOrFail($id);

        $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $subCategory->update($request->only(['category_id', 'name', 'name_ar', 'description', 'status']));

        return response()->json(['message' => 'Sub category updated successfully', 'sub_category' => $subCategory]);
    }

    #[OA\Delete(
        path: "/admin/sub-categories/{id}",
        summary: "Delete subcategory",
        description: "Delete a subcategory. This action cannot be undone.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Subcategory deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Sub category deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Subcategory not found")
        ]
    )]
    public function destroy($id)
    {
        $subCategory = SubCategory::findOrFail($id);
        $subCategory->delete();

        return response()->json(['message' => 'Sub category deleted successfully']);
    }

    #[OA\Get(
        path: "/admin/sub-categories/template/download",
        summary: "Download subcategories Excel/CSV template",
        description: "Download an Excel or CSV template for bulk subcategory import. Excel format includes a dropdown in the category column to select from existing categories. Use format=xlsx for Excel (recommended for dropdown) or format=csv for CSV. Available to group_admin and acc_admin.",
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

        $accessibleCategoryIds = null;
        if ($request->user()->role === 'acc_admin') {
            $acc = ACC::where('email', $request->user()->email)->first();
            $accessibleCategoryIds = $acc
                ? app(CategoryManagementService::class)->getAccessibleCategoryIds($acc, $request->user()->id)
                : [];
        }

        $fileName = 'subcategories_template.' . $format;

        return Excel::download(
            new SubCategoryTemplateExport($format, $accessibleCategoryIds),
            $fileName,
            $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }

    #[OA\Post(
        path: "/admin/sub-categories/import",
        summary: "Import subcategories from Excel/CSV file",
        description: "Upload an Excel or CSV file to bulk create/update subcategories. Template columns: category (required, select from dropdown in Excel or enter exact category name in CSV), name (required), description.",
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
        $import = new SubCategoryImport($request->user()->id);

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
            'message' => 'Subcategories imported successfully',
            'created_count' => $import->getCreatedCount(),
            'updated_count' => $import->getUpdatedCount(),
            'errors' => $import->getErrors(),
        ]);
    }
}

