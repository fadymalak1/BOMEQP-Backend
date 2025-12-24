<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Course;
use App\Models\CertificatePricing;
use Illuminate\Http\Request;

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

        // Add current pricing to each course
        $coursesWithDetails = $courses->map(function ($course) use ($acc) {
            // Get the current active pricing for this course
            $currentPricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->where('effective_from', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                })
                ->latest('effective_from')
                ->first();

            // Add pricing information to course
            $course->current_price = $currentPricing ? [
                'base_price' => $currentPricing->base_price,
                'currency' => $currentPricing->currency ?? 'USD',
                'group_commission_percentage' => $currentPricing->group_commission_percentage,
                'training_center_commission_percentage' => $currentPricing->training_center_commission_percentage,
                'instructor_commission_percentage' => $currentPricing->instructor_commission_percentage,
                'effective_from' => $currentPricing->effective_from,
                'effective_to' => $currentPricing->effective_to,
            ] : null;

            return $course;
        });

        return response()->json(['courses' => $coursesWithDetails->values()]);
    }

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
            'level' => 'required|in:beginner,intermediate,advanced',
            'status' => 'required|in:active,inactive,archived',
            // Pricing fields (optional)
            'pricing' => 'nullable|array',
            'pricing.base_price' => 'required_with:pricing|numeric|min:0',
            'pricing.currency' => 'required_with:pricing|string|size:3',
            'pricing.group_commission_percentage' => 'required_with:pricing|numeric|min:0|max:100',
            'pricing.training_center_commission_percentage' => 'required_with:pricing|numeric|min:0|max:100',
            'pricing.instructor_commission_percentage' => 'required_with:pricing|numeric|min:0|max:100',
            'pricing.effective_from' => 'required_with:pricing|date',
            'pricing.effective_to' => 'nullable|date|after:pricing.effective_from',
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
            'level' => $request->level,
            'status' => $request->status,
        ]);

        // Create pricing if provided
        if ($request->has('pricing') && $request->pricing) {
            $pricingData = $request->pricing;
            CertificatePricing::create([
                'acc_id' => $acc->id,
                'course_id' => $course->id,
                'base_price' => $pricingData['base_price'],
                'currency' => $pricingData['currency'],
                'group_commission_percentage' => $pricingData['group_commission_percentage'],
                'training_center_commission_percentage' => $pricingData['training_center_commission_percentage'],
                'instructor_commission_percentage' => $pricingData['instructor_commission_percentage'],
                'effective_from' => $pricingData['effective_from'],
                'effective_to' => $pricingData['effective_to'] ?? null,
            ]);
        }

        // Reload course with relationships
        $course->load(['subCategory.category']);

        // Get the current active pricing for this course
        $currentPricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->latest('effective_from')
            ->first();

        // Add pricing information to course
        $course->current_price = $currentPricing ? [
            'base_price' => $currentPricing->base_price,
            'currency' => $currentPricing->currency ?? 'USD',
            'group_commission_percentage' => $currentPricing->group_commission_percentage,
            'training_center_commission_percentage' => $currentPricing->training_center_commission_percentage,
            'instructor_commission_percentage' => $currentPricing->instructor_commission_percentage,
            'effective_from' => $currentPricing->effective_from,
            'effective_to' => $currentPricing->effective_to,
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
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'status' => 'sometimes|in:active,inactive,archived',
            // Pricing fields (optional)
            'pricing' => 'sometimes|array',
            'pricing.base_price' => 'required_with:pricing|numeric|min:0',
            'pricing.currency' => 'required_with:pricing|string|size:3',
            'pricing.group_commission_percentage' => 'required_with:pricing|numeric|min:0|max:100',
            'pricing.training_center_commission_percentage' => 'required_with:pricing|numeric|min:0|max:100',
            'pricing.instructor_commission_percentage' => 'required_with:pricing|numeric|min:0|max:100',
            'pricing.effective_from' => 'required_with:pricing|date',
            'pricing.effective_to' => 'nullable|date|after:pricing.effective_from',
        ]);

        // Update course fields
        $course->update($request->only([
            'sub_category_id', 'name', 'name_ar', 'code', 'description',
            'duration_hours', 'level', 'status'
        ]));

        // Handle pricing update if provided
        if ($request->has('pricing')) {
            $pricingData = $request->pricing;
            
            // Check if there's an existing active pricing
            $existingPricing = CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $acc->id)
                ->where('effective_from', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                })
                ->latest('effective_from')
                ->first();

            if ($existingPricing) {
                // Update existing pricing
                $existingPricing->update([
                    'base_price' => $pricingData['base_price'],
                    'currency' => $pricingData['currency'],
                    'group_commission_percentage' => $pricingData['group_commission_percentage'],
                    'training_center_commission_percentage' => $pricingData['training_center_commission_percentage'],
                    'instructor_commission_percentage' => $pricingData['instructor_commission_percentage'],
                    'effective_from' => $pricingData['effective_from'],
                    'effective_to' => $pricingData['effective_to'] ?? null,
                ]);
            } else {
                // Create new pricing
                CertificatePricing::create([
                    'acc_id' => $acc->id,
                    'course_id' => $course->id,
                    'base_price' => $pricingData['base_price'],
                    'currency' => $pricingData['currency'],
                    'group_commission_percentage' => $pricingData['group_commission_percentage'],
                    'training_center_commission_percentage' => $pricingData['training_center_commission_percentage'],
                    'instructor_commission_percentage' => $pricingData['instructor_commission_percentage'],
                    'effective_from' => $pricingData['effective_from'],
                    'effective_to' => $pricingData['effective_to'] ?? null,
                ]);
            }
        }

        // Reload course with relationships
        $course->load(['subCategory.category']);

        // Get the current active pricing for this course
        $currentPricing = CertificatePricing::where('course_id', $course->id)
            ->where('acc_id', $acc->id)
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->latest('effective_from')
            ->first();

        // Add pricing information to course
        $course->current_price = $currentPricing ? [
            'base_price' => $currentPricing->base_price,
            'currency' => $currentPricing->currency ?? 'USD',
            'group_commission_percentage' => $currentPricing->group_commission_percentage,
            'training_center_commission_percentage' => $currentPricing->training_center_commission_percentage,
            'instructor_commission_percentage' => $currentPricing->instructor_commission_percentage,
            'effective_from' => $currentPricing->effective_from,
            'effective_to' => $currentPricing->effective_to,
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

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $course = Course::where('acc_id', $acc->id)->findOrFail($id);

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

        return response()->json(['message' => 'Pricing set successfully', 'pricing' => $pricing]);
    }

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
        $pricing = CertificatePricing::where('course_id', $course->id)->latest()->first();

        if (!$pricing) {
            return response()->json(['message' => 'Pricing not found'], 404);
        }

        $pricing->update($request->only([
            'base_price', 'currency', 'group_commission_percentage',
            'training_center_commission_percentage', 'instructor_commission_percentage',
            'effective_from', 'effective_to'
        ]));

        return response()->json(['message' => 'Pricing updated successfully', 'pricing' => $pricing]);
    }
}

