<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\User;
use Illuminate\Http\Request;

class ACCController extends Controller
{
    public function index()
    {
        $accs = ACC::where('status', 'active')->get();
        return response()->json(['accs' => $accs]);
    }

    public function requestAuthorization(Request $request, $id)
    {
        $request->validate([
            'documents_json' => 'required|array',
            'documents_json.*.type' => 'required|string',
            'documents_json.*.url' => 'required|string',
            'additional_info' => 'nullable|string',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $acc = ACC::findOrFail($id);

        // Check if authorization already exists
        $existing = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('acc_id', $acc->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Authorization request already exists'], 400);
        }

        $authorization = TrainingCenterAccAuthorization::create([
            'training_center_id' => $trainingCenter->id,
            'acc_id' => $acc->id,
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

    public function authorizations(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $authorizations = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->with('acc')
            ->orderBy('request_date', 'desc')
            ->get();

        return response()->json(['authorizations' => $authorizations]);
    }
}

