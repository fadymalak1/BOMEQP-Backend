<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\TrainingCenter;
use App\Models\TrainingClass;
use Illuminate\Http\Request;

class TrainingCenterController extends Controller
{
    /**
     * Get training centers the instructor worked with
     * 
     * Get a list of all training centers that have assigned classes to this instructor.
     * 
     * @group Instructor Training Centers
     * @authenticated
     * 
     * @response 200 {
     *   "training_centers": [
     *     {
     *       "id": 1,
     *       "name": "ABC Training Center",
     *       "email": "info@abc.com",
     *       "phone": "+1234567890",
     *       "country": "USA",
     *       "city": "New York",
     *       "status": "active",
     *       "classes_count": 5,
     *       "completed_classes": 3,
     *       "upcoming_classes": 2
     *     }
     *   ]
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        // Get unique training centers from classes assigned to this instructor
        $trainingCenterIds = TrainingClass::where('instructor_id', $instructor->id)
            ->distinct()
            ->pluck('training_center_id')
            ->toArray();

        // Also include the instructor's own training center
        if ($instructor->training_center_id && !in_array($instructor->training_center_id, $trainingCenterIds)) {
            $trainingCenterIds[] = $instructor->training_center_id;
        }

        $trainingCenters = TrainingCenter::whereIn('id', $trainingCenterIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($tc) use ($instructor) {
                $classes = TrainingClass::where('instructor_id', $instructor->id)
                    ->where('training_center_id', $tc->id)
                    ->get();

                return [
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'legal_name' => $tc->legal_name,
                    'email' => $tc->email,
                    'phone' => $tc->phone,
                    'country' => $tc->country,
                    'city' => $tc->city,
                    'address' => $tc->address,
                    'status' => $tc->status,
                    'classes_count' => $classes->count(),
                    'completed_classes' => $classes->where('status', 'completed')->count(),
                    'upcoming_classes' => $classes->where('status', 'scheduled')
                        ->where('start_date', '>=', now())
                        ->count(),
                    'in_progress_classes' => $classes->where('status', 'in_progress')->count(),
                ];
            });

        return response()->json(['training_centers' => $trainingCenters]);
    }
}

