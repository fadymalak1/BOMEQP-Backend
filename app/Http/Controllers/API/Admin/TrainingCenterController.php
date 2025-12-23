<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingCenter;
use Illuminate\Http\Request;

class TrainingCenterController extends Controller
{
    /**
     * Get all training centers
     */
    public function index(Request $request)
    {
        $query = TrainingCenter::with(['wallet', 'instructors']);

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $trainingCenters = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'training_centers' => $trainingCenters->items(),
            'pagination' => [
                'current_page' => $trainingCenters->currentPage(),
                'last_page' => $trainingCenters->lastPage(),
                'per_page' => $trainingCenters->perPage(),
                'total' => $trainingCenters->total(),
            ]
        ]);
    }

    /**
     * Get a specific training center
     */
    public function show($id)
    {
        $trainingCenter = TrainingCenter::with([
            'wallet',
            'instructors',
            'authorizations.acc',
            'certificates',
            'trainingClasses'
        ])->findOrFail($id);

        return response()->json(['training_center' => $trainingCenter]);
    }

    /**
     * Update training center data
     */
    public function update(Request $request, $id)
    {
        $trainingCenter = TrainingCenter::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:255|unique:training_centers,registration_number,' . $id,
            'country' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:training_centers,email,' . $id,
            'website' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:255',
            'referred_by_group' => 'sometimes|boolean',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
        ]);

        $trainingCenter->update($request->only([
            'name',
            'legal_name',
            'registration_number',
            'country',
            'city',
            'address',
            'phone',
            'email',
            'website',
            'logo_url',
            'referred_by_group',
            'status',
        ]));

        return response()->json([
            'message' => 'Training center updated successfully',
            'training_center' => $trainingCenter->fresh()
        ], 200);
    }
}

