<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
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
}

