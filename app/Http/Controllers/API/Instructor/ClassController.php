<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\TrainingClass;
use App\Models\ClassCompletion;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $query = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course', 'trainingCenter']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $classes = $query->orderBy('start_date', 'desc')->get();

        return response()->json(['classes' => $classes]);
    }

    public function show($id)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $class = TrainingClass::where('instructor_id', $instructor->id)
            ->with(['course', 'trainingCenter', 'classModel', 'completion'])
            ->findOrFail($id);

        return response()->json(['class' => $class]);
    }

    public function markComplete(Request $request, $id)
    {
        $request->validate([
            'completion_rate_percentage' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $class = TrainingClass::where('instructor_id', $instructor->id)->findOrFail($id);

        // Check if class end date has passed
        if (now()->lt($class->end_date)) {
            return response()->json(['message' => 'Class end date has not been reached'], 400);
        }

        $class->update(['status' => 'completed']);

        $completion = ClassCompletion::updateOrCreate(
            ['training_class_id' => $class->id],
            [
                'completed_date' => now(),
                'completion_rate_percentage' => $request->completion_rate_percentage,
                'notes' => $request->notes,
                'marked_by' => $user->id,
            ]
        );

        // TODO: Send notification to training center

        return response()->json([
            'message' => 'Class marked as completed',
            'completion' => $completion,
        ]);
    }
}

