<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\TrainingClass;
use App\Models\Transaction;
use App\Models\ACC;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)
            ->with(['trainingCenter:id,name,email,phone,country,city'])
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        // Profile information
        $profile = [
            'id' => $instructor->id,
            'first_name' => $instructor->first_name,
            'last_name' => $instructor->last_name,
            'full_name' => $instructor->first_name . ' ' . $instructor->last_name,
            'email' => $instructor->email,
            'phone' => $instructor->phone,
            'id_number' => $instructor->id_number,
            'cv_url' => $instructor->cv_url,
            'certificates' => $instructor->certificates_json ?? [],
            'specializations' => $instructor->specializations ?? [],
            'status' => $instructor->status,
            'training_center' => $instructor->trainingCenter,
        ];

        // Classes statistics
        $assignedClasses = TrainingClass::where('instructor_id', $instructor->id)->count();
        $upcomingClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'scheduled')
            ->where('start_date', '>=', now())
            ->count();
        $completedClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'completed')
            ->count();
        $inProgressClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'in_progress')
            ->count();

        // Recent classes
        $assignedClassesList = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course:id,name,code', 'trainingCenter:id,name'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function($class) {
                return [
                    'id' => $class->id,
                    'course' => [
                        'id' => $class->course->id ?? null,
                        'name' => $class->course->name ?? '',
                        'code' => $class->course->code ?? '',
                    ],
                    'training_center' => [
                        'id' => $class->trainingCenter->id ?? null,
                        'name' => $class->trainingCenter->name ?? '',
                    ],
                    'start_date' => $class->start_date,
                    'end_date' => $class->end_date,
                    'status' => $class->status,
                    'enrolled_count' => $class->enrolled_count,
                    'max_capacity' => $class->max_capacity,
                    'location' => $class->location,
                ];
            });

        // Earnings calculation
        $earningsThisMonth = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        $totalEarnings = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->sum('amount');

        $pendingEarnings = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'pending')
            ->sum('amount');

        // Training Centers worked with
        $trainingCenterIds = TrainingClass::where('instructor_id', $instructor->id)
            ->distinct()
            ->pluck('training_center_id')
            ->toArray();
        
        if ($instructor->training_center_id && !in_array($instructor->training_center_id, $trainingCenterIds)) {
            $trainingCenterIds[] = $instructor->training_center_id;
        }

        $trainingCenters = \App\Models\TrainingCenter::whereIn('id', $trainingCenterIds)
            ->select('id', 'name', 'email', 'phone', 'country', 'city', 'status')
            ->get()
            ->map(function ($tc) use ($instructor) {
                $classesCount = TrainingClass::where('instructor_id', $instructor->id)
                    ->where('training_center_id', $tc->id)
                    ->count();
                return [
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'email' => $tc->email,
                    'phone' => $tc->phone,
                    'country' => $tc->country,
                    'city' => $tc->city,
                    'status' => $tc->status,
                    'classes_count' => $classesCount,
                ];
            });

        // ACCs worked with
        $authorizedAccIds = \App\Models\InstructorAccAuthorization::where('instructor_id', $instructor->id)
            ->where('status', 'approved')
            ->where('payment_status', 'paid')
            ->distinct()
            ->pluck('acc_id')
            ->toArray();

        $courseAccIds = TrainingClass::where('instructor_id', $instructor->id)
            ->with('course:id,acc_id')
            ->get()
            ->pluck('course.acc_id')
            ->filter()
            ->unique()
            ->toArray();

        $accIds = array_unique(array_merge($authorizedAccIds, $courseAccIds));

        $accs = ACC::whereIn('id', $accIds)
            ->select('id', 'name', 'email', 'phone', 'country', 'status')
            ->get()
            ->map(function ($acc) use ($instructor) {
                $classesCount = TrainingClass::where('instructor_id', $instructor->id)
                    ->whereHas('course', function ($query) use ($acc) {
                        $query->where('acc_id', $acc->id);
                    })
                    ->count();
                
                $authorization = \App\Models\InstructorAccAuthorization::where('instructor_id', $instructor->id)
                    ->where('acc_id', $acc->id)
                    ->where('status', 'approved')
                    ->where('payment_status', 'paid')
                    ->first();

                return [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'email' => $acc->email,
                    'phone' => $acc->phone,
                    'country' => $acc->country,
                    'status' => $acc->status,
                    'is_authorized' => $authorization ? true : false,
                    'authorization_date' => $authorization->reviewed_at ?? null,
                    'classes_count' => $classesCount,
                ];
            });

        // Unread notifications count
        $unreadNotificationsCount = $user->unreadNotifications()->count();

        return response()->json([
            'profile' => $profile,
            'statistics' => [
                'total_classes' => $assignedClasses,
                'upcoming_classes' => $upcomingClasses,
                'in_progress_classes' => $inProgressClasses,
                'completed_classes' => $completedClasses,
            ],
            'recent_classes' => $assignedClassesList,
            'earnings' => [
                'total' => $totalEarnings,
                'this_month' => $earningsThisMonth,
                'pending' => $pendingEarnings,
                'paid' => $totalEarnings - $pendingEarnings,
            ],
            'training_centers' => $trainingCenters,
            'accs' => $accs,
            'unread_notifications_count' => $unreadNotificationsCount,
        ]);
    }
}

