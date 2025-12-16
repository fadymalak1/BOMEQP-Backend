<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCMaterial;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $query = ACCMaterial::where('acc_id', $acc->id);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('material_type')) {
            $query->where('material_type', $request->material_type);
        }

        $materials = $query->get();
        return response()->json(['materials' => $materials]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'material_type' => 'required|in:pdf,video,presentation,package',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'file_url' => 'required|string',
            'preview_url' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $material = ACCMaterial::create([
            'acc_id' => $acc->id,
            'course_id' => $request->course_id,
            'material_type' => $request->material_type,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'file_url' => $request->file_url,
            'preview_url' => $request->preview_url,
            'status' => $request->status,
        ]);

        return response()->json(['material' => $material], 201);
    }

    public function show($id)
    {
        $material = ACCMaterial::with('course')->findOrFail($id);
        return response()->json(['material' => $material]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $material = ACCMaterial::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'material_type' => 'sometimes|in:pdf,video,presentation,package',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'file_url' => 'sometimes|string',
            'preview_url' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $material->update($request->only([
            'course_id', 'material_type', 'name', 'description',
            'price', 'file_url', 'preview_url', 'status'
        ]));

        return response()->json(['message' => 'Material updated successfully', 'material' => $material]);
    }

    public function destroy($id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $material = ACCMaterial::where('acc_id', $acc->id)->findOrFail($id);
        $material->delete();

        return response()->json(['message' => 'Material deleted successfully']);
    }
}

