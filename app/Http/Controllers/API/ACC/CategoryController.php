<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get all categories (including ACC's own categories)
     */
    public function index(Request $request)
    {
        $query = Category::with('subCategories');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $categories = $query->orderBy('name')->get();
        return response()->json(['categories' => $categories]);
    }

    /**
     * Get a specific category
     */
    public function show($id)
    {
        $category = Category::with('subCategories')->findOrFail($id);
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

        // Check if category was created by this ACC's user
        $category = Category::findOrFail($request->category_id);
        if ($category->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only create sub categories for categories you created'
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
     * Update a sub category (only if created by this ACC)
     */
    public function updateSubCategory(Request $request, $id)
    {
        $subCategory = SubCategory::findOrFail($id);

        // Check if sub category was created by this ACC's user
        if ($subCategory->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only update sub categories you created'
            ], 403);
        }

        $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        // If changing category_id, verify the new category belongs to this ACC
        if ($request->has('category_id') && $request->category_id != $subCategory->category_id) {
            $category = Category::findOrFail($request->category_id);
            if ($category->created_by !== $request->user()->id) {
                return response()->json([
                    'message' => 'You can only assign sub categories to categories you created'
                ], 403);
            }
        }

        $subCategory->update($request->only(['category_id', 'name', 'name_ar', 'description', 'status']));

        return response()->json([
            'message' => 'Sub category updated successfully',
            'sub_category' => $subCategory
        ], 200);
    }

    /**
     * Delete a sub category (only if created by this ACC)
     */
    public function destroySubCategory(Request $request, $id)
    {
        $subCategory = SubCategory::findOrFail($id);

        // Check if sub category was created by this ACC's user
        if ($subCategory->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only delete sub categories you created'
            ], 403);
        }

        $subCategory->delete();

        return response()->json(['message' => 'Sub category deleted successfully'], 200);
    }
}

