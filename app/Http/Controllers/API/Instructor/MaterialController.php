<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\TrainingClass;
use App\Models\ACCMaterial;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        // Get materials from courses assigned to this instructor
        $assignedCourseIds = TrainingClass::where('instructor_id', $instructor->id)
            ->pluck('course_id')
            ->unique();

        $materials = ACCMaterial::whereIn('course_id', $assignedCourseIds)
            ->where('status', 'active')
            ->with('course')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['materials' => $materials]);
    }
}

