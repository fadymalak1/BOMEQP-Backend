<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request)
    {
        $query = ClassModel::with('course');

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $classes = $query->get();
        return response()->json(['classes' => $classes]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255|unique:classes,name',
            'status' => 'required|in:active,inactive',
        ]);

        $class = ClassModel::create([
            'course_id' => $request->course_id,
            'name' => $request->name,
            'status' => $request->status,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['class' => $class], 201);
    }

    public function show($id)
    {
        $class = ClassModel::with('course')->findOrFail($id);
        return response()->json(['class' => $class]);
    }

    public function update(Request $request, $id)
    {
        $class = ClassModel::findOrFail($id);

        $request->validate([
            'course_id' => 'sometimes|exists:courses,id',
            'name' => 'sometimes|string|max:255|unique:classes,name,' . $id,
            'status' => 'sometimes|in:active,inactive',
        ]);

        $class->update($request->only(['course_id', 'name', 'status']));

        return response()->json(['message' => 'Class updated successfully', 'class' => $class]);
    }

    public function destroy($id)
    {
        $class = ClassModel::findOrFail($id);
        $class->delete();

        return response()->json(['message' => 'Class deleted successfully']);
    }
}

