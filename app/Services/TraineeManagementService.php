<?php

namespace App\Services;

use App\Models\Trainee;
use App\Models\TrainingCenter;
use App\Models\TrainingClass;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TraineeManagementService
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Create a new trainee
     *
     * @param Request $request
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function createTrainee(Request $request, TrainingCenter $trainingCenter): array
    {
        try {
            DB::beginTransaction();

            // Handle file uploads
            $idImageUrl = null;
            $cardImageUrl = null;
            $uploadedFiles = [];

            if ($request->hasFile('id_image')) {
                $idResult = $this->uploadTraineeFile(
                    $request->file('id_image'),
                    $trainingCenter->id,
                    'id_images'
                );

                if ($idResult['success']) {
                    $idImageUrl = $idResult['url'];
                    $uploadedFiles[] = $idResult['file_path'];
                } else {
                    throw new \Exception('Failed to upload ID image: ' . ($idResult['error'] ?? 'Unknown error'));
                }
            }

            if ($request->hasFile('card_image')) {
                $cardResult = $this->uploadTraineeFile(
                    $request->file('card_image'),
                    $trainingCenter->id,
                    'card_images'
                );

                if ($cardResult['success']) {
                    $cardImageUrl = $cardResult['url'];
                    $uploadedFiles[] = $cardResult['file_path'];
                } else {
                    // Cleanup ID image if card image fails
                    $this->fileUploadService->cleanupFiles($uploadedFiles);
                    throw new \Exception('Failed to upload card image: ' . ($cardResult['error'] ?? 'Unknown error'));
                }
            }

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

            // Enroll in classes if provided
            if ($request->has('enrolled_classes') && is_array($request->enrolled_classes)) {
                $enrolledClasses = [];
                foreach ($request->enrolled_classes as $classId) {
                    $class = TrainingClass::where('training_center_id', $trainingCenter->id)
                        ->find($classId);
                    
                    if ($class && !$trainee->trainingClasses()->where('training_class_id', $classId)->exists()) {
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

            DB::commit();

            return [
                'success' => true,
                'trainee' => $trainee->load('trainingClasses'),
                'message' => 'Trainee created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Cleanup uploaded files
            if (isset($uploadedFiles) && !empty($uploadedFiles)) {
                $this->fileUploadService->cleanupFiles($uploadedFiles);
            }

            Log::error('Failed to create trainee', [
                'training_center_id' => $trainingCenter->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update trainee
     *
     * @param Request $request
     * @param Trainee $trainee
     * @param TrainingCenter $trainingCenter
     * @return array
     */
    public function updateTrainee(Request $request, Trainee $trainee, TrainingCenter $trainingCenter): array
    {
        // Verify ownership
        if ($trainee->training_center_id !== $trainingCenter->id) {
            return [
                'success' => false,
                'message' => 'Unauthorized',
                'code' => 403
            ];
        }

        try {
            DB::beginTransaction();

            // Collect update data - handle both multipart/form-data and application/x-www-form-urlencoded
            $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'id_number', 'status'];
            
            // Get all request data - Laravel should parse PUT request bodies automatically
            // But sometimes form-urlencoded PUT requests need special handling
            $allRequestData = $request->all();
            
            // If request data is empty but we have a PUT request with form-urlencoded,
            // manually parse the request body
            if (empty($allRequestData) && $request->method() === 'PUT') {
                $contentType = $request->header('Content-Type', '');
                if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                    parse_str($request->getContent(), $parsedData);
                    $allRequestData = $parsedData;
                    // Merge parsed data into request for subsequent access
                    $request->merge($parsedData);
                }
            }
            
            // Filter to only include allowed fields that are actually present in the request
            $updateData = [];
            foreach ($allowedFields as $field) {
                // Check if the field exists in the request data
                // Try multiple methods to ensure we catch the data
                if (array_key_exists($field, $allRequestData)) {
                    $updateData[$field] = $request->input($field);
                } elseif ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            // Log for debugging
            Log::debug('Updating trainee - data collection', [
                'trainee_id' => $trainee->id,
                'update_data' => $updateData,
                'request_all' => $allRequestData,
                'request_keys' => array_keys($allRequestData),
                'request_method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'request_content' => substr($request->getContent(), 0, 500), // First 500 chars for debugging
            ]);

            $uploadedFiles = [];
            $filesToCleanup = [];

            // Handle ID image upload
            if ($request->hasFile('id_image')) {
                // Delete old ID image if exists
                if ($trainee->id_image_url) {
                    $oldPath = $this->extractFilePathFromUrl($trainee->id_image_url);
                    if ($oldPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                        $filesToCleanup[] = $oldPath;
                    }
                }

                $idResult = $this->uploadTraineeFile(
                    $request->file('id_image'),
                    $trainingCenter->id,
                    'id_images'
                );

                if ($idResult['success']) {
                    $updateData['id_image_url'] = $idResult['url'];
                    $uploadedFiles[] = $idResult['file_path'];
                } else {
                    throw new \Exception('Failed to upload ID image: ' . ($idResult['error'] ?? 'Unknown error'));
                }
            }

            // Handle card image upload
            if ($request->hasFile('card_image')) {
                // Delete old card image if exists
                if ($trainee->card_image_url) {
                    $oldPath = $this->extractFilePathFromUrl($trainee->card_image_url);
                    if ($oldPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                        $filesToCleanup[] = $oldPath;
                    }
                }

                $cardResult = $this->uploadTraineeFile(
                    $request->file('card_image'),
                    $trainingCenter->id,
                    'card_images'
                );

                if ($cardResult['success']) {
                    $updateData['card_image_url'] = $cardResult['url'];
                    $uploadedFiles[] = $cardResult['file_path'];
                } else {
                    // Cleanup uploaded files
                    $this->fileUploadService->cleanupFiles($uploadedFiles);
                    throw new \Exception('Failed to upload card image: ' . ($cardResult['error'] ?? 'Unknown error'));
                }
            }

            // Only update if there's data to update
            if (!empty($updateData)) {
                Log::debug('Updating trainee - before update', [
                    'trainee_id' => $trainee->id,
                    'current_data' => [
                        'first_name' => $trainee->first_name,
                        'last_name' => $trainee->last_name,
                        'email' => $trainee->email,
                        'phone' => $trainee->phone,
                        'id_number' => $trainee->id_number,
                        'status' => $trainee->status,
                    ],
                    'update_data' => $updateData,
                ]);
                
                // Update the trainee
                $updated = $trainee->update($updateData);
                
                Log::debug('Updating trainee - after update', [
                    'trainee_id' => $trainee->id,
                    'update_result' => $updated,
                    'updated_data' => $trainee->getChanges(),
                ]);
                
                // Refresh the model to get updated attributes from database
                $trainee->refresh();
            } else {
                Log::warning('Update trainee called with no data', [
                    'trainee_id' => $trainee->id,
                    'request_all' => $request->all(),
                ]);
            }

            // Cleanup old files after successful update
            foreach ($filesToCleanup as $filePath) {
                try {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($filePath);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old file', [
                        'file_path' => $filePath,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Handle class enrollment updates
            if ($request->has('enrolled_classes')) {
                // Get current enrolled classes
                $currentClasses = $trainee->trainingClasses()->pluck('training_classes.id')->toArray();
                
                // Get new classes - handle both array and string formats
                $enrolledClassesInput = $request->input('enrolled_classes');
                
                // Convert to array if needed
                if (!is_array($enrolledClassesInput)) {
                    if (is_string($enrolledClassesInput) && !empty($enrolledClassesInput)) {
                        // Single string value, convert to array
                        $enrolledClassesInput = [(int)$enrolledClassesInput];
                    } elseif (is_numeric($enrolledClassesInput)) {
                        // Single numeric value
                        $enrolledClassesInput = [(int)$enrolledClassesInput];
                    } else {
                        $enrolledClassesInput = [];
                    }
                } else {
                    // Ensure all values are integers
                    $enrolledClassesInput = array_map('intval', $enrolledClassesInput);
                }
                
                // Remove duplicates
                $newClasses = array_unique($enrolledClassesInput);
                
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

            DB::commit();

            return [
                'success' => true,
                'trainee' => $trainee->fresh()->load('trainingClasses'),
                'message' => 'Trainee updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup uploaded files
            if (isset($uploadedFiles) && !empty($uploadedFiles)) {
                $this->fileUploadService->cleanupFiles($uploadedFiles);
            }

            Log::error('Failed to update trainee', [
                'trainee_id' => $trainee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Upload trainee file (ID image or card image)
     *
     * @param UploadedFile $file
     * @param int $trainingCenterId
     * @param string $subdirectory 'id_images' or 'card_images'
     * @return array
     */
    protected function uploadTraineeFile(UploadedFile $file, int $trainingCenterId, string $subdirectory): array
    {
        try {
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs(
                "trainees/{$trainingCenterId}/{$subdirectory}",
                $fileName,
                'public'
            );

            $fileUrl = Storage::disk('public')->url($filePath);

            return [
                'success' => true,
                'url' => $fileUrl,
                'file_path' => $filePath
            ];
        } catch (\Exception $e) {
            Log::error('Failed to upload trainee file', [
                'training_center_id' => $trainingCenterId,
                'subdirectory' => $subdirectory,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract file path from storage URL
     *
     * @param string $url
     * @return string|null
     */
    protected function extractFilePathFromUrl(string $url): ?string
    {
        try {
            // Remove base URL
            $baseUrl = Storage::disk('public')->url('');
            $path = str_replace($baseUrl, '', $url);
            $path = ltrim($path, '/');

            // Handle /app/public prefix
            if (strpos($path, 'app/public/') === 0) {
                $path = substr($path, 11);
            }

            return $path;
        } catch (\Exception $e) {
            Log::warning('Failed to extract file path from URL', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

