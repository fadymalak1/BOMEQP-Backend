<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryManagementService
{
    /**
     * Check if category is accessible by ACC
     *
     * @param Category $category
     * @param ACC $acc
     * @param int $userId
     * @return bool
     */
    public function isCategoryAccessible(Category $category, ACC $acc, int $userId): bool
    {
        $isAssigned = $acc->categories()->where('categories.id', $category->id)->exists();
        $isCreatedByAcc = $category->created_by === $userId;
        return $isAssigned || $isCreatedByAcc;
    }

    /**
     * Get accessible category IDs for ACC
     *
     * @param ACC $acc
     * @param int $userId
     * @return array
     */
    public function getAccessibleCategoryIds(ACC $acc, int $userId): array
    {
        $assignedCategoryIds = $acc->categories()->pluck('categories.id')->toArray();
        $createdCategoryIds = Category::where('created_by', $userId)->pluck('id')->toArray();
        return array_unique(array_merge($assignedCategoryIds, $createdCategoryIds));
    }

    /**
     * Create category
     *
     * @param Request $request
     * @param int $userId
     * @return array
     */
    public function createCategory(Request $request, int $userId): array
    {
        try {
            $category = Category::create([
                'name' => $request->name,
                'name_ar' => $request->name_ar,
                'description' => $request->description,
                'icon_url' => $request->icon_url,
                'status' => $request->status,
                'created_by' => $userId,
            ]);

            return [
                'success' => true,
                'category' => $category,
                'message' => 'Category created successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create category', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update category
     *
     * @param Request $request
     * @param Category $category
     * @param int $userId
     * @return array
     */
    public function updateCategory(Request $request, Category $category, int $userId): array
    {
        // Check if category was created by this ACC's user
        if ($category->created_by !== $userId) {
            return [
                'success' => false,
                'message' => 'You can only update categories you created',
                'code' => 403
            ];
        }

        try {
            $category->update($request->only(['name', 'name_ar', 'description', 'icon_url', 'status']));

            return [
                'success' => true,
                'category' => $category->fresh(),
                'message' => 'Category updated successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update category', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete category
     *
     * @param Category $category
     * @param int $userId
     * @return array
     */
    public function deleteCategory(Category $category, int $userId): array
    {
        // Check if category was created by this ACC's user
        if ($category->created_by !== $userId) {
            return [
                'success' => false,
                'message' => 'You can only delete categories you created',
                'code' => 403
            ];
        }

        // Check if category has subcategories
        if ($category->subCategories()->count() > 0) {
            return [
                'success' => false,
                'message' => 'Cannot delete category with subcategories. Please delete subcategories first.',
                'code' => 400
            ];
        }

        try {
            DB::beginTransaction();

            $category->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Category deleted successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete category', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create subcategory
     *
     * @param Request $request
     * @param ACC $acc
     * @param int $userId
     * @return array
     */
    public function createSubCategory(Request $request, ACC $acc, int $userId): array
    {
        $category = Category::findOrFail($request->category_id);

        // Check if category is accessible
        if (!$this->isCategoryAccessible($category, $acc, $userId)) {
            return [
                'success' => false,
                'message' => 'You can only create sub categories for categories assigned to you or created by you',
                'code' => 403
            ];
        }

        try {
            $subCategory = SubCategory::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'name_ar' => $request->name_ar,
                'description' => $request->description,
                'status' => $request->status,
                'created_by' => $userId,
            ]);

            return [
                'success' => true,
                'sub_category' => $subCategory,
                'message' => 'Subcategory created successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create subcategory', [
                'category_id' => $request->category_id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update subcategory
     *
     * @param Request $request
     * @param SubCategory $subCategory
     * @param ACC $acc
     * @param int $userId
     * @return array
     */
    public function updateSubCategory(Request $request, SubCategory $subCategory, ACC $acc, int $userId): array
    {
        // Check if subcategory was created by this ACC's user
        if ($subCategory->created_by !== $userId) {
            return [
                'success' => false,
                'message' => 'You can only update subcategories you created',
                'code' => 403
            ];
        }

        // Verify parent category is still accessible
        $category = $subCategory->category;
        if (!$this->isCategoryAccessible($category, $acc, $userId)) {
            return [
                'success' => false,
                'message' => 'Parent category is no longer accessible',
                'code' => 403
            ];
        }

        try {
            $subCategory->update($request->only(['name', 'name_ar', 'description', 'status']));

            return [
                'success' => true,
                'sub_category' => $subCategory->fresh(),
                'message' => 'Subcategory updated successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update subcategory', [
                'sub_category_id' => $subCategory->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete subcategory
     *
     * @param SubCategory $subCategory
     * @param int $userId
     * @return array
     */
    public function deleteSubCategory(SubCategory $subCategory, int $userId): array
    {
        // Check if subcategory was created by this ACC's user
        if ($subCategory->created_by !== $userId) {
            return [
                'success' => false,
                'message' => 'You can only delete subcategories you created',
                'code' => 403
            ];
        }

        try {
            DB::beginTransaction();

            $subCategory->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Subcategory deleted successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete subcategory', [
                'sub_category_id' => $subCategory->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

