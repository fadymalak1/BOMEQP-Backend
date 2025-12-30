<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
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

        // Get category IDs assigned to this ACC
        $assignedCategoryIds = $acc->categories()->pluck('categories.id')->toArray();
        $createdCategoryIds = Category::where('created_by', $user->id)->pluck('id')->toArray();
        $accessibleCategoryIds = array_unique(array_merge($assignedCategoryIds, $createdCategoryIds));

        // Query categories: assigned to ACC OR created by ACC's user
        $query = Category::with(['subCategories' => function($q) use ($accessibleCategoryIds, $user) {
            // Only load subcategories that belong to accessible categories OR created by ACC
            $q->where(function($subQ) use ($accessibleCategoryIds, $user) {
                $subQ->whereIn('category_id', $accessibleCategoryIds)
                     ->orWhere('created_by', $user->id);
            });
        }])
        ->where(function($q) use ($assignedCategoryIds, $user) {
            $q->whereIn('id', $assignedCategoryIds)
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

        // Check if category was created by this ACC's user
        if ($category->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only update categories you created'
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $category->update($request->only(['name', 'name_ar', 'description', 'icon_url', 'status']));

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ], 200);
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

        // Check if category was created by this ACC's user
        if ($category->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only delete categories you created'
            ], 403);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully'], 200);
    }

    /**
     * Create a sub category
     */
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

        // Check if category is accessible: assigned to ACC OR created by ACC's user
        $category = Category::findOrFail($request->category_id);
        $isAssigned = $acc->categories()->where('categories.id', $request->category_id)->exists();
        $isCreatedByAcc = $category->created_by === $user->id;

        if (!$isAssigned && !$isCreatedByAcc) {
            return response()->json([
                'message' => 'You can only create sub categories for categories assigned to you or created by you'
            ], 403);
        }

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

    /**
     * List sub categories (only for accessible categories)
     */
    public function indexSubCategories(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Get category IDs assigned to this ACC or created by ACC
        $assignedCategoryIds = $acc->categories()->pluck('categories.id')->toArray();
        $createdCategoryIds = Category::where('created_by', $user->id)->pluck('id')->toArray();
        $accessibleCategoryIds = array_unique(array_merge($assignedCategoryIds, $createdCategoryIds));

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

    /**
     * Get a specific sub category (only if accessible)
     */
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

    /**
     * Update a sub category (only if created by this ACC or belongs to assigned category)
     */
    public function updateSubCategory(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subCategory = SubCategory::with('category')->findOrFail($id);
        $category = $subCategory->category;

        // Check if subcategory is accessible: belongs to assigned category OR created by ACC
        $isCategoryAssigned = $acc->categories()->where('categories.id', $category->id)->exists();
        $isCategoryCreatedByAcc = $category->created_by === $user->id;
        $isSubCategoryCreatedByAcc = $subCategory->created_by === $user->id;

        // Can update if: created by ACC OR (belongs to assigned/created category AND created by ACC)
        if (!$isSubCategoryCreatedByAcc && !($isCategoryAssigned || $isCategoryCreatedByAcc)) {
            return response()->json([
                'message' => 'You can only update sub categories you created or sub categories in accessible categories'
            ], 403);
        }

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
            $isNewCategoryAssigned = $acc->categories()->where('categories.id', $request->category_id)->exists();
            $isNewCategoryCreatedByAcc = $newCategory->created_by === $user->id;

            if (!$isNewCategoryAssigned && !$isNewCategoryCreatedByAcc) {
                return response()->json([
                    'message' => 'You can only assign sub categories to categories assigned to you or created by you'
                ], 403);
            }
        }

        $subCategory->update($request->only(['category_id', 'name', 'name_ar', 'description', 'status']));

        return response()->json([
            'message' => 'Sub category updated successfully',
            'sub_category' => $subCategory->fresh()->load('category')
        ], 200);
    }

    /**
     * Delete a sub category (only if created by this ACC)
     */
    public function destroySubCategory(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subCategory = SubCategory::with('category')->findOrFail($id);

        // Check if sub category was created by this ACC's user
        if ($subCategory->created_by !== $user->id) {
            return response()->json([
                'message' => 'You can only delete sub categories you created'
            ], 403);
        }

        $subCategory->delete();

        return response()->json(['message' => 'Sub category deleted successfully'], 200);
    }
}

