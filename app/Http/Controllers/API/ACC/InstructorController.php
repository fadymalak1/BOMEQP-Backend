<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\InstructorAccAuthorization;
use App\Models\Instructor;
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
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $authorization = InstructorAccAuthorization::where('acc_id', $acc->id)
            ->findOrFail($id);

        $authorization->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // TODO: Notify Group admin to set commission percentage
        // TODO: Send notification to instructor

        return response()->json(['message' => 'Instructor approved successfully']);
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

        // TODO: Send notification to instructor

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

