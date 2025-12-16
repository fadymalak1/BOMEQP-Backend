<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'documents' => 'required|array|min:1',
            'documents.*.type' => 'required|string|in:license,certificate,registration,other',
            'documents.*.file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
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

        // Upload files and create documents array
        $documents = [];
        $documentsData = $request->input('documents', []);
        
        foreach ($documentsData as $index => $documentData) {
            $fileKey = "documents.{$index}.file";
            
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                
                // Validate file type matches document type if needed
                $documentType = $documentData['type'] ?? null;
                
                // Create directory path: authorization/{training_center_id}/{acc_id}/
                $directory = 'authorization/' . $trainingCenter->id . '/' . $acc->id;
                $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                
                // Store file in public storage
                $path = $file->storeAs($directory, $fileName, 'public');
                $url = Storage::disk('public')->url($path);
                
                $documents[] = [
                    'type' => $documentType,
                    'url' => $url,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        if (empty($documents)) {
            return response()->json(['message' => 'No valid documents uploaded'], 422);
        }

        $authorization = TrainingCenterAccAuthorization::create([
            'training_center_id' => $trainingCenter->id,
            'acc_id' => $acc->id,
            'request_date' => now(),
            'status' => 'pending',
            'documents_json' => $documents,
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

