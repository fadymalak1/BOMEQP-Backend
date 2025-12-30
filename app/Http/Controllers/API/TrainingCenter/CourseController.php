<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TrainingCenterAccAuthorization;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CourseController extends Controller
{
    #[OA\Get(
        path: "/training-center/courses",
        summary: "List available courses",
        description: "Get all courses from ACCs that have approved this training center. Only shows active courses.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
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
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "ACC not authorized"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Get approved ACC IDs for this training center
        $approvedAccIds = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('status', 'approved')
            ->pluck('acc_id');

        if ($approvedAccIds->isEmpty()) {
            return response()->json([
                'courses' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ]
            ]);
        }

        // Get courses from approved ACCs
        $query = Course::whereIn('acc_id', $approvedAccIds)
            ->where('status', 'active') // Only show active courses
            ->with(['acc', 'subCategory.category', 'certificatePricing' => function($query) {
                $query->where('effective_to', null)
                      ->orWhere('effective_to', '>=', now())
                      ->orderBy('effective_from', 'desc')
                      ->limit(1);
            }]);

        // Optional filters
        if ($request->has('acc_id')) {
            // Verify this ACC is in the approved list
            if ($approvedAccIds->contains($request->acc_id)) {
                $query->where('acc_id', $request->acc_id);
            } else {
                return response()->json([
                    'message' => 'ACC not authorized for this training center',
                    'courses' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ]
                ], 403);
            }
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

        return response()->json([
            'courses' => $courses->items(),
            'pagination' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/training-center/courses/{id}",
        summary: "Get course details",
        description: "Get detailed information about a specific course from an authorized ACC.",
        tags: ["Training Center"],
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
            new OA\Response(response: 403, description: "Course not available - ACC authorization required"),
            new OA\Response(response: 404, description: "Course not found")
        ]
    )]
    public function show($id)
    {
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Get approved ACC IDs
        $approvedAccIds = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('status', 'approved')
            ->pluck('acc_id');

        $course = Course::whereIn('acc_id', $approvedAccIds)
            ->with([
                'acc',
                'subCategory.category',
                'certificatePricing' => function($query) {
                    $query->orderBy('effective_from', 'desc');
                },
                'classes'
            ])
            ->findOrFail($id);

        return response()->json(['course' => $course]);
    }
}

