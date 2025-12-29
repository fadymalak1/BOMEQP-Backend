<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class InstructorController extends Controller
{
    /**
     * Get all instructors in the system
     */
    public function index(Request $request)
    {
        $query = Instructor::with(['trainingCenter', 'authorizations', 'courseAuthorizations']);

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('training_center_id')) {
            $query->where('training_center_id', $request->training_center_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $instructors = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'instructors' => $instructors->items(),
            'pagination' => [
                'current_page' => $instructors->currentPage(),
                'last_page' => $instructors->lastPage(),
                'per_page' => $instructors->perPage(),
                'total' => $instructors->total(),
            ]
        ]);
    }

    /**
     * Get a specific instructor with full details
     */
    public function show($id)
    {
        $instructor = Instructor::with([
            'trainingCenter',
            'authorizations.acc',
            'courseAuthorizations.course',
            'trainingClasses.course',
            'certificates'
        ])->findOrFail($id);

        return response()->json(['instructor' => $instructor]);
    }

    /**
     * Update instructor data
     */
    public function update(Request $request, $id)
    {
        $instructor = Instructor::findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:instructors,email,' . $id,
            'phone' => 'sometimes|string|max:255',
            'id_number' => 'sometimes|string|max:255|unique:instructors,id_number,' . $id,
            'cv_url' => 'nullable|string|max:255',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
            'is_assessor' => 'nullable|boolean',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
        ]);

        $updateData = $request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'id_number',
            'cv_url',
            'certificates_json',
            'specializations',
            'status',
        ]);
        
        // Handle boolean conversion for is_assessor
        if ($request->has('is_assessor')) {
            $updateData['is_assessor'] = $request->boolean('is_assessor');
        }
        
        $instructor->update($updateData);

        return response()->json([
            'message' => 'Instructor updated successfully',
            'instructor' => $instructor->fresh()->load(['trainingCenter', 'authorizations', 'courseAuthorizations'])
        ], 200);
    }

    /**
     * Set commission percentage for instructor authorization
     * This is called after ACC Admin approves and sets authorization price
     */
    public function setInstructorCommission(Request $request, $id)
    {
        $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $authorization = InstructorAccAuthorization::findOrFail($id);

        // Verify authorization is approved by ACC and waiting for commission
        if ($authorization->status !== 'approved' || $authorization->group_admin_status !== 'pending') {
            return response()->json([
                'message' => 'Authorization must be approved by ACC Admin first and waiting for commission setting'
            ], 400);
        }

        $authorization->update([
            'commission_percentage' => $request->commission_percentage,
            'group_admin_status' => 'commission_set',
            'group_commission_set_by' => $request->user()->id,
            'group_commission_set_at' => now(),
        ]);

        // Send notification to Training Center to complete payment
        $authorization->load(['instructor', 'trainingCenter']);
        $trainingCenter = $authorization->trainingCenter;
        if ($trainingCenter) {
            $trainingCenterUser = \App\Models\User::where('email', $trainingCenter->email)->first();
            if ($trainingCenterUser) {
                $notificationService = new NotificationService();
                $instructor = $authorization->instructor;
                $instructorName = $instructor->first_name . ' ' . $instructor->last_name;
                $notificationService->notifyInstructorAuthorized(
                    $trainingCenterUser->id,
                    $authorization->id,
                    $instructorName,
                    $authorization->acc->name
                );
            }
        }

        return response()->json([
            'message' => 'Commission percentage set successfully. Training Center can now complete payment.',
            'authorization' => $authorization->fresh()->load(['instructor', 'acc', 'trainingCenter'])
        ], 200);
    }

    /**
     * Get instructor authorization requests waiting for commission setting
     */
    public function pendingCommissionRequests(Request $request)
    {
        $authorizations = InstructorAccAuthorization::where('status', 'approved')
            ->where('group_admin_status', 'pending')
            ->whereNotNull('authorization_price')
            ->with(['instructor', 'acc', 'trainingCenter'])
            ->orderBy('reviewed_at', 'desc')
            ->get();

        return response()->json([
            'authorizations' => $authorizations,
            'total' => $authorizations->count()
        ], 200);
    }
}

