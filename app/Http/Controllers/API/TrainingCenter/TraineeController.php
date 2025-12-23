<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Trainee;
use App\Models\TrainingCenter;
use App\Models\TrainingClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TraineeController extends Controller
{
    /**
     * Generate storage URL for a file path
     * Ensures the URL includes /app/public in the path to match server structure
     */
    private function getStorageUrl($path)
    {
        $baseUrl = Storage::disk('public')->url('');
        
        // If base URL already includes /app/public, use standard Storage URL
        if (strpos($baseUrl, '/app/public') !== false) {
            return Storage::disk('public')->url($path);
        }
        
        // Otherwise, ensure /app/public is included in the path
        // Remove trailing slash from base URL
        $baseUrl = rtrim($baseUrl, '/');
        
        // Ensure path doesn't start with /app/public (to avoid duplication)
        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'app/public/') === 0) {
            $cleanPath = substr($cleanPath, 11); // Remove 'app/public/' prefix
        }
        
        // Construct URL: baseUrl/app/public/path
        return $baseUrl . '/app/public/' . $cleanPath;
    }

    /**
     * Get all trainees for the training center
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = Trainee::where('training_center_id', $trainingCenter->id)
            ->with('trainingClasses.course', 'trainingClasses.instructor');

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        $trainees = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'trainees' => $trainees->items(),
            'pagination' => [
                'current_page' => $trainees->currentPage(),
                'last_page' => $trainees->lastPage(),
                'per_page' => $trainees->perPage(),
                'total' => $trainees->total(),
            ]
        ]);
    }

    /**
     * Get a specific trainee
     */
    public function show($id)
    {
        $user = request()->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainee = Trainee::where('training_center_id', $trainingCenter->id)
            ->with(['trainingClasses.course', 'trainingClasses.instructor', 'trainingClasses.classModel'])
            ->findOrFail($id);

        return response()->json(['trainee' => $trainee]);
    }

    /**
     * Create a new trainee
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:trainees,email',
            'phone' => 'required|string|max:255',
            'id_number' => 'required|string|unique:trainees,id_number',
            'id_image' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
            'card_image' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
            'enrolled_classes' => 'nullable|array',
            'enrolled_classes.*' => 'exists:training_classes,id',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        // Handle file uploads
        $idImagePath = null;
        $cardImagePath = null;

        try {
            if ($request->hasFile('id_image')) {
                $idImage = $request->file('id_image');
                $idImageName = Str::random(40) . '.' . $idImage->getClientOriginalExtension();
                $idImagePath = $idImage->storeAs(
                    "trainees/{$trainingCenter->id}/id_images",
                    $idImageName,
                    'public'
                );
            }

            if ($request->hasFile('card_image')) {
                $cardImage = $request->file('card_image');
                $cardImageName = Str::random(40) . '.' . $cardImage->getClientOriginalExtension();
                $cardImagePath = $cardImage->storeAs(
                    "trainees/{$trainingCenter->id}/card_images",
                    $cardImageName,
                    'public'
                );
            }

            // Generate URLs for stored files
            $idImageUrl = $idImagePath ? $this->getStorageUrl($idImagePath) : null;
            $cardImageUrl = $cardImagePath ? $this->getStorageUrl($cardImagePath) : null;

            // Create trainee
            $trainee = Trainee::create([
                'training_center_id' => $trainingCenter->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'id_number' => $request->id_number,
                'id_image_url' => $idImageUrl,
                'card_image_url' => $cardImageUrl,
                'status' => $request->status ?? 'active',
            ]);

            // Attach enrolled classes
            if ($request->has('enrolled_classes') && is_array($request->enrolled_classes)) {
                $enrolledClasses = [];
                foreach ($request->enrolled_classes as $classId) {
                    // Verify the class belongs to this training center
                    $trainingClass = TrainingClass::where('training_center_id', $trainingCenter->id)
                        ->find($classId);
                    
                    if ($trainingClass) {
                        $enrolledClasses[$classId] = [
                            'status' => 'enrolled',
                            'enrolled_at' => now(),
                        ];
                    }
                }
                
                if (!empty($enrolledClasses)) {
                    $trainee->trainingClasses()->attach($enrolledClasses);
                    
                    // Update enrolled_count for each class
                    foreach (array_keys($enrolledClasses) as $classId) {
                        $class = TrainingClass::find($classId);
                        if ($class) {
                            $class->increment('enrolled_count');
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Trainee created successfully',
                'trainee' => $trainee->load('trainingClasses')
            ], 201);

        } catch (\Exception $e) {
            // Clean up uploaded files if trainee creation fails
            if ($idImagePath && Storage::disk('public')->exists($idImagePath)) {
                Storage::disk('public')->delete($idImagePath);
            }
            if ($cardImagePath && Storage::disk('public')->exists($cardImagePath)) {
                Storage::disk('public')->delete($cardImagePath);
            }

            return response()->json([
                'message' => 'Failed to create trainee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update trainee
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainee = Trainee::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:trainees,email,' . $id,
            'phone' => 'sometimes|string|max:255',
            'id_number' => 'sometimes|string|unique:trainees,id_number,' . $id,
            'id_image' => 'sometimes|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'card_image' => 'sometimes|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'enrolled_classes' => 'nullable|array',
            'enrolled_classes.*' => 'exists:training_classes,id',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        $updateData = $request->only(['first_name', 'last_name', 'email', 'phone', 'id_number', 'status']);

        // Handle file uploads if provided
        if ($request->hasFile('id_image')) {
            // Delete old file
            if ($trainee->id_image_url) {
                // Extract path from URL - remove the storage URL base
                $storageUrl = Storage::disk('public')->url('');
                $oldPath = str_replace($storageUrl, '', $trainee->id_image_url);
                // Remove leading slash if present
                $oldPath = ltrim($oldPath, '/');
                Storage::disk('public')->delete($oldPath);
            }

            $idImage = $request->file('id_image');
            $idImageName = Str::random(40) . '.' . $idImage->getClientOriginalExtension();
            $idImagePath = $idImage->storeAs(
                "trainees/{$trainingCenter->id}/id_images",
                $idImageName,
                'public'
            );
            $updateData['id_image_url'] = $this->getStorageUrl($idImagePath);
        }

        if ($request->hasFile('card_image')) {
            // Delete old file
            if ($trainee->card_image_url) {
                // Extract path from URL - remove the storage URL base
                $storageUrl = Storage::disk('public')->url('');
                $oldPath = str_replace($storageUrl, '', $trainee->card_image_url);
                // Remove leading slash if present
                $oldPath = ltrim($oldPath, '/');
                Storage::disk('public')->delete($oldPath);
            }

            $cardImage = $request->file('card_image');
            $cardImageName = Str::random(40) . '.' . $cardImage->getClientOriginalExtension();
            $cardImagePath = $cardImage->storeAs(
                "trainees/{$trainingCenter->id}/card_images",
                $cardImageName,
                'public'
            );
            $updateData['card_image_url'] = $this->getStorageUrl($cardImagePath);
        }

        $trainee->update($updateData);

        // Update enrolled classes if provided
        if ($request->has('enrolled_classes')) {
            // Get current enrolled classes
            $currentClasses = $trainee->trainingClasses()->pluck('training_classes.id')->toArray();
            
            // Get new classes
            $newClasses = is_array($request->enrolled_classes) ? $request->enrolled_classes : [];
            
            // Verify all new classes belong to this training center
            $validClasses = TrainingClass::where('training_center_id', $trainingCenter->id)
                ->whereIn('id', $newClasses)
                ->pluck('id')
                ->toArray();

            // Classes to remove
            $classesToRemove = array_diff($currentClasses, $validClasses);
            
            // Classes to add
            $classesToAdd = array_diff($validClasses, $currentClasses);

            // Remove classes
            if (!empty($classesToRemove)) {
                $trainee->trainingClasses()->detach($classesToRemove);
                foreach ($classesToRemove as $classId) {
                    $class = TrainingClass::find($classId);
                    if ($class && $class->enrolled_count > 0) {
                        $class->decrement('enrolled_count');
                    }
                }
            }

            // Add new classes
            if (!empty($classesToAdd)) {
                $enrolledClasses = [];
                foreach ($classesToAdd as $classId) {
                    $enrolledClasses[$classId] = [
                        'status' => 'enrolled',
                        'enrolled_at' => now(),
                    ];
                }
                $trainee->trainingClasses()->attach($enrolledClasses);
                
                foreach ($classesToAdd as $classId) {
                    $class = TrainingClass::find($classId);
                    if ($class) {
                        $class->increment('enrolled_count');
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Trainee updated successfully',
            'trainee' => $trainee->fresh()->load('trainingClasses')
        ], 200);
    }

    /**
     * Delete trainee
     */
    public function destroy($id)
    {
        $user = request()->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainee = Trainee::where('training_center_id', $trainingCenter->id)->findOrFail($id);

        // Delete associated files
        if ($trainee->id_image_url) {
            // Extract path from URL - remove the storage URL base
            $storageUrl = Storage::disk('public')->url('');
            $idImagePath = str_replace($storageUrl, '', $trainee->id_image_url);
            // Remove leading slash if present
            $idImagePath = ltrim($idImagePath, '/');
            Storage::disk('public')->delete($idImagePath);
        }

        if ($trainee->card_image_url) {
            // Extract path from URL - remove the storage URL base
            $storageUrl = Storage::disk('public')->url('');
            $cardImagePath = str_replace($storageUrl, '', $trainee->card_image_url);
            // Remove leading slash if present
            $cardImagePath = ltrim($cardImagePath, '/');
            Storage::disk('public')->delete($cardImagePath);
        }

        // Decrement enrolled_count for enrolled classes
        $enrolledClasses = $trainee->trainingClasses()->pluck('training_classes.id')->toArray();
        foreach ($enrolledClasses as $classId) {
            $class = TrainingClass::find($classId);
            if ($class && $class->enrolled_count > 0) {
                $class->decrement('enrolled_count');
            }
        }

        $trainee->delete();

        return response()->json(['message' => 'Trainee deleted successfully'], 200);
    }
}

