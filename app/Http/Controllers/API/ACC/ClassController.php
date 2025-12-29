<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingClass;
use App\Models\TrainingCenterAccAuthorization;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    /**
     * Get all classes from training centers that have authorization from this ACC
     * 
     * Only shows classes for courses that belong to the ACC and from training centers
     * that have approved authorization from the ACC.
     * 
     * @group ACC Classes
     * @authenticated
     * 
     * @queryParam status string Filter by class status (scheduled, in_progress, completed, cancelled). Example: in_progress
     * @queryParam training_center_id integer Filter by training center ID. Example: 1
     * @queryParam course_id integer Filter by course ID. Example: 1
     * @queryParam date_from date Filter classes starting from date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam date_to date Filter classes starting until date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam per_page integer Items per page. Example: 15
     * @queryParam page integer Page number. Example: 1
     * 
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "training_center_id": 1,
     *       "course_id": 1,
     *       "instructor_id": 1,
     *       "start_date": "2024-02-01",
     *       "end_date": "2024-02-05",
     *       "status": "scheduled",
     *       "max_capacity": 20,
     *       "enrolled_count": 15,
     *       "location": "physical",
     *       "location_details": "Training Room A",
     *       "course": {
     *         "id": 1,
     *         "name": "Fire Safety",
     *         "code": "FS-001"
     *       },
     *       "training_center": {
     *         "id": 1,
     *         "name": "ABC Training Center",
     *         "email": "info@abc.com"
     *       },
     *       "instructor": {
     *         "id": 1,
     *         "first_name": "John",
     *         "last_name": "Doe"
     *       }
     *     }
     *   ],
     *   "current_page": 1,
     *   "per_page": 15,
     *   "total": 50
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Get training center IDs that have approved authorization from this ACC
        $authorizedTrainingCenterIds = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->pluck('training_center_id')
            ->toArray();

        // Build query: classes from authorized training centers for ACC's courses
        $query = TrainingClass::whereHas('course', function ($q) use ($acc) {
                $q->where('acc_id', $acc->id);
            })
            ->whereIn('training_center_id', $authorizedTrainingCenterIds)
            ->with(['course', 'trainingCenter', 'instructor']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('training_center_id')) {
            $query->where('training_center_id', $request->training_center_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('start_date', '<=', $request->date_to);
        }

        // Order by start date (upcoming first)
        $query->orderBy('start_date', 'desc');

        // Paginate
        $perPage = $request->get('per_page', 15);
        $classes = $query->paginate($perPage);

        return response()->json($classes);
    }

    /**
     * Get a specific class by ID
     * 
     * @group ACC Classes
     * @authenticated
     * 
     * @urlParam id integer required The ID of the class. Example: 1
     * 
     * @response 200 {
     *   "id": 1,
     *   "training_center_id": 1,
     *   "course_id": 1,
     *   "instructor_id": 1,
     *   "start_date": "2024-02-01",
     *   "end_date": "2024-02-05",
     *   "status": "scheduled",
     *   "max_capacity": 20,
     *   "enrolled_count": 15,
     *   "location": "physical",
     *   "location_details": "Training Room A",
     *   "schedule_json": {
     *     "monday": "09:00-17:00",
     *     "tuesday": "09:00-17:00"
     *   },
     *   "course": {
     *     "id": 1,
     *     "name": "Fire Safety",
     *     "code": "FS-001",
     *     "description": "Fire safety training course"
     *   },
     *   "training_center": {
     *     "id": 1,
     *     "name": "ABC Training Center",
     *     "email": "info@abc.com",
     *     "phone": "+1234567890"
     *   },
     *   "instructor": {
     *     "id": 1,
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email": "john@example.com"
     *   }
     * }
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Get training center IDs that have approved authorization from this ACC
        $authorizedTrainingCenterIds = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->pluck('training_center_id')
            ->toArray();

        $class = TrainingClass::whereHas('course', function ($q) use ($acc) {
                $q->where('acc_id', $acc->id);
            })
            ->whereIn('training_center_id', $authorizedTrainingCenterIds)
            ->with(['course', 'trainingCenter', 'instructor'])
            ->find($id);

        if (!$class) {
            return response()->json(['message' => 'Class not found or not authorized'], 404);
        }

        return response()->json($class);
    }
}

