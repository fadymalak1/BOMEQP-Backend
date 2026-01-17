<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Course;
use App\Services\CourseManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class CourseController extends Controller
{
    protected CourseManagementService $courseService;

    public function __construct(CourseManagementService $courseService)
    {
        $this->courseService = $courseService;
    }
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
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), description: "Search by course name, code, or description"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 15, description: "Number of items per page (default: 15)"),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer"), example: 1, description: "Page number (default: 1)")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Courses retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "last_page", type: "integer")
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

        $courses = $this->courseService->getCoursesWithPricing($request, $acc);

        return response()->json($courses);
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
            'pricing' => 'nullable|array',
            'pricing.base_price' => 'required_with:pricing|numeric|min:0',
            'pricing.currency' => 'required_with:pricing|string|size:3',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        try {
            $result = $this->courseService->createCourse($request, $acc);
            return response()->json([
                'message' => $result['message'],
                'course' => $result['course']
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create course', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to create course',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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
            'pricing' => 'sometimes|array',
            'pricing.base_price' => 'required_with:pricing|numeric|min:0',
            'pricing.currency' => 'required_with:pricing|string|size:3',
        ]);

        try {
            $result = $this->courseService->updateCourse($request, $course, $acc);
            return response()->json([
                'message' => $result['message'],
                'course' => $result['course']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update course', [
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to update course',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $course = Course::where('acc_id', $acc->id)->findOrFail($id);

        try {
            $result = $this->courseService->setPricing($request, $course, $acc);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null
                ], $result['code']);
            }

            return response()->json([
                'message' => $result['message'],
                'pricing' => $result['pricing']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to set pricing', [
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to set pricing',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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

        try {
            $result = $this->courseService->updatePricing($request, $course, $acc);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null
                ], $result['code']);
            }

            return response()->json([
                'message' => $result['message'],
                'pricing' => $result['pricing']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update pricing', [
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to update pricing',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

