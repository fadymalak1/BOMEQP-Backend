<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAccAuthorization;
use App\Models\User;
use Illuminate\Http\Request;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructors = Instructor::where('training_center_id', $trainingCenter->id)->get();
        return response()->json(['instructors' => $instructors]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:instructors,email',
            'phone' => 'required|string',
            'id_number' => 'required|string|unique:instructors,id_number',
            'cv_url' => 'nullable|string',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::create([
            'training_center_id' => $trainingCenter->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'id_number' => $request->id_number,
            'cv_url' => $request->cv_url,
            'certificates_json' => $request->certificates_json ?? $request->certificates,
            'specializations' => $request->specializations,
            'status' => 'pending',
        ]);

        return response()->json(['instructor' => $instructor], 201);
    }

    public function show($id)
    {
        $instructor = Instructor::with('trainingCenter')->findOrFail($id);
        return response()->json(['instructor' => $instructor]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:instructors,email,' . $id,
            'phone' => 'sometimes|string',
            'id_number' => 'sometimes|string|unique:instructors,id_number,' . $id,
            'cv_url' => 'nullable|string',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'email', 'phone', 'id_number',
            'cv_url', 'specializations'
        ]);
        
        if ($request->has('certificates_json') || $request->has('certificates')) {
            $updateData['certificates_json'] = $request->certificates_json ?? $request->certificates;
        }
        
        $instructor->update($updateData);

        return response()->json(['message' => 'Instructor updated successfully', 'instructor' => $instructor]);
    }

    public function requestAuthorization(Request $request, $id)
    {
        $request->validate([
            'acc_id' => 'required|exists:accs,id',
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
            'documents_json' => 'nullable|array',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $instructor = Instructor::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $authorization = InstructorAccAuthorization::create([
            'instructor_id' => $instructor->id,
            'acc_id' => $request->acc_id,
            'training_center_id' => $trainingCenter->id,
            'request_date' => now(),
            'status' => 'pending',
            'documents_json' => $request->documents_json ?? $request->documents,
        ]);

        // TODO: Send notification to ACC

        return response()->json([
            'message' => 'Authorization request submitted successfully',
            'authorization' => $authorization,
        ], 201);
    }

    public function destroy($id)
    {
        $instructor = Instructor::findOrFail($id);
        
        // Check if instructor belongs to the training center
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();
        
        if (!$trainingCenter || $instructor->training_center_id !== $trainingCenter->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $instructor->delete();
        
        return response()->json(['message' => 'Instructor deleted successfully']);
    }
}

