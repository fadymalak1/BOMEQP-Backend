<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get categories assigned to ACC or created by ACC
     */
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

    /**
     * Get a specific category (only if assigned to ACC or created by ACC)
     */
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

    /**
     * Create a new category
     */
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

