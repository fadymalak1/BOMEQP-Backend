<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\TrainingClass;
use App\Models\ClassCompletion;
use App\Models\User;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $classes = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->with(['course', 'instructor', 'classModel'])
            ->get();

        return response()->json(['classes' => $classes]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'class_id' => 'required|exists:classes,id',
            'instructor_id' => 'required|exists:instructors,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'schedule_json' => 'nullable|array',
            'max_capacity' => 'required|integer|min:1',
            'location' => 'required|in:physical,online',
            'location_details' => 'nullable|string',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::create([
            'training_center_id' => $trainingCenter->id,
            'course_id' => $request->course_id,
            'class_id' => $request->class_id,
            'instructor_id' => $request->instructor_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'schedule_json' => $request->schedule_json ?? $request->schedule,
            'max_capacity' => $request->max_capacity,
            'enrolled_count' => 0,
            'status' => 'scheduled',
            'location' => $request->location,
            'location_details' => $request->location_details,
        ]);

        return response()->json(['class' => $class->load(['course', 'instructor'])], 201);
    }

    public function show($id)
    {
        $class = TrainingClass::with(['course', 'instructor', 'trainingCenter', 'classModel', 'completion'])
            ->findOrFail($id);
        return response()->json(['class' => $class]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'class_id' => 'sometimes|exists:classes,id',
            'instructor_id' => 'sometimes|exists:instructors,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'schedule_json' => 'nullable|array',
            'max_capacity' => 'sometimes|integer|min:1',
            'location' => 'sometimes|in:physical,online',
            'location_details' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
        ]);

        $updateData = $request->only([
            'course_id', 'class_id', 'instructor_id', 'start_date', 'end_date',
            'max_capacity', 'location', 'location_details', 'status'
        ]);

        if ($request->has('schedule_json') || $request->has('schedule')) {
            $updateData['schedule_json'] = $request->schedule_json ?? $request->schedule;
        }

        $class->update($updateData);

        return response()->json(['message' => 'Class updated successfully', 'class' => $class]);
    }

    public function destroy($id)
    {
        $user = request()->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)->findOrFail($id);
        $class->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }

    public function complete(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $class = TrainingClass::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        if ($class->status !== 'completed') {
            $class->update(['status' => 'completed']);

            ClassCompletion::create([
                'training_class_id' => $class->id,
                'completed_date' => now(),
                'completion_rate_percentage' => 100,
                'certificates_generated_count' => 0,
                'marked_by' => $user->id,
            ]);
        }

        return response()->json(['message' => 'Class marked as completed']);
    }
}

