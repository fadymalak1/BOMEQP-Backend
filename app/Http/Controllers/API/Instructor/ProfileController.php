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
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
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
                // Delete old CV file if exists
                if ($instructor->cv_url) {
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
                }

                // Upload new CV file
                $cvFile = $request->file('cv');
                $originalName = $cvFile->getClientOriginalName();
                $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $fileName = time() . '_' . $instructor->training_center_id . '_' . $sanitizedName;
                
                // Ensure the directory exists
                $directory = 'instructors/cv';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                
                // Store the file
                $cvPath = $cvFile->storeAs($directory, $fileName, 'public');
                
                // Verify file was actually stored
                $fullPath = Storage::disk('public')->path($cvPath);
                $fileExists = file_exists($fullPath);
                $fileSize = $fileExists ? filesize($fullPath) : 0;
                
                if ($cvPath && $fileExists && $fileSize > 0) {
                    // Generate URL using the API route (route is /storage/instructors/cv/{filename} in api.php, so it becomes /api/storage/instructors/cv/{filename})
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
                    \Log::error('Failed to store CV file', [
                        'instructor_id' => $instructor->id,
                        'file_name' => $fileName,
                        'cv_path' => $cvPath,
                        'file_exists' => $fileExists,
                        'file_size' => $fileSize,
                        'full_path' => $fullPath ?? 'N/A'
                    ]);
                    return response()->json([
                        'message' => 'Failed to store CV file',
                        'error' => 'File storage failed or file not found after upload'
                    ], 500);
                }
            } catch (\Exception $e) {
                \Log::error('Error uploading CV file', [
                    'instructor_id' => $instructor->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'Failed to upload CV file',
                    'error' => config('app.debug') ? $e->getMessage() : 'File upload failed'
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

