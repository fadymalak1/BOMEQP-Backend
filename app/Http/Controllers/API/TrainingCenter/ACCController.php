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
        try {
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
                return response()->json([
                    'message' => 'Authorization request already exists',
                    'authorization_id' => $existing->id
                ], 400);
            }

            // Get all uploaded files
            $allFiles = $request->allFiles();
            $documents = [];
            
            // Handle files - support both documents[0][file] and documents.0.file format
            if (isset($allFiles['documents']) && is_array($allFiles['documents'])) {
                foreach ($allFiles['documents'] as $index => $fileData) {
                    $file = null;
                    $documentType = null;
                    
                    // Handle nested structure: documents[0][file]
                    if (is_array($fileData) && isset($fileData['file'])) {
                        $file = $fileData['file'];
                        // Get type from request input
                        $documentType = $request->input("documents.{$index}.type");
                    } 
                    // Handle flat structure or direct file
                    elseif ($fileData instanceof \Illuminate\Http\UploadedFile) {
                        $file = $fileData;
                        $documentType = $request->input("documents.{$index}.type");
                    }
                    
                    if ($file && $file->isValid()) {
                        // Validate file
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['file' => $file],
                            [
                                'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                            ]
                        );
                        
                        if ($validator->fails()) {
                            return response()->json([
                                'message' => 'File validation failed',
                                'errors' => $validator->errors()
                            ], 422);
                        }
                        
                        // Validate document type
                        if (!$documentType || !in_array($documentType, ['license', 'certificate', 'registration', 'other'])) {
                            return response()->json([
                                'message' => "Invalid document type for document at index {$index}. Must be one of: license, certificate, registration, other"
                            ], 422);
                        }
                        
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
            }

            // Also try alternative format: documents.0.file
            if (empty($documents)) {
                $documentsInput = $request->input('documents', []);
                foreach ($documentsInput as $index => $docData) {
                    $fileKey = "documents.{$index}.file";
                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);
                        if ($file && $file->isValid()) {
                            $documentType = $docData['type'] ?? null;
                            
                            if (!$documentType || !in_array($documentType, ['license', 'certificate', 'registration', 'other'])) {
                                continue;
                            }
                            
                            $directory = 'authorization/' . $trainingCenter->id . '/' . $acc->id;
                            $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
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
                }
            }

            if (empty($documents)) {
                return response()->json([
                    'message' => 'No valid documents uploaded. Please ensure files are uploaded correctly.',
                    'hint' => 'Use FormData with structure: documents[0][type]=license&documents[0][file]=<file>'
                ], 422);
            }

            $authorization = TrainingCenterAccAuthorization::create([
                'training_center_id' => $trainingCenter->id,
                'acc_id' => $acc->id,
                'request_date' => now(),
                'status' => 'pending',
                'documents_json' => $documents,
            ]);

            return response()->json([
                'message' => 'Authorization request submitted successfully',
                'authorization' => $authorization,
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Authorization request error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Server error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Please contact support'
            ], 500);
        }
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

