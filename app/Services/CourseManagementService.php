<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\Course;
use App\Models\CertificatePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CourseManagementService
{
    /**
     * Create a new course
     *
     * @param Request $request
     * @param ACC $acc
     * @return array
     */
    public function createCourse(Request $request, ACC $acc): array
    {
        try {
            DB::beginTransaction();

            // Create course
            $course = Course::create([
                'sub_category_id' => $request->sub_category_id,
                'acc_id' => $acc->id,
                'name' => $request->name,
                'name_ar' => $request->name_ar,
                'code' => $request->code,
                'description' => $request->description,
                'duration_hours' => $request->duration_hours,
                'max_capacity' => $request->max_capacity,
                'assessor_required' => $request->boolean('assessor_required', false),
                'level' => $request->level,
                'status' => $request->status,
            ]);

            // Create pricing if provided
            if ($request->has('pricing') && $request->pricing) {
                $this->createOrUpdatePricing($request->pricing, $course, $acc);
            }

            DB::commit();

            // Reload course with relationships
            $course->load(['subCategory.category']);
            $course = $this->addPricingToCourse($course, $acc);

            return [
                'success' => true,
                'course' => $course,
                'message' => $request->has('pricing') && $request->pricing 
                    ? 'Course created successfully with pricing' 
                    : 'Course created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create course', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update a course
     *
     * @param Request $request
     * @param Course $course
     * @param ACC $acc
     * @return array
     */
    public function updateCourse(Request $request, Course $course, ACC $acc): array
    {
        try {
            DB::beginTransaction();

            // Update course fields
            $updateData = $request->only([
                'sub_category_id', 'name', 'name_ar', 'code', 'description',
                'duration_hours', 'max_capacity', 'level', 'status'
            ]);

            // Handle boolean conversion for assessor_required
            if ($request->has('assessor_required')) {
                $updateData['assessor_required'] = $request->boolean('assessor_required');
            }

            $course->update($updateData);

            // Handle pricing update if provided
            if ($request->has('pricing') && $request->pricing) {
                $this->createOrUpdatePricing($request->pricing, $course, $acc);
            }

            DB::commit();

            // Reload course with relationships
            $course->load(['subCategory.category']);
            $course = $this->addPricingToCourse($course, $acc);

            return [
                'success' => true,
                'course' => $course,
                'message' => $request->has('pricing') 
                    ? 'Course updated successfully and pricing updated' 
                    : 'Course updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update course', [
                'course_id' => $course->id,
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create or update pricing for a course
     *
     * @param array $pricingData
     * @param Course $course
     * @param ACC $acc
     * @return CertificatePricing
     */
    private function createOrUpdatePricing(array $pricingData, Course $course, ACC $acc): CertificatePricing
    {
        $existingPricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->latest('created_at')
            ->first();

        if ($existingPricing) {
            $existingPricing->update([
                'base_price' => $pricingData['base_price'],
                'currency' => $pricingData['currency'],
            ]);
            return $existingPricing;
        } else {
            return CertificatePricing::create([
                'acc_id' => $acc->id,
                'course_id' => $course->id,
                'base_price' => $pricingData['base_price'],
                'currency' => $pricingData['currency'],
                'group_commission_percentage' => 0,
                'training_center_commission_percentage' => 0,
                'instructor_commission_percentage' => 0,
                'effective_from' => now()->format('Y-m-d'),
                'effective_to' => null,
            ]);
        }
    }

    /**
     * Set pricing with commission percentages
     *
     * @param Request $request
     * @param Course $course
     * @param ACC $acc
     * @return array
     */
    public function setPricing(Request $request, Course $course, ACC $acc): array
    {
        try {
            DB::beginTransaction();

            // Get existing pricing or create new one (no effective dates - always active)
            $existingPricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->latest('created_at')
                ->first();

            if ($existingPricing) {
                // Update existing pricing
                $existingPricing->update([
                    'base_price' => $request->base_price,
                    'currency' => $request->currency,
                ]);
                $pricing = $existingPricing;
            } else {
                // Create new pricing
                $pricing = CertificatePricing::create([
                    'acc_id' => $acc->id,
                    'course_id' => $course->id,
                    'base_price' => $request->base_price,
                    'currency' => $request->currency,
                    'group_commission_percentage' => 0,
                    'training_center_commission_percentage' => 0,
                    'instructor_commission_percentage' => 0,
                    'effective_from' => now()->format('Y-m-d'),
                    'effective_to' => null,
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'pricing' => $pricing->fresh(),
                'message' => 'Pricing set successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to set pricing', [
                'course_id' => $course->id,
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update pricing
     *
     * @param Request $request
     * @param Course $course
     * @param ACC $acc
     * @return array
     */
    public function updatePricing(Request $request, Course $course, ACC $acc): array
    {
        try {
            DB::beginTransaction();

            // Get current pricing (no effective dates - always active)
            $pricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->latest('created_at')
                ->first();

            if (!$pricing) {
                return [
                    'success' => false,
                    'message' => 'No pricing found for this course',
                    'code' => 404
                ];
            }

            // Update pricing fields (only base_price and currency)
            $updateData = [];
            if ($request->has('base_price')) {
                $updateData['base_price'] = $request->base_price;
            }
            if ($request->has('currency')) {
                $updateData['currency'] = $request->currency;
            }

            $pricing->update($updateData);

            DB::commit();

            return [
                'success' => true,
                'pricing' => $pricing->fresh(),
                'message' => 'Pricing updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update pricing', [
                'course_id' => $course->id,
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Add pricing information to course
     *
     * @param Course $course
     * @param ACC $acc
     * @return Course
     */
    public function addPricingToCourse(Course $course, ACC $acc): Course
    {
        $currentPricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->latest('created_at')
            ->first();

        $course->current_price = $currentPricing ? [
            'base_price' => $currentPricing->base_price,
            'currency' => $currentPricing->currency ?? 'USD',
        ] : null;

        return $course;
    }

    /**
     * Get courses with pricing for ACC
     *
     * @param Request $request
     * @param ACC $acc
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCoursesWithPricing(Request $request, ACC $acc)
    {
        $query = Course::where('acc_id', $acc->id)
            ->with(['subCategory.category']);

        // Optional filters
        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $courses = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add pricing to each course
        $courses->getCollection()->transform(function ($course) use ($acc) {
            return $this->addPricingToCourse($course, $acc);
        });

        return $courses;
    }
}

