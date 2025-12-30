<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Course;
use App\Models\CertificatePricing;
use Illuminate\Http\Request;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class CourseController extends Controller
{
    #[OA\Get(
        path: "/acc/courses",
        summary: "List ACC courses",
        description: "Get all courses for the authenticated ACC with optional filtering.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "sub_category_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "level", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Courses retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "courses", type: "array", items: new OA\Items(type: "object"))
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

    #[OA\Post(
        path: "/acc/courses",
        summary: "Create a new course",
        description: "Create a new course with optional pricing. If pricing is provided, it will be set for the course.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["sub_category_id", "name", "code", "duration_hours", "max_capacity", "level", "status"],
                properties: [
                    new OA\Property(property: "sub_category_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Advanced Fire Safety"),
                    new OA\Property(property: "name_ar", type: "string", nullable: true, example: "السلامة من الحرائق المتقدمة"),
                    new OA\Property(property: "code", type: "string", example: "AFS-001"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Advanced fire safety training course"),
                    new OA\Property(property: "duration_hours", type: "integer", example: 40),
                    new OA\Property(property: "max_capacity", type: "integer", example: 20),
                    new OA\Property(property: "assessor_required", type: "boolean", nullable: true, example: true),
                    new OA\Property(property: "level", type: "string", enum: ["beginner", "intermediate", "advanced"], example: "advanced"),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive", "archived"], example: "active"),
                    new OA\Property(
                        property: "pricing",
                        type: "object",
                        nullable: true,
                        properties: [
                            new OA\Property(property: "base_price", type: "number", format: "float", example: 500.00),
                            new OA\Property(property: "currency", type: "string", example: "USD")
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Course created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Course created successfully with pricing"),
                        new OA\Property(property: "course", type: "object")
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

    #[OA\Get(
        path: "/acc/courses/{id}",
        summary: "Get course details",
        description: "Get detailed information about a specific course including pricing.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Course retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "course", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Course not found")
        ]
    )]
    public function show($id)
    {
        $course = Course::with(['subCategory.category', 'certificatePricing'])->findOrFail($id);
        return response()->json(['course' => $course]);
    }

    #[OA\Put(
        path: "/acc/courses/{id}",
        summary: "Update course",
        description: "Update course details and optionally update or set pricing. If pricing is provided, it will be updated or created.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "sub_category_id", type: "integer", nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true, example: "Advanced Fire Safety"),
                    new OA\Property(property: "name_ar", type: "string", nullable: true),
                    new OA\Property(property: "code", type: "string", nullable: true, example: "AFS-001"),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "duration_hours", type: "integer", nullable: true, example: 40),
                    new OA\Property(property: "max_capacity", type: "integer", nullable: true, example: 25),
                    new OA\Property(property: "assessor_required", type: "boolean", nullable: true, example: true),
                    new OA\Property(property: "level", type: "string", enum: ["beginner", "intermediate", "advanced"], nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive", "archived"], nullable: true),
                    new OA\Property(
                        property: "pricing",
                        type: "object",
                        nullable: true,
                        properties: [
                            new OA\Property(property: "base_price", type: "number", format: "float", example: 550.00),
                            new OA\Property(property: "currency", type: "string", example: "USD")
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Course updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Course updated successfully and pricing updated"),
                        new OA\Property(property: "course", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Course not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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

    #[OA\Delete(
        path: "/acc/courses/{id}",
        summary: "Delete course",
        description: "Delete a course. This action cannot be undone.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Course deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Course deleted successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Course not found")
        ]
    )]
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

    #[OA\Post(
        path: "/acc/courses/{id}/pricing",
        summary: "Set course pricing",
        description: "Set base price and commission percentages for a course. Pricing is always effective.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["base_price", "currency"],
                properties: [
                    new OA\Property(property: "base_price", type: "number", format: "float", example: 500.00),
                    new OA\Property(property: "currency", type: "string", example: "USD")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Pricing set successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Pricing set successfully"),
                        new OA\Property(property: "pricing", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Course not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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

    #[OA\Put(
        path: "/acc/courses/{id}/pricing",
        summary: "Update course pricing",
        description: "Update the active pricing for a course. Pricing is always effective.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "base_price", type: "number", format: "float", nullable: true, example: 550.00),
                    new OA\Property(property: "currency", type: "string", nullable: true, example: "USD")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Pricing updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Pricing updated successfully"),
                        new OA\Property(property: "pricing", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Pricing not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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

