<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: "/instructor/profile",
        summary: "Get instructor profile",
        description: "Get the authenticated instructor's profile information including personal details and training center.",
        tags: ["Instructor"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Profile retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "profile", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor not found")
        ]
    )]
    public function show(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)
            ->with(['trainingCenter:id,name,email,phone,country,city'])
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $userAccount = User::where('email', $user->email)->first();

        return response()->json([
            'profile' => [
                'id' => $instructor->id,
                'first_name' => $instructor->first_name,
                'last_name' => $instructor->last_name,
                'full_name' => $instructor->first_name . ' ' . $instructor->last_name,
                'email' => $instructor->email,
                'phone' => $instructor->phone,
                'id_number' => $instructor->id_number,
                'country' => $instructor->country,
                'city' => $instructor->city,
                'cv_url' => $instructor->cv_url,
                'certificates' => $instructor->certificates_json ?? [], // Returns array of objects with name, issue_date, url
                'specializations' => $instructor->specializations ?? [],
                'status' => $instructor->status,
                'training_center' => $instructor->trainingCenter,
                'user' => $userAccount ? [
                    'id' => $userAccount->id,
                    'name' => $userAccount->name,
                    'email' => $userAccount->email,
                    'role' => $userAccount->role,
                    'status' => $userAccount->status,
                ] : null,
            ]
        ]);
    }

    #[OA\Put(
        path: "/instructor/profile",
        summary: "Update instructor profile",
        description: "Update the authenticated instructor's profile information.",
        tags: ["Instructor"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "first_name", type: "string", nullable: true, example: "John", description: "First name of the instructor"),
                        new OA\Property(property: "last_name", type: "string", nullable: true, example: "Doe", description: "Last name of the instructor"),
                        new OA\Property(property: "phone", type: "string", nullable: true, example: "+1234567890", description: "Phone number"),
                        new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt", description: "Country"),
                        new OA\Property(property: "city", type: "string", nullable: true, example: "Cairo", description: "City"),
                        new OA\Property(property: "cv", type: "string", format: "binary", nullable: true, description: "CV file (PDF, max 10MB)"),
                        new OA\Property(
                            property: "certificates", 
                            type: "array", 
                            nullable: true, 
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "name", type: "string", example: "Certificate Name"),
                                    new OA\Property(property: "issue_date", type: "string", format: "date", example: "2024-01-01"),
                                    new OA\Property(property: "certificate_file", type: "string", format: "binary", nullable: true, description: "Certificate PDF file (max 10MB) - upload file instead of providing URL")
                                ]
                            ), 
                            description: "Array of certificate objects. Each certificate can have a certificate_file (PDF) uploaded, or you can provide certificates as JSON with name, issue_date, and optionally url."
                        ),
                        new OA\Property(
                            property: "certificate_files", 
                            type: "array", 
                            nullable: true, 
                            items: new OA\Items(type: "string", format: "binary"),
                            description: "Alternative: Array of certificate PDF files. Files will be matched with certificates array by index."
                        ),
                        new OA\Property(property: "specializations", type: "array", nullable: true, items: new OA\Items(type: "string"), description: "Array of specializations"),
                    ],
                    description: "Note: email and id_number cannot be changed by instructor for security reasons. is_assessor can only be changed by training center."
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Profile updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Profile updated successfully"),
                        new OA\Property(property: "profile", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request)
    {
        // Increase execution time and memory limit for file uploads
        set_time_limit(300); // 5 minutes
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '256M');
        
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        // Check if request is too large before validation
        $contentLength = $request->header('Content-Length');
        if ($contentLength && $contentLength > 12 * 1024 * 1024) { // 12MB
            return response()->json([
                'message' => 'Request size exceeds maximum allowed size of 12MB',
                'error' => 'Request too large',
                'error_code' => 'request_too_large',
                'content_length' => $contentLength
            ], 413);
        }

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'cv' => 'nullable|file|mimes:pdf|max:10240',
            'certificates' => 'nullable|array',
            'certificates.*.name' => 'required_with:certificates|string|max:255',
            'certificates.*.issue_date' => 'required_with:certificates|date',
            'certificates.*.url' => 'nullable|url|max:500',
            'certificates.*.certificate_file' => 'nullable|file|mimes:pdf|max:10240',
            'certificate_files' => 'nullable|array',
            'certificate_files.*' => 'nullable|file|mimes:pdf|max:10240',
            'specializations' => 'nullable|array',
            // Note: email and id_number cannot be changed by instructor for security reasons
            // Note: is_assessor can only be changed by training center
        ]);

        $updateData = [];

        // Only include fields that are actually provided and not empty
        // Use input() to handle both JSON and form data
        $fields = ['first_name', 'last_name', 'phone', 'country', 'city', 'specializations'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // For specializations, check if it's an array and not empty
                if ($field === 'specializations') {
                    if (is_array($value) && !empty($value)) {
                        $updateData[$field] = $value;
                    }
                } else {
                    // Only add if value is not null and not empty string
                    if ($value !== null && $value !== '') {
                        $updateData[$field] = $value;
                    }
                }
            }
        }

        // Handle CV file upload
        if ($request->hasFile('cv')) {
            try {
                $cvFile = $request->file('cv');
                
                // Validate file before processing
                if (!$cvFile->isValid()) {
                    return response()->json([
                        'message' => 'Invalid CV file. Please ensure the file is a valid PDF and try again.',
                        'error' => 'File validation failed',
                        'error_code' => 'invalid_file'
                    ], 422);
                }
                
                // Check file size (10MB = 10485760 bytes)
                $maxSize = 10 * 1024 * 1024; // 10MB in bytes
                $fileSize = $cvFile->getSize();
                
                if ($fileSize > $maxSize) {
                    return response()->json([
                        'message' => 'CV file size exceeds the maximum allowed size of 10MB',
                        'error' => 'File too large',
                        'error_code' => 'file_too_large',
                        'file_size' => $fileSize,
                        'max_size' => $maxSize
                    ], 422);
                }
                
                // Check available disk space
                $freeSpace = disk_free_space(storage_path('app/public'));
                if ($freeSpace !== false && $freeSpace < $fileSize * 2) {
                    return response()->json([
                        'message' => 'Insufficient disk space to upload file',
                        'error' => 'Disk space insufficient',
                        'error_code' => 'insufficient_space'
                    ], 507); // 507 Insufficient Storage
                }
                
                // Delete old CV file if exists
                if ($instructor->cv_url) {
                    try {
                        $urlParts = parse_url($instructor->cv_url);
                        $path = ltrim($urlParts['path'] ?? '', '/');
                        // Try multiple patterns to extract filename
                        $oldFileName = null;
                        if (preg_match('#instructors/cv/(.+)$#', $path, $matches)) {
                            $oldFileName = $matches[1];
                        } elseif (preg_match('#storage/instructors/cv/(.+)$#', $path, $matches)) {
                            $oldFileName = $matches[1];
                        } elseif (preg_match('#api/storage/instructors/cv/(.+)$#', $path, $matches)) {
                            $oldFileName = $matches[1];
                        }
                        
                        if ($oldFileName) {
                            $oldFilePath = 'instructors/cv/' . $oldFileName;
                            if (Storage::disk('public')->exists($oldFilePath)) {
                                Storage::disk('public')->delete($oldFilePath);
                                \Log::info('Deleted old CV file', ['instructor_id' => $instructor->id, 'file' => $oldFilePath]);
                            }
                        }
                    } catch (\Exception $deleteError) {
                        \Log::warning('Failed to delete old CV file', [
                            'instructor_id' => $instructor->id,
                            'error' => $deleteError->getMessage()
                        ]);
                        // Continue with upload even if old file deletion fails
                    }
                }

                // Upload new CV file
                $originalName = $cvFile->getClientOriginalName();
                $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $fileName = time() . '_' . $instructor->id . '_' . $sanitizedName;
                
                // Set execution time limit for large file uploads
                set_time_limit(300); // 5 minutes
                ini_set('max_execution_time', 300);
                
                // Ensure the directory exists with proper permissions
                $directory = 'instructors/cv';
                $directoryPath = storage_path('app/public/' . $directory);
                if (!file_exists($directoryPath)) {
                    if (!mkdir($directoryPath, 0755, true)) {
                        \Log::error('Failed to create CV directory', [
                            'instructor_id' => $instructor->id,
                            'directory' => $directoryPath
                        ]);
                        return response()->json([
                            'message' => 'Failed to create storage directory',
                            'error' => 'Directory creation failed',
                            'error_code' => 'directory_creation_failed'
                        ], 500);
                    }
                }
                
                // Check directory permissions
                if (!is_writable($directoryPath)) {
                    \Log::error('CV directory is not writable', [
                        'instructor_id' => $instructor->id,
                        'directory' => $directoryPath,
                        'permissions' => substr(sprintf('%o', fileperms($directoryPath)), -4)
                    ]);
                    return response()->json([
                        'message' => 'Storage directory is not writable. Please contact administrator.',
                        'error' => 'Directory not writable',
                        'error_code' => 'directory_not_writable'
                    ], 500);
                }
                
                // Store the file with error handling
                try {
                    $cvPath = $cvFile->storeAs($directory, $fileName, 'public');
                    
                    if (!$cvPath) {
                        throw new \Exception('File storage returned false');
                    }
                } catch (\Exception $storeError) {
                    \Log::error('Failed to store CV file', [
                        'instructor_id' => $instructor->id,
                        'error' => $storeError->getMessage(),
                        'trace' => $storeError->getTraceAsString(),
                        'file_name' => $fileName,
                        'directory' => $directory
                    ]);
                    return response()->json([
                        'message' => 'Failed to store CV file. Please check file permissions and try again.',
                        'error' => config('app.debug') ? $storeError->getMessage() : 'File storage failed',
                        'error_code' => 'file_storage_failed'
                    ], 500);
                }
                
                // Verify file was actually stored
                $fullPath = Storage::disk('public')->path($cvPath);
                $fileExists = file_exists($fullPath);
                $fileSize = $fileExists ? filesize($fullPath) : 0;
                
                if ($cvPath && $fileExists && $fileSize > 0) {
                    // Generate URL using the API route
                    $newCvUrl = url('/api/storage/instructors/cv/' . $fileName);
                    $updateData['cv_url'] = $newCvUrl;
                    \Log::info('CV file uploaded successfully', [
                        'instructor_id' => $instructor->id,
                        'original_name' => $originalName,
                        'file_name' => $fileName,
                        'cv_url' => $newCvUrl,
                        'storage_path' => $cvPath,
                        'full_path' => $fullPath,
                        'file_size' => $fileSize,
                        'file_exists' => $fileExists
                    ]);
                } else {
                    \Log::error('Failed to verify CV file after upload', [
                        'instructor_id' => $instructor->id,
                        'file_name' => $fileName,
                        'cv_path' => $cvPath,
                        'file_exists' => $fileExists,
                        'file_size' => $fileSize,
                        'full_path' => $fullPath ?? 'N/A'
                    ]);
                    return response()->json([
                        'message' => 'File was uploaded but could not be verified. Please try again.',
                        'error' => 'File verification failed',
                        'error_code' => 'file_verification_failed'
                    ], 500);
                }
            } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
                \Log::error('CV file upload failed - file too large', [
                    'instructor_id' => $instructor->id,
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'message' => 'CV file size exceeds server limits. Maximum size is 10MB.',
                    'error' => 'File too large for server configuration',
                    'error_code' => 'post_too_large'
                ], 413); // 413 Payload Too Large
            } catch (\Exception $e) {
                \Log::error('Error uploading CV file', [
                    'instructor_id' => $instructor->id,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                    'file_size' => $request->hasFile('cv') ? $request->file('cv')->getSize() : null
                ]);
                return response()->json([
                    'message' => 'Failed to upload CV file. Please ensure the file is a valid PDF and try again.',
                    'error' => config('app.debug') ? $e->getMessage() : 'File upload failed',
                    'error_code' => 'upload_failed',
                    'error_class' => config('app.debug') ? get_class($e) : null
                ], 500);
            }
        }

        // Handle certificates array with file uploads
        if ($request->has('certificates')) {
            $certificates = $request->input('certificates');
            $certificateFiles = $request->file('certificate_files', []);
            
            if (is_array($certificates)) {
                // Validate and format certificates
                $formattedCertificates = [];
                foreach ($certificates as $index => $cert) {
                    if (isset($cert['name']) && isset($cert['issue_date'])) {
                        $certificateUrl = null;
                        
                        // Check if certificate file is uploaded for this index
                        if (isset($certificateFiles[$index]) && $certificateFiles[$index]->isValid()) {
                            try {
                                $certFile = $certificateFiles[$index];
                                $originalName = $certFile->getClientOriginalName();
                                $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                                $fileName = time() . '_' . $instructor->id . '_' . $index . '_' . $sanitizedName;
                                
                                // Ensure the directory exists
                                $directory = 'instructors/certificates';
                                if (!Storage::disk('public')->exists($directory)) {
                                    Storage::disk('public')->makeDirectory($directory, 0755, true);
                                }
                                
                                // Store the file
                                $certPath = $certFile->storeAs($directory, $fileName, 'public');
                                
                                // Verify file was actually stored
                                $fullPath = Storage::disk('public')->path($certPath);
                                $fileExists = file_exists($fullPath);
                                $fileSize = $fileExists ? filesize($fullPath) : 0;
                                
                                if ($certPath && $fileExists && $fileSize > 0) {
                                    // Generate URL using the API route
                                    $certificateUrl = url('/api/storage/instructors/certificates/' . $fileName);
                                    \Log::info('Certificate file uploaded successfully', [
                                        'instructor_id' => $instructor->id,
                                        'index' => $index,
                                        'file_name' => $fileName,
                                        'certificate_url' => $certificateUrl,
                                    ]);
                                } else {
                                    \Log::error('Failed to store certificate file', [
                                        'instructor_id' => $instructor->id,
                                        'index' => $index,
                                        'file_name' => $fileName,
                                    ]);
                                }
                            } catch (\Exception $e) {
                                \Log::error('Error uploading certificate file', [
                                    'instructor_id' => $instructor->id,
                                    'index' => $index,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        } elseif (isset($cert['certificate_file']) && $request->hasFile("certificates.{$index}.certificate_file")) {
                            // Handle nested certificate_file in certificates array
                            try {
                                $certFile = $request->file("certificates.{$index}.certificate_file");
                                if ($certFile && $certFile->isValid()) {
                                    $originalName = $certFile->getClientOriginalName();
                                    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                                    $fileName = time() . '_' . $instructor->id . '_' . $index . '_' . $sanitizedName;
                                    
                                    // Ensure the directory exists
                                    $directory = 'instructors/certificates';
                                    if (!Storage::disk('public')->exists($directory)) {
                                        Storage::disk('public')->makeDirectory($directory, 0755, true);
                                    }
                                    
                                    // Store the file
                                    $certPath = $certFile->storeAs($directory, $fileName, 'public');
                                    
                                    // Verify file was actually stored
                                    $fullPath = Storage::disk('public')->path($certPath);
                                    $fileExists = file_exists($fullPath);
                                    $fileSize = $fileExists ? filesize($fullPath) : 0;
                                    
                                    if ($certPath && $fileExists && $fileSize > 0) {
                                        // Generate URL using the API route
                                        $certificateUrl = url('/api/storage/instructors/certificates/' . $fileName);
                                        \Log::info('Certificate file uploaded successfully (nested)', [
                                            'instructor_id' => $instructor->id,
                                            'index' => $index,
                                            'file_name' => $fileName,
                                            'certificate_url' => $certificateUrl,
                                        ]);
                                    }
                                }
                            } catch (\Exception $e) {
                                \Log::error('Error uploading certificate file (nested)', [
                                    'instructor_id' => $instructor->id,
                                    'index' => $index,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        } elseif (isset($cert['url']) && !empty($cert['url'])) {
                            // Use provided URL if no file uploaded
                            $certificateUrl = $cert['url'];
                        }
                        
                        $formattedCertificates[] = [
                            'name' => $cert['name'],
                            'issue_date' => $cert['issue_date'],
                            'url' => $certificateUrl,
                        ];
                    }
                }
                $updateData['certificates_json'] = $formattedCertificates;
            }
        }

        // Log update data before saving
        \Log::info('Updating instructor profile', [
            'instructor_id' => $instructor->id,
            'update_data' => $updateData,
            'has_cv_url' => isset($updateData['cv_url']),
            'cv_url_value' => $updateData['cv_url'] ?? 'NOT SET',
            'old_cv_url' => $instructor->cv_url
        ]);
        
        // Only update if there's data to update
        if (!empty($updateData)) {
            $instructor->update($updateData);
            // Refresh the model to ensure we have the latest data
            $instructor->refresh();
        }

        // Update user account name if first_name or last_name changed
        if (isset($updateData['first_name']) || isset($updateData['last_name'])) {
            $userAccount = User::where('email', $user->email)->first();
            if ($userAccount) {
                // Use updated instructor data (after refresh)
                $fullName = $instructor->first_name . ' ' . $instructor->last_name;
                $userAccount->update(['name' => $fullName]);
            }
        }

        $instructor->refresh();
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => [
                'id' => $instructor->id,
                'first_name' => $instructor->first_name,
                'last_name' => $instructor->last_name,
                'email' => $instructor->email,
                'phone' => $instructor->phone,
                'id_number' => $instructor->id_number,
                'country' => $instructor->country,
                'city' => $instructor->city,
                'cv_url' => $instructor->cv_url,
                'certificates' => $instructor->certificates_json ?? [], // Array of objects with name, issue_date, url
                'specializations' => $instructor->specializations ?? [],
                'status' => $instructor->status,
                'is_assessor' => $instructor->is_assessor,
                'training_center_id' => $instructor->training_center_id,
                'training_center' => $instructor->trainingCenter,
                'created_at' => $instructor->created_at,
                'updated_at' => $instructor->updated_at,
            ]
        ]);
    }
}

