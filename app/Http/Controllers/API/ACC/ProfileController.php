<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCDocument;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    #[OA\Get(
        path: "/acc/profile",
        summary: "Get ACC profile",
        description: "Get the authenticated ACC's profile information.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Profile retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "profile",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "ABC Accreditation Body"),
                                new OA\Property(property: "legal_name", type: "string", example: "ABC Accreditation Body LLC"),
                                new OA\Property(property: "registration_number", type: "string", example: "REG123456"),
                                new OA\Property(property: "email", type: "string", example: "info@example.com"),
                                new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                                new OA\Property(property: "country", type: "string", example: "Egypt"),
                                new OA\Property(property: "address", type: "string", example: "123 Main St"),
                                new OA\Property(
                                    property: "mailing_address",
                                    type: "object",
                                    description: "Mailing address information",
                                    properties: [
                                        new OA\Property(property: "street", type: "string", nullable: true, example: "123 Main Street"),
                                        new OA\Property(property: "city", type: "string", nullable: true, example: "Cairo"),
                                        new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt"),
                                        new OA\Property(property: "postal_code", type: "string", nullable: true, example: "12345")
                                    ]
                                ),
                                new OA\Property(
                                    property: "physical_address",
                                    type: "object",
                                    description: "Physical address information",
                                    properties: [
                                        new OA\Property(property: "street", type: "string", nullable: true, example: "456 Business Avenue"),
                                        new OA\Property(property: "city", type: "string", nullable: true, example: "Cairo"),
                                        new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt"),
                                        new OA\Property(property: "postal_code", type: "string", nullable: true, example: "12345")
                                    ]
                                ),
                                new OA\Property(property: "website", type: "string", nullable: true, example: "https://example.com"),
                                new OA\Property(property: "logo_url", type: "string", nullable: true),
                                new OA\Property(property: "status", type: "string", example: "active"),
                                new OA\Property(property: "commission_percentage", type: "number", format: "float", example: 10.00),
                                new OA\Property(property: "stripe_account_id", type: "string", nullable: true),
                                new OA\Property(property: "stripe_account_configured", type: "boolean", example: true),
                                new OA\Property(
                                    property: "documents",
                                    type: "array",
                                    description: "List of ACC documents",
                                    items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "document_type", type: "string", enum: ["license", "registration", "certificate", "other"], example: "license"),
                                            new OA\Property(property: "document_url", type: "string", example: "https://example.com/storage/accs/1/documents/file.pdf"),
                                            new OA\Property(property: "uploaded_at", type: "string", format: "date-time"),
                                            new OA\Property(property: "verified", type: "boolean", example: false),
                                            new OA\Property(property: "verified_by", type: "object", nullable: true),
                                            new OA\Property(property: "verified_at", type: "string", format: "date-time", nullable: true),
                                            new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                            new OA\Property(property: "updated_at", type: "string", format: "date-time")
                                        ]
                                    )
                                ),
                                new OA\Property(property: "user", type: "object", nullable: true),
                                new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                new OA\Property(property: "updated_at", type: "string", format: "date-time")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function show(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->with('documents.verifiedBy')->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        return response()->json([
            'profile' => $this->formatAccProfile($acc)
        ]);
    }

    #[OA\Put(
        path: "/acc/profile",
        summary: "Update ACC profile",
        description: "Update the authenticated ACC's profile information including Stripe account ID.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: [
                new OA\MediaType(
                    mediaType: "application/json",
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: "name", type: "string", nullable: true, example: "ABC Accreditation Body"),
                            new OA\Property(property: "legal_name", type: "string", nullable: true, example: "ABC Accreditation Body LLC"),
                        new OA\Property(property: "phone", type: "string", nullable: true, example: "+1234567890"),
                        new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt"),
                        new OA\Property(property: "address", type: "string", nullable: true, example: "123 Main St"),
                        new OA\Property(property: "mailing_street", type: "string", nullable: true, example: "123 Main Street"),
                        new OA\Property(property: "mailing_city", type: "string", nullable: true, example: "Cairo"),
                        new OA\Property(property: "mailing_country", type: "string", nullable: true, example: "Egypt"),
                        new OA\Property(property: "mailing_postal_code", type: "string", nullable: true, example: "12345"),
                        new OA\Property(property: "physical_street", type: "string", nullable: true, example: "456 Business Avenue"),
                        new OA\Property(property: "physical_city", type: "string", nullable: true, example: "Cairo"),
                        new OA\Property(property: "physical_country", type: "string", nullable: true, example: "Egypt"),
                        new OA\Property(property: "physical_postal_code", type: "string", nullable: true, example: "12345"),
                        new OA\Property(property: "website", type: "string", nullable: true, example: "https://example.com"),
                        new OA\Property(property: "logo_url", type: "string", nullable: true, example: "https://example.com/logo.png", description: "Logo URL (optional if logo file is uploaded)"),
                        new OA\Property(property: "logo", type: "string", format: "binary", nullable: true, description: "Logo file to upload (image file: jpg, jpeg, png, max 5MB)"),
                        new OA\Property(property: "stripe_account_id", type: "string", nullable: true, example: "acct_xxxxxxxxxxxxx", description: "Stripe Connect account ID (starts with 'acct_')"),
                            new OA\Property(
                                property: "documents",
                                type: "array",
                                nullable: true,
                                description: "Array of documents to upload or update",
                                items: new OA\Items(
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", nullable: true, description: "Document ID for update, omit for new document"),
                                        new OA\Property(property: "document_type", type: "string", enum: ["license", "registration", "certificate", "other"], description: "Type of document"),
                                        new OA\Property(property: "file", type: "string", format: "binary", nullable: true, description: "File to upload (multipart/form-data)")
                                    ]
                                )
                            )
                        ]
                    )
                ),
                new OA\MediaType(
                    mediaType: "multipart/form-data",
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: "name", type: "string", nullable: true),
                            new OA\Property(property: "legal_name", type: "string", nullable: true),
                            new OA\Property(property: "phone", type: "string", nullable: true),
                            new OA\Property(property: "country", type: "string", nullable: true),
                            new OA\Property(property: "address", type: "string", nullable: true),
                            new OA\Property(property: "mailing_street", type: "string", nullable: true),
                            new OA\Property(property: "mailing_city", type: "string", nullable: true),
                            new OA\Property(property: "mailing_country", type: "string", nullable: true),
                            new OA\Property(property: "mailing_postal_code", type: "string", nullable: true),
                            new OA\Property(property: "physical_street", type: "string", nullable: true),
                            new OA\Property(property: "physical_city", type: "string", nullable: true),
                            new OA\Property(property: "physical_country", type: "string", nullable: true),
                            new OA\Property(property: "physical_postal_code", type: "string", nullable: true),
                            new OA\Property(property: "website", type: "string", nullable: true),
                            new OA\Property(property: "logo_url", type: "string", nullable: true, description: "Logo URL (optional if logo file is uploaded)"),
                            new OA\Property(property: "logo", type: "string", format: "binary", nullable: true, description: "Logo file to upload (image file: jpg, jpeg, png, max 5MB)"),
                            new OA\Property(property: "stripe_account_id", type: "string", nullable: true),
                            new OA\Property(property: "documents", type: "array", items: new OA\Items(type: "object"))
                        ]
                    )
                )
            ]
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
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // ============================================
        // STEP 1: Validate file uploads (if any)
        // ============================================
        $uploadValidation = $this->validateFileUploads($request, $acc);
        if (!$uploadValidation['valid']) {
            return response()->json([
                'message' => $uploadValidation['message'],
                'error_code' => $uploadValidation['error_code'] ?? null,
                'hint' => $uploadValidation['hint'] ?? null
            ], 422);
        }

        // ============================================
        // STEP 2: Validate all input data
        // ============================================
        try {
            $request->validate([
                // Basic Information
                'name' => 'sometimes|nullable|string|max:255',
                'legal_name' => 'sometimes|nullable|string|max:255',
                'phone' => 'sometimes|nullable|string|max:255',
                'country' => 'sometimes|nullable|string|max:255',
                'address' => 'sometimes|nullable|string',
                
                // Mailing Address
                'mailing_street' => 'sometimes|nullable|string|max:255',
                'mailing_city' => 'sometimes|nullable|string|max:255',
                'mailing_country' => 'sometimes|nullable|string|max:255',
                'mailing_postal_code' => 'sometimes|nullable|string|max:20',
                
                // Physical Address
                'physical_street' => 'sometimes|nullable|string|max:255',
                'physical_city' => 'sometimes|nullable|string|max:255',
                'physical_country' => 'sometimes|nullable|string|max:255',
                'physical_postal_code' => 'sometimes|nullable|string|max:20',
                
                // Additional Information
                'website' => 'sometimes|nullable|url|max:255',
                'logo_url' => 'sometimes|nullable|url|max:255',
                'logo' => 'sometimes|nullable|image|mimes:jpeg,jpg,png|max:5120', // Max 5MB
                'stripe_account_id' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) {
                        if ($value && !preg_match('/^acct_[a-zA-Z0-9]+$/', $value)) {
                            $fail('The Stripe account ID must start with "acct_" and be a valid Stripe account ID.');
                        }
                    },
                ],
                
                // Documents
                'documents' => 'sometimes|nullable|array',
                'documents.*.id' => 'sometimes|nullable|integer|exists:acc_documents,id',
                'documents.*.document_type' => 'sometimes|nullable|in:license,registration,certificate,other',
                'documents.*.file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        // ============================================
        // STEP 3: Process updates in transaction
        // ============================================
        $uploadedFiles = [];
        $oldFilesToDelete = [];
        $updatedDocuments = [];
        $hasTextUpdates = false;
        $hasFileUpdates = false;
        
        // Early detection of file uploads (before transaction)
        $hasLogoFile = $request->hasFile('logo');
        $hasDocumentFiles = false;
        
        // Check for document files
        if ($request->has('documents') && is_array($request->input('documents'))) {
            $documents = $request->input('documents', []);
            foreach ($documents as $index => $docData) {
                $fileKey = "documents.{$index}.file";
                if ($request->hasFile($fileKey)) {
                    $hasDocumentFiles = true;
                    break;
                }
            }
        }

        try {
            DB::beginTransaction();

            // Process text/data field updates (partial updates supported)
            $updateData = $this->processTextFields($request);
            if (!empty($updateData)) {
                $hasTextUpdates = true;
                $acc->update($updateData);
                $acc->refresh();
                
                // Update user account name if name changed
                if (isset($updateData['name'])) {
                    $userAccount = User::where('email', $user->email)->lockForUpdate()->first();
                    if ($userAccount) {
                        $userAccount->update(['name' => $acc->name]);
                    }
                }
            }

            // Process logo file upload
            if ($hasLogoFile) {
                $logoFile = $request->file('logo');
                // Check if file is valid before processing
                if ($logoFile && $logoFile->isValid()) {
                    $logoResult = $this->handleLogoUpload($request, $acc);
                    if ($logoResult['success']) {
                        $updateData['logo_url'] = $logoResult['logo_url'];
                        $uploadedFiles[] = $logoResult['file_path'];
                        $hasFileUpdates = true;
                        $acc->update(['logo_url' => $logoResult['logo_url']]);
                        $acc->refresh();
                    } else {
                        throw new \Exception($logoResult['error']);
                    }
                } else {
                    // File exists but is invalid
                    throw new \Exception('Invalid logo file uploaded');
                }
            }

            // Process document uploads/updates
            $hasDocumentsInput = $request->has('documents') && is_array($request->input('documents'));
            
            // Process documents if we have input or detected files
            if ($hasDocumentsInput || $hasDocumentFiles) {
                $docResult = $this->handleDocuments($request, $acc);
                $updatedDocuments = $docResult['updated_documents'];
                $uploadedFiles = array_merge($uploadedFiles, $docResult['uploaded_files']);
                $oldFilesToDelete = array_merge($oldFilesToDelete, $docResult['old_files']);
                // Set hasFileUpdates if we have uploaded files OR updated documents
                if (!empty($updatedDocuments) || !empty($docResult['uploaded_files'])) {
                    $hasFileUpdates = true;
                }
            }
            
            // Safety check: If we detected files but hasFileUpdates is still false,
            // it means files were attempted but processing didn't complete successfully
            // In this case, we should have thrown an exception, but as a fallback,
            // ensure we don't return "No changes" if files were detected
            if (($hasLogoFile || $hasDocumentFiles) && !$hasFileUpdates) {
                // This shouldn't happen - if files were detected, they should have been processed
                // or an exception should have been thrown. Log this case for debugging.
                Log::warning('Files detected but hasFileUpdates is false', [
                    'acc_id' => $acc->id,
                    'has_logo_file' => $hasLogoFile,
                    'has_document_files' => $hasDocumentFiles,
                    'uploaded_files_count' => count($uploadedFiles),
                ]);
            }

            // Check if any updates were made
            // Log for debugging
            Log::info('ACC profile update check', [
                'acc_id' => $acc->id,
                'has_text_updates' => $hasTextUpdates,
                'has_file_updates' => $hasFileUpdates,
                'has_logo_file' => $hasLogoFile,
                'has_document_files' => $hasDocumentFiles,
                'uploaded_files_count' => count($uploadedFiles),
                'updated_documents_count' => count($updatedDocuments),
            ]);
            
            if (!$hasTextUpdates && !$hasFileUpdates) {
                DB::rollBack();
                $acc->load('documents.verifiedBy');
                return response()->json([
                    'message' => 'No changes provided. Profile remains unchanged.',
                    'profile' => $this->formatAccProfile($acc)
                ], 200);
            }

            // Commit transaction
            DB::commit();

            // Delete old files after successful commit
            $this->deleteOldFiles($oldFilesToDelete);

            // Reload ACC with documents
            $acc->load('documents.verifiedBy');

            return response()->json([
                'message' => 'Profile updated successfully',
                'profile' => $this->formatAccProfile($acc)
            ]);

        } catch (\Exception $e) {
            // Rollback transaction
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Cleanup uploaded files
            $this->cleanupUploadedFiles($uploadedFiles);

            // Log error
            Log::error('ACC profile update failed', [
                'acc_id' => $acc->id ?? null,
                'user_email' => $user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => config('app.debug') ? 'Profile update failed: ' . $e->getMessage() : 'Profile update failed'
            ], 500);
        }
    }

    /**
     * Validate file uploads before processing
     */
    private function validateFileUploads(Request $request, ACC $acc): array
    {
        if ($request->hasFile('logo')) {
            $logoFile = $request->file('logo');
            
            if (!$logoFile->isValid()) {
                $errorCode = $logoFile->getError();
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
                ];
                
                $errorMessage = $errorMessages[$errorCode] ?? 'Unknown upload error';
                
                Log::error('Logo upload failed at server level', [
                    'acc_id' => $acc->id,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                ]);
                
                return [
                    'valid' => false,
                    'message' => 'File upload failed: ' . $errorMessage,
                    'error_code' => $errorCode,
                    'hint' => 'Please check file size limits. Maximum allowed: 5MB'
                ];
            }
            
            // Check file size
            $fileSize = $logoFile->getSize();
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if ($fileSize > $maxSize) {
                return [
                    'valid' => false,
                    'message' => 'File size exceeds maximum allowed size of 5MB',
                    'hint' => 'Maximum file size: 5MB. Your file: ' . round($fileSize / 1024 / 1024, 2) . ' MB'
                ];
            }
        }
        
        return ['valid' => true];
    }

    /**
     * Process text/data field updates (supports partial updates)
     */
    private function processTextFields(Request $request): array
    {
        $updateData = [];
        $logoFileUploaded = $request->hasFile('logo');
        
        // Define all updatable text fields
        $textFields = [
            'name', 'legal_name', 'phone', 'country', 'address',
            'mailing_street', 'mailing_city', 'mailing_country', 'mailing_postal_code',
            'physical_street', 'physical_city', 'physical_country', 'physical_postal_code',
            'website', 'logo_url', 'stripe_account_id'
        ];
        
        // Fields that can be set to null (cleared)
        $nullableFields = [
            'website', 'logo_url', 'stripe_account_id', 'address',
            'mailing_street', 'mailing_city', 'mailing_country', 'mailing_postal_code',
            'physical_street', 'physical_city', 'physical_country', 'physical_postal_code'
        ];
        
        foreach ($textFields as $field) {
            // Skip logo_url if logo file was uploaded (file takes precedence)
            if ($field === 'logo_url' && $logoFileUploaded) {
                continue;
            }
            
            // Only process fields that are explicitly provided
            if ($request->has($field)) {
                $value = $request->input($field);
                
                if (in_array($field, $nullableFields)) {
                    // Nullable fields: empty string becomes null
                    $updateData[$field] = ($value === '' || $value === null) ? null : $value;
                } else {
                    // Non-nullable fields: only update if not empty
                    if ($value !== null && $value !== '') {
                        $updateData[$field] = $value;
                    }
                }
            }
        }
        
        return $updateData;
    }

    /**
     * Handle logo file upload
     */
    private function handleLogoUpload(Request $request, ACC $acc): array
    {
        try {
            $logoFile = $request->file('logo');
            
            // Delete old logo if exists
            if ($acc->logo_url) {
                try {
                    $oldLogoPath = str_replace(Storage::disk('public')->url(''), '', $acc->logo_url);
                    if (Storage::disk('public')->exists($oldLogoPath)) {
                        Storage::disk('public')->delete($oldLogoPath);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old logo', [
                        'acc_id' => $acc->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Prepare file name
            $originalName = $logoFile->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $fileName = time() . '_' . $acc->id . '_' . $sanitizedName;
            
            // Ensure directory exists
            $directory = 'accs/' . $acc->id . '/logo';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            // Store file
            $logoPath = $logoFile->storeAs($directory, $fileName, 'public');
            
            // Verify file was stored
            $fullPath = Storage::disk('public')->path($logoPath);
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                throw new \Exception('Failed to store logo file');
            }
            
            $logoUrl = Storage::disk('public')->url($logoPath);
            
            Log::info('ACC logo uploaded successfully', [
                'acc_id' => $acc->id,
                'file_name' => $fileName,
                'logo_url' => $logoUrl,
            ]);
            
            return [
                'success' => true,
                'logo_url' => $logoUrl,
                'file_path' => $logoPath
            ];
            
        } catch (\Exception $e) {
            Log::error('Error uploading ACC logo', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle document uploads and updates
     */
    private function handleDocuments(Request $request, ACC $acc): array
    {
        $updatedDocuments = [];
        $uploadedFiles = [];
        $oldFilesToDelete = [];
        
        $documents = $request->input('documents', []);
        
        foreach ($documents as $index => $docData) {
            if (!is_array($docData)) {
                continue;
            }

            $documentId = $docData['id'] ?? null;
            $documentType = $docData['document_type'] ?? null;
            $fileKey = "documents.{$index}.file";
            
            // Handle file upload
            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                
                if ($file && $file->isValid()) {
                    // Validate document type
                    if (!$documentType || !in_array($documentType, ['license', 'registration', 'certificate', 'other'])) {
                        throw new \Exception("Invalid document type for document at index {$index}");
                    }

                    // Store file
                    $directory = 'accs/' . $acc->id . '/documents';
                    $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs($directory, $fileName, 'public');
                    $url = Storage::disk('public')->url($path);
                    
                    $uploadedFiles[] = $path;
                    
                    if ($documentId) {
                        // Update existing document
                        $document = ACCDocument::where('id', $documentId)
                            ->where('acc_id', $acc->id)
                            ->lockForUpdate()
                            ->first();
                        
                        if (!$document) {
                            throw new \Exception("Document with ID {$documentId} not found");
                        }
                        
                        // Track old file for deletion
                        if ($document->document_url) {
                            $oldPath = str_replace(Storage::disk('public')->url(''), '', $document->document_url);
                            if (Storage::disk('public')->exists($oldPath)) {
                                $oldFilesToDelete[] = $oldPath;
                            }
                        }
                        
                        $document->update([
                            'document_type' => $documentType,
                            'document_url' => $url,
                            'uploaded_at' => now(),
                            'verified' => false,
                            'verified_by' => null,
                            'verified_at' => null,
                        ]);
                        $updatedDocuments[] = $document->id;
                    } else {
                        // Create new document
                        $newDocument = ACCDocument::create([
                            'acc_id' => $acc->id,
                            'document_type' => $documentType,
                            'document_url' => $url,
                            'uploaded_at' => now(),
                            'verified' => false,
                        ]);
                        $updatedDocuments[] = $newDocument->id;
                    }
                }
            } elseif ($documentId && isset($docData['document_type'])) {
                // Update document type only (no file upload)
                $document = ACCDocument::where('id', $documentId)
                    ->where('acc_id', $acc->id)
                    ->lockForUpdate()
                    ->first();
                
                if (!$document) {
                    throw new \Exception("Document with ID {$documentId} not found");
                }
                
                if (!in_array($docData['document_type'], ['license', 'registration', 'certificate', 'other'])) {
                    throw new \Exception("Invalid document type: {$docData['document_type']}");
                }

                $document->update(['document_type' => $docData['document_type']]);
                $updatedDocuments[] = $document->id;
            }
        }
        
        return [
            'updated_documents' => $updatedDocuments,
            'uploaded_files' => $uploadedFiles,
            'old_files' => $oldFilesToDelete
        ];
    }

    /**
     * Delete old files after successful commit
     */
    private function deleteOldFiles(array $oldFiles): void
    {
        foreach ($oldFiles as $oldPath) {
            try {
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete old file', [
                    'path' => $oldPath,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Cleanup uploaded files on rollback
     */
    private function cleanupUploadedFiles(array $uploadedFiles): void
    {
        foreach ($uploadedFiles as $filePath) {
            try {
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete uploaded file during rollback', [
                    'path' => $filePath,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Format ACC profile with documents
     */
    private function formatAccProfile(ACC $acc): array
    {
        $userAccount = User::where('email', $acc->email)->first();

        return [
            'id' => $acc->id,
            'name' => $acc->name,
            'legal_name' => $acc->legal_name,
            'registration_number' => $acc->registration_number,
            'email' => $acc->email,
            'phone' => $acc->phone,
            'country' => $acc->country,
            'address' => $acc->address,
            'mailing_address' => [
                'street' => $acc->mailing_street,
                'city' => $acc->mailing_city,
                'country' => $acc->mailing_country,
                'postal_code' => $acc->mailing_postal_code,
            ],
            'physical_address' => [
                'street' => $acc->physical_street,
                'city' => $acc->physical_city,
                'country' => $acc->physical_country,
                'postal_code' => $acc->physical_postal_code,
            ],
            'website' => $acc->website,
            'logo_url' => $acc->logo_url,
            'status' => $acc->status,
            'commission_percentage' => $acc->commission_percentage,
            'stripe_account_id' => $acc->stripe_account_id,
            'stripe_account_configured' => !empty($acc->stripe_account_id),
            'documents' => $acc->documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'document_url' => $document->document_url,
                    'uploaded_at' => $document->uploaded_at,
                    'verified' => $document->verified,
                    'verified_by' => $document->verifiedBy ? [
                        'id' => $document->verifiedBy->id,
                        'name' => $document->verifiedBy->name,
                        'email' => $document->verifiedBy->email,
                    ] : null,
                    'verified_at' => $document->verified_at,
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                ];
            }),
            'user' => $userAccount ? [
                'id' => $userAccount->id,
                'name' => $userAccount->name,
                'email' => $userAccount->email,
                'role' => $userAccount->role,
                'status' => $userAccount->status,
            ] : null,
            'created_at' => $acc->created_at,
            'updated_at' => $acc->updated_at,
        ];
    }

    #[OA\Post(
        path: "/acc/profile/verify-stripe-account",
        summary: "Verify Stripe account ID",
        description: "Verify if a Stripe Connect account ID is valid and connected to the platform.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    required: ["stripe_account_id"],
                    properties: [
                        new OA\Property(property: "stripe_account_id", type: "string", example: "acct_xxxxxxxxxxxxx", description: "Stripe Connect account ID to verify")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Verification result",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "valid", type: "boolean", example: true),
                        new OA\Property(property: "account", type: "object", nullable: true),
                        new OA\Property(property: "error", type: "string", nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 400, description: "Stripe not configured")
        ]
    )]
    public function verifyStripeAccount(Request $request)
    {
        $request->validate([
            'stripe_account_id' => 'required|string|max:255',
        ]);

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'valid' => false,
                'error' => 'Stripe is not configured'
            ], 400);
        }

        $verification = $this->stripeService->verifyStripeAccount($request->stripe_account_id);

        if ($verification['valid']) {
            return response()->json([
                'valid' => true,
                'account' => $verification['account'],
                'message' => 'Stripe account is valid and connected'
            ]);
        } else {
            return response()->json([
                'valid' => false,
                'error' => $verification['error'] ?? 'Invalid Stripe account',
                'message' => 'Stripe account verification failed. Please check that the account ID is correct and the account is properly connected to the platform.'
            ], 400);
        }
    }
}

