<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class TrainingCenterController extends Controller
{
    public function requests(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $requests = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->with('trainingCenter')
            ->orderBy('request_date', 'desc')
            ->get();

        return response()->json(['requests' => $requests]);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notifications
        $authorization->load(['trainingCenter', 'acc']);
        $notificationService = new NotificationService();
        
        // Notify Training Center
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService->notifyTrainingCenterAuthorized(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $acc->name
                );
            }
        }

        return response()->json(['message' => 'Training center approved successfully']);
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

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
        $authorization->load(['trainingCenter', 'acc']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterAuthorizationRejected(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $acc->name,
                    $request->rejection_reason
                );
            }
        }

        return response()->json(['message' => 'Training center rejected']);
    }

    public function return(Request $request, $id)
    {
        $request->validate([
            'return_comment' => 'required|string',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'returned',
            'return_comment' => $request->return_comment,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Send notification to Training Center
        $authorization->load(['trainingCenter', 'acc']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $notificationService->notifyTrainingCenterAuthorizationReturned(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $acc->name,
                    $request->return_comment
                );
            }
        }

        return response()->json(['message' => 'Request returned successfully']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $trainingCenters = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'approved')
            ->with('trainingCenter')
            ->get()
            ->pluck('trainingCenter');

        return response()->json(['training_centers' => $trainingCenters]);
    }
}

