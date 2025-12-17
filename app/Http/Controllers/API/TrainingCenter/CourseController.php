<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\TrainingCenterAccAuthorization;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Get all courses from ACCs that have approved this training center
     */
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

    /**
     * Get a specific course details
     */
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

