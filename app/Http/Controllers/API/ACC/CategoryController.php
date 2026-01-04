<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Category;
use App\Models\SubCategory;
use App\Services\CategoryManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    protected CategoryManagementService $categoryService;

    public function __construct(CategoryManagementService $categoryService)
    {
        $this->categoryService = $categoryService;
    }
    #[OA\Get(
        path: "/acc/categories",
        summary: "List ACC categories",
        description: "Get categories assigned to ACC or created by ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "inactive"]), example: "active")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Categories retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "categories", type: "array", items: new OA\Items(type: "object"))
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

        $accessibleCategoryIds = $this->categoryService->getAccessibleCategoryIds($acc, $user->id);

        // Query categories: assigned to ACC OR created by ACC's user
        $query = Category::with(['subCategories' => function($q) use ($accessibleCategoryIds, $user) {
            $q->where(function($subQ) use ($accessibleCategoryIds, $user) {
                $subQ->whereIn('category_id', $accessibleCategoryIds)
                     ->orWhere('created_by', $user->id);
            });
        }])
        ->where(function($q) use ($accessibleCategoryIds, $user) {
            $q->whereIn('id', $accessibleCategoryIds)
              ->orWhere('created_by', $user->id);
        });

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $categories = $query->orderBy('name')->get();
        return response()->json(['categories' => $categories]);
    }

    #[OA\Get(
        path: "/acc/categories/{id}",
        summary: "Get category details",
        description: "Get a specific category. Only accessible if assigned to ACC or created by ACC.",
        tags: ["ACC"],
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
            new OA\Response(response: 404, description: "Category not found or not accessible")
        ]
    )]
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $category = Category::with(['subCategories' => function($q) use ($acc, $user) {
            // Only load subcategories that are accessible
            $q->where(function($subQ) use ($acc, $user) {
                $subQ->whereIn('category_id', $acc->categories()->pluck('categories.id'))
                     ->orWhere('created_by', $user->id);
            });
        }])->findOrFail($id);

        // Check if category is accessible: assigned to ACC OR created by ACC's user
        $isAssigned = $acc->categories()->where('categories.id', $id)->exists();
        $isCreatedByAcc = $category->created_by === $user->id;

        if (!$isAssigned && !$isCreatedByAcc) {
            return response()->json([
                'message' => 'Category not found or not accessible'
            ], 404);
        }

        return response()->json(['category' => $category]);
    }

    #[OA\Post(
        path: "/acc/categories",
        summary: "Create category",
        description: "Create a new category.",
        tags: ["ACC"],
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
                        new OA\Property(property: "message", type: "string", example: "Category created successfully"),
                        new OA\Property(property: "category", type: "object")
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
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            $result = $this->categoryService->createCategory($request, $request->user()->id);
            return response()->json(['category' => $result['category']], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create category', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update a category (only if created by this ACC)
     */
    #[OA\Put(
        path: "/acc/categories/{id}",
        summary: "Update category",
        description: "Update a category. Only accessible if assigned to ACC or created by ACC.",
        tags: ["ACC"],
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
            new OA\Response(response: 404, description: "Category not found or not accessible"),
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

        try {
            $result = $this->categoryService->updateCategory($request, $category, $request->user()->id);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json([
                'message' => $result['message'],
                'category' => $result['category']
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update category', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a category (only if created by this ACC)
     */
    #[OA\Delete(
        path: "/acc/categories/{id}",
        summary: "Delete category",
        description: "Delete a category. Only accessible if created by ACC.",
        tags: ["ACC"],
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
            new OA\Response(response: 403, description: "Cannot delete category - not created by ACC"),
            new OA\Response(response: 404, description: "Category not found")
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        try {
            $result = $this->categoryService->deleteCategory($category, $request->user()->id);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json(['message' => $result['message']], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete category', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to delete category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Post(
        path: "/acc/categories/sub-categories",
        summary: "Create subcategory",
        description: "Create a new subcategory for an accessible category.",
        tags: ["ACC"],
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
            new OA\Response(response: 403, description: "Category not accessible"),
            new OA\Response(response: 404, description: "ACC or category not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function storeSubCategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        try {
            $result = $this->categoryService->createSubCategory($request, $acc, $user->id);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json(['sub_category' => $result['sub_category']], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create subcategory', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create subcategory',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/acc/categories/sub-categories",
        summary: "List subcategories",
        description: "Get subcategories for accessible categories.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "inactive"]), example: "active")
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
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Category not accessible"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function indexSubCategories(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $accessibleCategoryIds = $this->categoryService->getAccessibleCategoryIds($acc, $user->id);

        $query = SubCategory::with('category')
            ->whereIn('category_id', $accessibleCategoryIds);

        if ($request->has('category_id')) {
            // Verify the category is accessible
            if (!in_array($request->category_id, $accessibleCategoryIds)) {
                return response()->json([
                    'message' => 'Category not accessible'
                ], 403);
            }
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $subCategories = $query->orderBy('name')->get();
        return response()->json(['sub_categories' => $subCategories]);
    }

    #[OA\Get(
        path: "/acc/categories/sub-categories/{id}",
        summary: "Get subcategory details",
        description: "Get a specific subcategory. Only accessible if the parent category is assigned to ACC or created by ACC.",
        tags: ["ACC"],
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
            new OA\Response(response: 404, description: "Subcategory not found or not accessible")
        ]
    )]
    public function showSubCategory(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subCategory = SubCategory::with('category')->findOrFail($id);

        // Check if subcategory's category is accessible
        $category = $subCategory->category;
        $isAssigned = $acc->categories()->where('categories.id', $category->id)->exists();
        $isCreatedByAcc = $category->created_by === $user->id;

        if (!$isAssigned && !$isCreatedByAcc) {
            return response()->json([
                'message' => 'Sub category not found or not accessible'
            ], 404);
        }

        return response()->json(['sub_category' => $subCategory]);
    }

    #[OA\Put(
        path: "/acc/categories/sub-categories/{id}",
        summary: "Update subcategory",
        description: "Update a subcategory. Only accessible if created by ACC or belongs to assigned category.",
        tags: ["ACC"],
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
                        new OA\Property(property: "message", type: "string", example: "Subcategory updated successfully"),
                        new OA\Property(property: "sub_category", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Cannot update subcategory"),
            new OA\Response(response: 404, description: "Subcategory not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function updateSubCategory(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subCategory = SubCategory::with('category')->findOrFail($id);

        $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        // If changing category_id, verify the new category is accessible
        if ($request->has('category_id') && $request->category_id != $subCategory->category_id) {
            $newCategory = Category::findOrFail($request->category_id);
            if (!$this->categoryService->isCategoryAccessible($newCategory, $acc, $user->id)) {
                return response()->json([
                    'message' => 'You can only assign sub categories to categories assigned to you or created by you'
                ], 403);
            }
            $subCategory->category_id = $request->category_id;
        }

        try {
            $result = $this->categoryService->updateSubCategory($request, $subCategory, $acc, $user->id);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json([
                'message' => $result['message'],
                'sub_category' => $result['sub_category']->load('category')
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update subcategory', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update subcategory',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a sub category (only if created by this ACC)
     */
    #[OA\Delete(
        path: "/acc/categories/sub-categories/{id}",
        summary: "Delete subcategory",
        description: "Delete a subcategory. Only accessible if created by ACC.",
        tags: ["ACC"],
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
                        new OA\Property(property: "message", type: "string", example: "Subcategory deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Cannot delete subcategory - not created by ACC"),
            new OA\Response(response: 404, description: "Subcategory not found")
        ]
    )]
    public function destroySubCategory(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subCategory = SubCategory::with('category')->findOrFail($id);

        try {
            $result = $this->categoryService->deleteSubCategory($subCategory, $user->id);
            
            if (!$result['success']) {
                return response()->json(['message' => $result['message']], $result['code']);
            }

            return response()->json(['message' => $result['message']], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete subcategory', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to delete subcategory',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

