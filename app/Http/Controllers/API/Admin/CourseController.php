<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CourseController extends Controller
{
    #[OA\Get(
        path: "/admin/courses",
        summary: "List all courses",
        description: "Get all courses in the system with optional filtering. Includes current pricing information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["active", "inactive"]), example: "active"),
            new OA\Parameter(name: "sub_category_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "level", in: "query", schema: new OA\Schema(type: "string", enum: ["beginner", "intermediate", "advanced"]), example: "beginner"),
            new OA\Parameter(name: "search", in: "query", schema: new OA\Schema(type: "string"), example: "fire safety"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Courses retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "courses", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = Course::with(['acc', 'subCategory.category']);

        // Optional filters
        if ($request->has('acc_id')) {
            $query->where('acc_id', $request->acc_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->sub_category_id);
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

        $courses = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        // Add current pricing to each course
        $coursesWithPricing = $courses->getCollection()->map(function ($course) {
            // Get the current active pricing for this course
            $currentPricing = \App\Models\CertificatePricing::where('course_id', $course->id)
                ->where('acc_id', $course->acc_id)
                ->where('effective_from', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                })
                ->latest('effective_from')
                ->first();

            // Get ACC to retrieve commission percentage (set by Group Admin)
            $acc = $course->acc;

            // Add pricing information to course (commission comes from ACC, set by Group Admin)
            $course->current_price = $currentPricing ? [
                'base_price' => $currentPricing->base_price,
                'currency' => $currentPricing->currency ?? 'USD',
                'group_commission_percentage' => $acc->commission_percentage ?? 0, // From ACC, set by Group Admin
                'effective_from' => $currentPricing->effective_from,
                'effective_to' => $currentPricing->effective_to,
            ] : null;

            return $course;
        });

        return response()->json([
            'courses' => $coursesWithPricing->values(),
            'pagination' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/admin/courses/{id}",
        summary: "Get course details",
        description: "Get detailed information about a specific course including ACC, category, pricing, classes, and certificates.",
        tags: ["Admin"],
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
        $course = Course::with([
            'acc',
            'subCategory.category',
            'certificatePricing',
            'classes',
            'certificates',
            'certificateCodes',
            'trainingClasses'
        ])->findOrFail($id);

        return response()->json(['course' => $course]);
    }
}

