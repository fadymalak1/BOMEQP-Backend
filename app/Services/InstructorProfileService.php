<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstructorProfileService
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Get instructor profile with formatted data
     *
     * @param Instructor $instructor
     * @return array
     */
    public function getProfile(Instructor $instructor): array
    {
        $userAccount = User::where('email', $instructor->email)->first();

        return [
            'id' => $instructor->id,
            'first_name' => $instructor->first_name,
            'last_name' => $instructor->last_name,
            'full_name' => $instructor->first_name . ' ' . $instructor->last_name,
            'email' => $instructor->email,
            'phone' => $instructor->phone,
            'date_of_birth' => $instructor->date_of_birth,
            'id_number' => $instructor->id_number,
            'country' => $instructor->country,
            'city' => $instructor->city,
            'cv_url' => $instructor->cv_url,
            'passport_image_url' => $instructor->passport_image_url,
            'photo_url' => $instructor->photo_url,
            'certificates' => $instructor->certificates_json ?? [],
            'specializations' => $instructor->specializations ?? [],
            'languages' => $instructor->specializations ?? [], // Alias for backward compatibility
            'is_assessor' => $instructor->is_assessor,
            'status' => $instructor->status,
            'training_center' => $instructor->trainingCenter,
            'user' => $userAccount ? [
                'id' => $userAccount->id,
                'name' => $userAccount->name,
                'email' => $userAccount->email,
                'role' => $userAccount->role,
                'status' => $userAccount->status,
            ] : null,
        ];
    }

    /**
     * Update instructor profile
     *
     * @param Request $request
     * @param Instructor $instructor
     * @param User $user
     * @return array
     * @throws \Exception
     */
    public function updateProfile(Request $request, Instructor $instructor, User $user): array
    {
        $updateData = [];
        $uploadedFiles = [];

        try {
            DB::beginTransaction();

            // Enhanced data collection for POST (multipart/form-data) and PUT (form-urlencoded)
            $allRequestData = $request->all();
            $requestMethod = $request->method();
            $contentType = $request->header('Content-Type', '');
            
            // Handle PUT/PATCH requests with form-urlencoded (Laravel limitation)
            if (in_array($requestMethod, ['PUT', 'PATCH']) && 
                str_contains($contentType, 'application/x-www-form-urlencoded') && 
                empty($allRequestData)) {
                parse_str($request->getContent(), $parsedData);
                $allRequestData = $parsedData;
                $request->merge($parsedData);
            }

            // Process basic text fields
            $textFields = ['first_name', 'last_name', 'phone', 'date_of_birth', 'country', 'city', 'id_number'];
            foreach ($textFields as $field) {
                if ($request->has($field) || array_key_exists($field, $allRequestData)) {
                    $value = $request->input($field);
                    if ($value !== null && $value !== '') {
                        $updateData[$field] = $value;
                    }
                }
            }

            // Handle CV file upload
            if ($request->hasFile('cv')) {
                $cvResult = $this->uploadCV($request, $instructor);
                if (!$cvResult['success']) {
                    throw new \Exception($cvResult['error'] ?? 'CV upload failed');
                }
                $updateData['cv_url'] = $cvResult['url'];
                $uploadedFiles[] = $cvResult['file_path'];
            }

            // Handle Passport file upload
            if ($request->hasFile('passport')) {
                $passportResult = $this->uploadPassport($request, $instructor);
                if (!$passportResult['success']) {
                    throw new \Exception($passportResult['error'] ?? 'Passport upload failed');
                }
                $updateData['passport_image_url'] = $passportResult['url'];
                $uploadedFiles[] = $passportResult['file_path'];
            }

            // Handle profile image upload
            if ($request->hasFile('photo')) {
                $photoResult = $this->uploadPhoto($request, $instructor);
                if (!$photoResult['success']) {
                    throw new \Exception($photoResult['error'] ?? 'Profile image upload failed');
                }
                $updateData['photo_url'] = $photoResult['url'];
                $uploadedFiles[] = $photoResult['file_path'];
            }

            // Handle certificates
            if ($request->has('certificates')) {
                $certificatesResult = $this->handleCertificates($request, $instructor);
                if (isset($certificatesResult['certificates'])) {
                    $updateData['certificates_json'] = $certificatesResult['certificates'];
                }
                $uploadedFiles = array_merge($uploadedFiles, $certificatesResult['uploaded_files'] ?? []);
            }

            // Handle specializations/languages
            if ($request->has('specializations') || $request->has('languages')) {
                $specializations = $request->input('languages') ?? $request->input('specializations');
                if (is_array($specializations)) {
                    $updateData['specializations'] = array_filter($specializations);
                }
            }

            // Update instructor if there are changes
            if (!empty($updateData)) {
                $instructor->update($updateData);
                $instructor->refresh();

                // Update user account name if name changed
                if (isset($updateData['first_name']) || isset($updateData['last_name'])) {
                    $fullName = ($updateData['first_name'] ?? $instructor->first_name) . ' ' . 
                                ($updateData['last_name'] ?? $instructor->last_name);
                    $userAccount = User::where('email', $user->email)->lockForUpdate()->first();
                    if ($userAccount) {
                        $userAccount->update(['name' => trim($fullName)]);
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Profile updated successfully',
                'profile' => $this->getProfile($instructor)
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup uploaded files
            $this->fileUploadService->cleanupFiles($uploadedFiles);

            Log::error('Instructor profile update failed', [
                'instructor_id' => $instructor->id ?? null,
                'user_email' => $user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Upload CV file
     *
     * @param Request $request
     * @param Instructor $instructor
     * @return array
     */
    private function uploadCV(Request $request, Instructor $instructor): array
    {
        try {
            $cvFile = $request->file('cv');

            // Validate file
            $validation = $this->fileUploadService->validateFile($cvFile, 10, ['application/pdf']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['message']
                ];
            }

            // Check available disk space
            $fileSize = $cvFile->getSize();
            $freeSpace = disk_free_space(storage_path('app/public'));
            if ($freeSpace !== false && $freeSpace < $fileSize * 2) {
                return [
                    'success' => false,
                    'error' => 'Insufficient disk space to upload file'
                ];
            }

            // Delete old CV if exists
            if ($instructor->cv_url) {
                $this->fileUploadService->deleteOldFile($instructor->cv_url, 'instructor', $instructor->id, 'cv');
            }

            // Upload new CV
            $originalName = $cvFile->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $fileName = time() . '_' . $instructor->id . '_' . $sanitizedName;

            // Ensure directory exists
            $directory = 'instructors/cv';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store file
            $cvPath = $cvFile->storeAs($directory, $fileName, 'public');

            if (!$cvPath || !Storage::disk('public')->exists($cvPath)) {
                throw new \Exception('Failed to store CV file');
            }

            // Generate URL using the API route
            $cvUrl = url('/api/storage/instructors/cv/' . $fileName);

            Log::info('CV file uploaded successfully', [
                'instructor_id' => $instructor->id,
                'file_name' => $fileName,
                'cv_url' => $cvUrl,
            ]);

            return [
                'success' => true,
                'url' => $cvUrl,
                'file_path' => $cvPath
            ];

        } catch (\Exception $e) {
            Log::error('Error uploading CV file', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload passport file
     *
     * @param Request $request
     * @param Instructor $instructor
     * @return array
     */
    private function uploadPassport(Request $request, Instructor $instructor): array
    {
        try {
            $passportFile = $request->file('passport');

            // Validate file (JPEG, PNG, PDF, max 10MB)
            $validation = $this->fileUploadService->validateFile($passportFile, 10, [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png'
            ]);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['message']
                ];
            }

            // Check available disk space
            $fileSize = $passportFile->getSize();
            $freeSpace = disk_free_space(storage_path('app/public'));
            if ($freeSpace !== false && $freeSpace < $fileSize * 2) {
                return [
                    'success' => false,
                    'error' => 'Insufficient disk space to upload file'
                ];
            }

            // Delete old passport if exists
            if ($instructor->passport_image_url) {
                $this->fileUploadService->deleteOldFile($instructor->passport_image_url, 'instructor', $instructor->id, 'passport');
            }

            // Upload new passport
            $originalName = $passportFile->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $fileName = time() . '_' . $instructor->id . '_' . $sanitizedName;

            // Ensure directory exists
            $directory = 'instructors/passport';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store file
            $passportPath = $passportFile->storeAs($directory, $fileName, 'public');

            if (!$passportPath || !Storage::disk('public')->exists($passportPath)) {
                throw new \Exception('Failed to store passport file');
            }

            // Generate URL using the API route
            $passportUrl = url('/api/storage/instructors/passport/' . $fileName);

            Log::info('Passport file uploaded successfully', [
                'instructor_id' => $instructor->id,
                'file_name' => $fileName,
                'passport_url' => $passportUrl,
            ]);

            return [
                'success' => true,
                'url' => $passportUrl,
                'file_path' => $passportPath
            ];

        } catch (\Exception $e) {
            Log::error('Error uploading passport file', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload profile image file
     *
     * @param Request $request
     * @param Instructor $instructor
     * @return array
     */
    private function uploadPhoto(Request $request, Instructor $instructor): array
    {
        try {
            $photoFile = $request->file('photo');

            // Validate file (image formats: jpg, jpeg, png, max 5MB)
            $validation = $this->fileUploadService->validateFile($photoFile, 5, ['image/jpeg', 'image/jpg', 'image/png']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['message']
                ];
            }

            // Check available disk space
            $fileSize = $photoFile->getSize();
            $freeSpace = disk_free_space(storage_path('app/public'));
            if ($freeSpace !== false && $freeSpace < $fileSize * 2) {
                return [
                    'success' => false,
                    'error' => 'Insufficient disk space to upload file'
                ];
            }

            // Delete old photo if exists
            if ($instructor->photo_url) {
                try {
                    // Extract filename from URL (format: /api/storage/instructors/photo/{filename})
                    $urlParts = parse_url($instructor->photo_url);
                    $path = ltrim($urlParts['path'] ?? '', '/');
                    if (preg_match('#api/storage/instructors/photo/(.+)$#', $path, $matches)) {
                        $oldFileName = $matches[1];
                        $oldFilePath = 'instructors/photo/' . $oldFileName;
                        if (Storage::disk('public')->exists($oldFilePath)) {
                            Storage::disk('public')->delete($oldFilePath);
                            Log::info('Deleted old instructor photo', [
                                'instructor_id' => $instructor->id,
                                'file_path' => $oldFilePath
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old instructor photo', [
                        'instructor_id' => $instructor->id,
                        'photo_url' => $instructor->photo_url,
                        'error' => $e->getMessage()
                    ]);
                    // Don't throw - continue with upload even if deletion fails
                }
            }

            // Upload new photo
            $originalName = $photoFile->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $fileName = time() . '_' . $instructor->id . '_' . $sanitizedName;

            // Ensure directory exists
            $directory = 'instructors/photo';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store file
            $photoPath = $photoFile->storeAs($directory, $fileName, 'public');

            if (!$photoPath || !Storage::disk('public')->exists($photoPath)) {
                throw new \Exception('Failed to store profile image file');
            }

            // Generate URL using the API route
            $photoUrl = url('/api/storage/instructors/photo/' . $fileName);

            Log::info('Profile image uploaded successfully', [
                'instructor_id' => $instructor->id,
                'file_name' => $fileName,
                'photo_url' => $photoUrl,
            ]);

            return [
                'success' => true,
                'url' => $photoUrl,
                'file_path' => $photoPath
            ];

        } catch (\Exception $e) {
            Log::error('Error uploading profile image', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle certificates with file uploads
     * Merges new certificates with existing ones
     *
     * @param Request $request
     * @param Instructor $instructor
     * @return array
     */
    private function handleCertificates(Request $request, Instructor $instructor): array
    {
        $newCertificates = $request->input('certificates', []);
        $certificateFiles = $request->file('certificate_files', []);
        $uploadedFiles = [];
        $processedCertificates = [];

        // Start with existing certificates from the database
        $existingCertificates = $instructor->certificates_json ?? [];
        
        // Process new certificates from the request
        foreach ($newCertificates as $index => $cert) {
            if (!is_array($cert)) {
                continue;
            }

            $certificateData = [
                'name' => $cert['name'] ?? '',
                'issuer' => $cert['issuer'] ?? '',
                'issue_date' => $cert['issue_date'] ?? null,
                'expiry' => $cert['expiry'] ?? null,
            ];

            // Handle file upload if provided
            $fileKey = "certificate_files.{$index}";
            if (isset($certificateFiles[$index])) {
                $file = $certificateFiles[$index];
            } elseif ($request->hasFile("certificates.{$index}.certificate_file")) {
                $file = $request->file("certificates.{$index}.certificate_file");
            } else {
                $file = null;
            }

            if ($file && $file->isValid()) {
                // Upload certificate file
                $uploadResult = $this->fileUploadService->uploadDocument(
                    $file,
                    $instructor->id,
                    'instructor',
                    'certificate'
                );

                if ($uploadResult['success']) {
                    $certificateData['url'] = $uploadResult['url'];
                    $uploadedFiles[] = $uploadResult['file_path'];
                } else {
                    Log::warning('Failed to upload certificate file', [
                        'instructor_id' => $instructor->id,
                        'index' => $index,
                        'error' => $uploadResult['error']
                    ]);
                }
            } elseif (isset($cert['url'])) {
                // Keep existing URL if no new file uploaded
                $certificateData['url'] = $cert['url'];
            }

            $processedCertificates[] = $certificateData;
        }

        // Merge new certificates with existing ones
        $allCertificates = array_merge($existingCertificates, $processedCertificates);

        return [
            'certificates' => $allCertificates,
            'uploaded_files' => $uploadedFiles
        ];
    }
}

