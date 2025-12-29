<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Course;
use App\Models\CertificatePricing;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

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
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $courses = $query->orderBy('created_at', 'desc')->get();

        // Add current pricing to each course (pricing is always effective)
        $coursesWithDetails = $courses->map(function ($course) use ($acc) {
            // Get the current pricing for this course
            $currentPricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->latest('created_at')
                ->first();

            // Add pricing information to course
            $course->current_price = $currentPricing ? [
                'base_price' => $currentPricing->base_price,
                'currency' => $currentPricing->currency ?? 'USD',
            ] : null;

            return $course;
        });

        return response()->json(['courses' => $coursesWithDetails->values()]);
    }

    /**
     * Create a new course
     * 
     * Create a new course with optional pricing. If pricing is provided, it will be set for the course.
     * 
     * @group ACC Courses
     * @authenticated
     * 
     * @bodyParam sub_category_id integer required Sub category ID. Example: 1
     * @bodyParam name string required Course name. Example: Advanced Fire Safety
     * @bodyParam name_ar string optional Course name in Arabic. Example: السلامة من الحرائق المتقدمة
     * @bodyParam code string required Unique course code. Example: AFS-001
     * @bodyParam description string optional Course description. Example: Advanced fire safety training course
     * @bodyParam duration_hours integer required Course duration in hours. Example: 40
     * @bodyParam max_capacity integer required Maximum capacity for classes of this course. Example: 20
     * @bodyParam assessor_required boolean optional Whether an assessor is required for this course. Example: true
     * @bodyParam level string required Course level. Example: advanced
     * @bodyParam status string required Course status. Example: active
     * @bodyParam pricing array optional Pricing information.
     * @bodyParam pricing.base_price number required Base price. Example: 500.00
     * @bodyParam pricing.currency string required Currency code (3 characters). Example: USD
     * 
     * @response 201 {
     *   "message": "Course created successfully with pricing",
     *   "course": {
     *     "id": 1,
     *     "name": "Advanced Fire Safety",
     *     "code": "AFS-001",
     *     "current_price": {
     *       "base_price": 500.00,
     *       "currency": "USD"
     *     }
     *   }
     * }
     */
    public function store(Request $request)
    {
        // Validate course fields
        $request->validate([
            'sub_category_id' => 'required|exists:sub_categories,id',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'code' => 'required|string|max:255|unique:courses,code',
            'description' => 'nullable|string',
            'duration_hours' => 'required|integer|min:1',
            'max_capacity' => 'required|integer|min:1',
            'assessor_required' => 'nullable|boolean',
            'level' => 'required|in:beginner,intermediate,advanced',
            'status' => 'required|in:active,inactive,archived',
            // Pricing fields (optional)
            'pricing' => 'nullable|array',
            'pricing.base_price' => 'required_with:pricing|numeric|min:0',
            'pricing.currency' => 'required_with:pricing|string|size:3',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

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

        // Create pricing if provided (commissions are set by Group Admin, not ACC)
        // Pricing is always effective - no date restrictions
        if ($request->has('pricing') && $request->pricing) {
            $pricingData = $request->pricing;
            // If pricing exists, update it; otherwise create new
            $existingPricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->first();
            
            if ($existingPricing) {
                $existingPricing->update([
                    'base_price' => $pricingData['base_price'],
                    'currency' => $pricingData['currency'],
                ]);
            } else {
                CertificatePricing::create([
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

        // Reload course with relationships
        $course->load(['subCategory.category']);

        // Get the current pricing for this course (always effective)
        $currentPricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->latest('created_at')
            ->first();

        // Add pricing information to course
        $course->current_price = $currentPricing ? [
            'base_price' => $currentPricing->base_price,
            'currency' => $currentPricing->currency ?? 'USD',
        ] : null;

        $message = 'Course created successfully';
        if ($request->has('pricing') && $request->pricing) {
            $message .= ' with pricing';
        }

        return response()->json([
            'message' => $message,
            'course' => $course
        ], 201);
    }

    public function show($id)
    {
        $course = Course::with(['subCategory.category', 'certificatePricing'])->findOrFail($id);
        return response()->json(['course' => $course]);
    }

    /**
     * Update a course
     * 
     * Update course details and optionally update or set pricing. If pricing is provided and there's an active pricing,
     * it will be updated. If no active pricing exists, a new one will be created.
     * 
     * @group ACC Courses
     * @authenticated
     * 
     * @urlParam id integer required Course ID. Example: 1
     * 
     * @bodyParam sub_category_id integer optional Sub category ID. Example: 1
     * @bodyParam name string optional Course name. Example: Advanced Fire Safety
     * @bodyParam name_ar string optional Course name in Arabic. Example: السلامة من الحرائق المتقدمة
     * @bodyParam code string optional Unique course code. Example: AFS-001
     * @bodyParam description string optional Course description. Example: Advanced fire safety training course
     * @bodyParam duration_hours integer optional Course duration in hours. Example: 40
     * @bodyParam max_capacity integer optional Maximum capacity for classes of this course. Example: 25
     * @bodyParam assessor_required boolean optional Whether an assessor is required for this course. Example: true
     * @bodyParam level string optional Course level. Example: advanced
     * @bodyParam status string optional Course status. Example: active
     * @bodyParam pricing array optional Pricing information.
     * @bodyParam pricing.base_price number required Base price. Example: 550.00
     * @bodyParam pricing.currency string required Currency code (3 characters). Example: USD
     * 
     * @response 200 {
     *   "message": "Course updated successfully and pricing updated",
     *   "course": {
     *     "id": 1,
     *     "name": "Advanced Fire Safety",
     *     "code": "AFS-001",
     *     "current_price": {
     *       "base_price": 550.00,
     *       "currency": "USD"
     *       "effective_from": "2024-01-01",
     *       "effective_to": null
     *     }
     *   }
     * }
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $course = Course::where('acc_id', $acc->id)->findOrFail($id);

        // Validate course fields
        $request->validate([
            'sub_category_id' => 'sometimes|exists:sub_categories,id',
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'code' => 'sometimes|string|max:255|unique:courses,code,' . $id,
            'description' => 'nullable|string',
            'duration_hours' => 'sometimes|integer|min:1',
            'max_capacity' => 'sometimes|integer|min:1',
            'assessor_required' => 'nullable|boolean',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'status' => 'sometimes|in:active,inactive,archived',
            // Pricing fields (optional)
            'pricing' => 'sometimes|array',
            'pricing.base_price' => 'required_with:pricing|numeric|min:0',
            'pricing.currency' => 'required_with:pricing|string|size:3',
        ]);

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

        // Handle pricing update if provided (pricing is always effective - no date restrictions)
        if ($request->has('pricing') && $request->pricing) {
            $pricingData = $request->pricing;
            
            // Get existing pricing for this course
            $existingPricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->latest('created_at')
                ->first();

            if ($existingPricing) {
                // Update existing pricing (commissions not updated by ACC)
                $existingPricing->update([
                    'base_price' => $pricingData['base_price'],
                    'currency' => $pricingData['currency'],
                ]);
            } else {
                // Create new pricing if none exists (commissions are set by Group Admin, not ACC)
                CertificatePricing::create([
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

        // Reload course with relationships
        $course->load(['subCategory.category']);

        // Get the current pricing for this course (always effective)
        $currentPricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->latest('created_at')
            ->first();

        // Add pricing information to course
        $course->current_price = $currentPricing ? [
            'base_price' => $currentPricing->base_price,
            'currency' => $currentPricing->currency ?? 'USD',
        ] : null;

        $message = 'Course updated successfully';
        if ($request->has('pricing')) {
            $message .= ' and pricing updated';
        }

        return response()->json([
            'message' => $message,
            'course' => $course
        ]);
    }

    public function destroy($id)
    {
        $user = request()->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $course = Course::where('acc_id', $acc->id)->findOrFail($id);
        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }

    /**
     * Set course pricing
     * 
     * Set base price and commission percentages for a course. If there's an active pricing,
     * it will be ended (effective_to set) before creating the new one.
     * 
     * @group ACC Courses
     * @authenticated
     * 
     * @urlParam id integer required Course ID. Example: 1
     * 
     * @bodyParam base_price number required Base price for the course. Example: 500.00
     * @bodyParam currency string required Currency code (3 characters). Example: USD
     * @bodyParam group_commission_percentage number required Group commission percentage (0-100). Example: 10.0
     * @bodyParam training_center_commission_percentage number required Training center commission percentage (0-100). Example: 5.0
     * @bodyParam instructor_commission_percentage number required Instructor commission percentage (0-100). Example: 3.0
     * @bodyParam effective_from date required Date from which this pricing is effective. Example: 2024-01-01
     * @bodyParam effective_to date optional Date until which this pricing is effective. Example: 2024-12-31
     * 
     * @response 200 {
     *   "message": "Pricing set successfully",
     *   "pricing": {
     *     "id": 1,
     *     "course_id": 5,
     *     "base_price": 500.00,
     *     "currency": "USD",
     *     "group_commission_percentage": 10.0,
     *     "training_center_commission_percentage": 5.0,
     *     "instructor_commission_percentage": 3.0,
     *     "effective_from": "2024-01-01",
     *     "effective_to": null
     *   }
     * }
     */
    public function setPricing(Request $request, $id)
    {
        $request->validate([
            'base_price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'group_commission_percentage' => 'required|numeric|min:0|max:100',
            'training_center_commission_percentage' => 'required|numeric|min:0|max:100',
            'instructor_commission_percentage' => 'required|numeric|min:0|max:100',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        // Validate commission percentages don't exceed 100% total
        $totalCommission = $request->group_commission_percentage + 
                          $request->training_center_commission_percentage + 
                          $request->instructor_commission_percentage;
        
        if ($totalCommission > 100) {
            return response()->json([
                'message' => 'Total commission percentages cannot exceed 100%',
                'errors' => [
                    'commission_percentages' => ['The sum of all commission percentages is ' . $totalCommission . '% which exceeds 100%']
                ]
            ], 422);
        }

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $course = Course::where('acc_id', $acc->id)->findOrFail($id);

        // Check for overlapping active pricing and end it
        $activePricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->where('effective_from', '<=', $request->effective_from)
            ->where(function ($q) use ($request) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $request->effective_from);
            })
            ->first();

        if ($activePricing) {
            // End the previous pricing one day before the new one starts
            $newEffectiveFrom = Carbon::parse($request->effective_from);
            $previousEffectiveTo = $newEffectiveFrom->copy()->subDay();
            
            // Only update if the previous pricing doesn't already have an end date
            if (!$activePricing->effective_to || 
                Carbon::parse($activePricing->effective_to) > $previousEffectiveTo) {
                $activePricing->update([
                    'effective_to' => $previousEffectiveTo->format('Y-m-d')
                ]);
            }
        }

        // Create new pricing
        $pricing = CertificatePricing::create([
            'acc_id' => $acc->id,
            'course_id' => $course->id,
            'base_price' => $request->base_price,
            'currency' => $request->currency,
            'group_commission_percentage' => $request->group_commission_percentage,
            'training_center_commission_percentage' => $request->training_center_commission_percentage,
            'instructor_commission_percentage' => $request->instructor_commission_percentage,
            'effective_from' => $request->effective_from,
            'effective_to' => $request->effective_to,
        ]);

        return response()->json([
            'message' => 'Pricing set successfully',
            'pricing' => $pricing->fresh()
        ]);
    }

    /**
     * Update course pricing
     * 
     * Update the active pricing for a course. Updates the most recent active pricing record.
     * 
     * @group ACC Courses
     * @authenticated
     * 
     * @urlParam id integer required Course ID. Example: 1
     * 
     * @bodyParam base_price number optional Base price for the course. Example: 550.00
     * @bodyParam currency string optional Currency code (3 characters). Example: USD
     * @bodyParam group_commission_percentage number optional Group commission percentage (0-100). Example: 10.0
     * @bodyParam training_center_commission_percentage number optional Training center commission percentage (0-100). Example: 5.0
     * @bodyParam instructor_commission_percentage number optional Instructor commission percentage (0-100). Example: 3.0
     * @bodyParam effective_from date optional Date from which this pricing is effective. Example: 2024-01-01
     * @bodyParam effective_to date optional Date until which this pricing is effective. Example: 2024-12-31
     * 
     * @response 200 {
     *   "message": "Pricing updated successfully",
     *   "pricing": {
     *     "id": 1,
     *     "course_id": 5,
     *     "base_price": 550.00,
     *     "currency": "USD",
     *     "group_commission_percentage": 10.0,
     *     "training_center_commission_percentage": 5.0,
     *     "instructor_commission_percentage": 3.0,
     *     "effective_from": "2024-01-01",
     *     "effective_to": null
     *   }
     * }
     * @response 404 {
     *   "message": "Pricing not found"
     * }
     */
    public function updatePricing(Request $request, $id)
    {
        $request->validate([
            'base_price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'group_commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'training_center_commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'instructor_commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $course = Course::where('acc_id', $acc->id)->findOrFail($id);

        // Get the active pricing (most recent active one)
        $pricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->where(function ($q) {
                $q->where('effective_from', '<=', now())
                  ->where(function ($subQ) {
                      $subQ->whereNull('effective_to')
                           ->orWhere('effective_to', '>=', now());
                  });
            })
            ->latest('effective_from')
            ->first();

        // If no active pricing, try to get the latest one
        if (!$pricing) {
            $pricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->latest('effective_from')
                ->first();
        }

        if (!$pricing) {
            return response()->json(['message' => 'Pricing not found for this course'], 404);
        }

        // Validate commission percentages if provided
        $updateData = $request->only([
            'base_price', 'currency', 'group_commission_percentage',
            'training_center_commission_percentage', 'instructor_commission_percentage',
            'effective_from', 'effective_to'
        ]);

        // Check commission percentages if any are being updated
        if ($request->has('group_commission_percentage') || 
            $request->has('training_center_commission_percentage') || 
            $request->has('instructor_commission_percentage')) {
            
            $groupCommission = $request->group_commission_percentage ?? $pricing->group_commission_percentage;
            $tcCommission = $request->training_center_commission_percentage ?? $pricing->training_center_commission_percentage;
            $instructorCommission = $request->instructor_commission_percentage ?? $pricing->instructor_commission_percentage;
            
            $totalCommission = $groupCommission + $tcCommission + $instructorCommission;
            
            if ($totalCommission > 100) {
                return response()->json([
                    'message' => 'Total commission percentages cannot exceed 100%',
                    'errors' => [
                        'commission_percentages' => ['The sum of all commission percentages is ' . $totalCommission . '% which exceeds 100%']
                    ]
                ], 422);
            }
        }

        $pricing->update($updateData);

        return response()->json([
            'message' => 'Pricing updated successfully',
            'pricing' => $pricing->fresh()
        ]);
    }
}

