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
    /**
     * Get instructor dashboard data
     * 
     * Returns all data needed for the instructor dashboard including:
     * - Profile summary (name, email)
     * - Class statistics (total, upcoming, in progress, completed)
     * - Recent classes
     * - Additional data (earnings, training centers, ACCs, notifications)
     * 
     * @group Instructor Dashboard
     * @authenticated
     * 
     * @response 200 {
     *   "profile": {
     *     "name": "Fady Malak",
     *     "email": "fady@example.com"
     *   },
     *   "statistics": {
     *     "total_classes": 10,
     *     "upcoming_classes": 2,
     *     "in_progress": 1,
     *     "completed": 7
     *   },
     *   "recent_classes": [...],
     *   "earnings": {...},
     *   "training_centers": [...],
     *   "accs": [...],
     *   "unread_notifications_count": 3
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)
            ->with(['trainingCenter:id,name,email,phone,country,city'])
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        // Profile summary for dashboard (matching UI requirements)
        $profile = [
            'name' => trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
            'email' => $instructor->email ?? null,
        ];

        // If name is empty, set to null
        if (empty(trim($profile['name']))) {
            $profile['name'] = null;
        }

        // Classes statistics - matching dashboard cards
        $totalClasses = TrainingClass::where('instructor_id', $instructor->id)->count();
        
        $upcomingClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'scheduled')
            ->where('start_date', '>=', now()->startOfDay())
            ->count();
        
        $inProgress = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'in_progress')
            ->count();
        
        $completed = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'completed')
            ->count();

        // Recent classes (latest 10)
        $recentClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course:id,name,code,max_capacity', 'trainingCenter:id,name'])
            ->orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($class) {
                return [
                    'id' => $class->id,
                    'course' => [
                        'id' => $class->course->id ?? null,
                        'name' => $class->course->name ?? 'N/A',
                        'code' => $class->course->code ?? '',
                    ],
                    'training_center' => [
                        'id' => $class->trainingCenter->id ?? null,
                        'name' => $class->trainingCenter->name ?? 'N/A',
                    ],
                    'start_date' => $class->start_date ? $class->start_date->format('Y-m-d') : null,
                    'end_date' => $class->end_date ? $class->end_date->format('Y-m-d') : null,
                    'status' => $class->status,
                    'enrolled_count' => $class->enrolled_count ?? 0,
                    'max_capacity' => $class->course->max_capacity ?? 0,
                    'location' => $class->location ?? 'physical',
                    'location_details' => $class->location_details ?? null,
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
            // Profile summary for dashboard UI
            'profile' => $profile,
            
            // Statistics matching dashboard cards
            'statistics' => [
                'total_classes' => $totalClasses,
                'upcoming_classes' => $upcomingClasses,
                'in_progress' => $inProgress,
                'completed' => $completed,
            ],
            
            // Recent classes for dashboard
            'recent_classes' => $recentClasses,
            
            // Additional data
            'earnings' => [
                'total' => round($totalEarnings, 2),
                'this_month' => round($earningsThisMonth, 2),
                'pending' => round($pendingEarnings, 2),
                'paid' => round($totalEarnings - $pendingEarnings, 2),
            ],
            'training_centers' => $trainingCenters,
            'accs' => $accs,
            'unread_notifications_count' => $unreadNotificationsCount,
        ]);
    }
}

