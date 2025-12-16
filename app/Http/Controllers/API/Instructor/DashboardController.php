<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\TrainingClass;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $assignedClasses = TrainingClass::where('instructor_id', $instructor->id)->count();
        $upcomingClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'scheduled')
            ->where('start_date', '>=', now())
            ->count();
        $completedClasses = TrainingClass::where('instructor_id', $instructor->id)
            ->where('status', 'completed')
            ->count();

        // Calculate earnings (simplified - should calculate from commission ledger)
        $earningsThisMonth = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        $assignedClassesList = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course', 'trainingCenter'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function($class) {
                return [
                    'id' => $class->id,
                    'course' => $class->course->name ?? '',
                    'start_date' => $class->start_date,
                    'end_date' => $class->end_date,
                    'status' => $class->status,
                    'enrolled_count' => $class->enrolled_count,
                    'max_capacity' => $class->max_capacity,
                ];
            });

        $totalEarnings = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->sum('amount');

        $pendingEarnings = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'assigned_classes' => $assignedClassesList,
            'upcoming_classes' => $upcomingClasses,
            'completed_classes' => $completedClasses,
            'earnings' => [
                'total' => $totalEarnings,
                'pending' => $pendingEarnings,
                'paid' => $totalEarnings - $pendingEarnings,
            ],
            'available_materials' => [],
            'notifications' => [],
        ]);
    }
}

