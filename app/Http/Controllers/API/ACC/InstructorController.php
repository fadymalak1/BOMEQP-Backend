<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\InstructorAccAuthorization;
use App\Models\Instructor;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class InstructorController extends Controller
{
    public function requests(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $requests = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->with(['instructor', 'trainingCenter'])
            ->orderBy('request_date', 'desc')
            ->get();

        return response()->json(['requests' => $requests]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'authorization_price' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'approved',
            'authorization_price' => $request->authorization_price,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'group_admin_status' => 'pending', // Waiting for Group Admin to set commission
        ]);

        // Get course IDs from documents_json
        $documentsData = $authorization->documents_json ?? [];
        $courseIds = $documentsData['requested_course_ids'] ?? [];

        // Create InstructorCourseAuthorization records for all approved courses
        if (!empty($courseIds)) {
            foreach ($courseIds as $courseId) {
                \App\Models\InstructorCourseAuthorization::updateOrCreate(
                    [
                        'instructor_id' => $authorization->instructor_id,
                        'course_id' => $courseId,
                        'acc_id' => $authorization->acc_id,
                    ],
                    [
                        'authorized_at' => now(),
                        'authorized_by' => $user->id,
                        'status' => 'active',
                    ]
                );
            }
        }

        // Send notification to Group Admin to set commission percentage
        $authorization->load(['instructor', 'acc']);
        $instructor = $authorization->instructor;
        $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
        
        $notificationService = new NotificationService();
        $notificationService->notifyAdminInstructorNeedsCommission(
            $authorization->id,
            $instructorName,
            $acc->name,
            $request->authorization_price
        );

        return response()->json([
            'message' => 'Instructor approved successfully. Waiting for Group Admin to set commission percentage.',
            'authorization' => $authorization->fresh(),
            'courses_authorized' => count($courseIds)
        ]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
        $authorization->load(['instructor', 'trainingCenter']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                $notificationService->notifyInstructorAuthorizationRejected(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $instructorName,
                    $request->rejection_reason
                );
            }
        }

        return response()->json(['message' => 'Instructor rejected']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $instructors = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->with('instructor')
            ->get()
            ->pluck('instructor');

        return response()->json(['instructors' => $instructors]);
    }
}

