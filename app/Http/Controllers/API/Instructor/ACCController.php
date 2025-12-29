<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\ACC;
use App\Models\InstructorAccAuthorization;
use App\Models\TrainingClass;
use Illuminate\Http\Request;

class ACCController extends Controller
{
    /**
     * Get ACCs the instructor worked with
     * 
     * Get a list of all ACCs that have authorized this instructor or have courses assigned to this instructor.
     * 
     * @group Instructor ACCs
     * @authenticated
     * 
     * @response 200 {
     *   "accs": [
     *     {
     *       "id": 1,
     *       "name": "ABC Accreditation Body",
     *       "email": "info@abc.com",
     *       "phone": "+1234567890",
     *       "country": "USA",
     *       "status": "active",
     *       "authorization_status": "approved",
     *       "authorization_date": "2024-01-15T10:30:00.000000Z",
     *       "classes_count": 8,
     *       "completed_classes": 5
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

        // Get ACCs from authorizations
        $authorizedAccIds = InstructorAccAuthorization::where('instructor_id', $instructor->id)
            ->where('status', 'approved')
            ->where('payment_status', 'paid')
            ->distinct()
            ->pluck('acc_id')
            ->toArray();

        // Get ACCs from courses in classes assigned to this instructor
        $courseAccIds = TrainingClass::where('instructor_id', $instructor->id)
            ->with('course:id,acc_id')
            ->get()
            ->pluck('course.acc_id')
            ->filter()
            ->unique()
            ->toArray();

        // Merge and get unique ACC IDs
        $accIds = array_unique(array_merge($authorizedAccIds, $courseAccIds));

        $accs = ACC::whereIn('id', $accIds)
            ->get()
            ->map(function ($acc) use ($instructor) {
                // Get authorization info
                $authorization = InstructorAccAuthorization::where('instructor_id', $instructor->id)
                    ->where('acc_id', $acc->id)
                    ->where('status', 'approved')
                    ->where('payment_status', 'paid')
                    ->first();

                // Get classes count for this ACC
                $classes = TrainingClass::where('instructor_id', $instructor->id)
                    ->whereHas('course', function ($query) use ($acc) {
                        $query->where('acc_id', $acc->id);
                    })
                    ->get();

                return [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'legal_name' => $acc->legal_name,
                    'email' => $acc->email,
                    'phone' => $acc->phone,
                    'country' => $acc->country,
                    'address' => $acc->address,
                    'website' => $acc->website,
                    'status' => $acc->status,
                    'authorization' => $authorization ? [
                        'status' => $authorization->status,
                        'authorization_date' => $authorization->reviewed_at,
                        'payment_status' => $authorization->payment_status,
                        'authorization_price' => $authorization->authorization_price,
                    ] : null,
                    'classes_count' => $classes->count(),
                    'completed_classes' => $classes->where('status', 'completed')->count(),
                    'upcoming_classes' => $classes->where('status', 'scheduled')
                        ->where('start_date', '>=', now())
                        ->count(),
                    'in_progress_classes' => $classes->where('status', 'in_progress')->count(),
                ];
            });

        return response()->json(['accs' => $accs]);
    }
}

