<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\ACCDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ACCProfileService
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Get ACC profile with formatted data
     *
     * @param ACC $acc
     * @return array
     */
    public function getProfile(ACC $acc): array
    {
        $userAccount = User::where('email', $acc->email)->first();

        return [
            'id' => $acc->id,
            'name' => $acc->name,
            'legal_name' => $acc->legal_name,
            'registration_number' => $acc->registration_number,
            'email' => $acc->email,
            'phone' => $acc->phone,
            'fax' => $acc->fax,
            'country' => $acc->country,
            'address' => $acc->address,
            'mailing_address' => [
                'street' => $acc->mailing_street,
                'city' => $acc->mailing_city,
                'country' => $acc->mailing_country,
                'postal_code' => $acc->mailing_postal_code,
                'same_as_physical' => $acc->mailing_same_as_physical ?? false,
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
            'primary_contact' => [
                'title' => $acc->primary_contact_title,
                'first_name' => $acc->primary_contact_first_name,
                'last_name' => $acc->primary_contact_last_name,
                'email' => $acc->primary_contact_email,
                'country' => $acc->primary_contact_country,
                'mobile' => $acc->primary_contact_mobile,
                'passport_url' => $acc->primary_contact_passport_url,
            ],
            'secondary_contact' => [
                'title' => $acc->secondary_contact_title,
                'first_name' => $acc->secondary_contact_first_name,
                'last_name' => $acc->secondary_contact_last_name,
                'email' => $acc->secondary_contact_email,
                'country' => $acc->secondary_contact_country,
                'mobile' => $acc->secondary_contact_mobile,
                'passport_url' => $acc->secondary_contact_passport_url,
            ],
            'company_gov_registry_number' => $acc->company_gov_registry_number,
            'company_registration_certificate_url' => $acc->company_registration_certificate_url,
            'how_did_you_hear_about_us' => $acc->how_did_you_hear_about_us,
            'agreed_to_receive_communications' => $acc->agreed_to_receive_communications ?? false,
            'agreed_to_terms_and_conditions' => $acc->agreed_to_terms_and_conditions ?? false,
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

    /**
     * Update ACC profile
     *
     * @param Request $request
     * @param ACC $acc
     * @param User $user
     * @return array
     * @throws \Exception
     */
    public function updateProfile(Request $request, ACC $acc, User $user): array
    {
        $uploadedFiles = [];
        $oldFilesToDelete = [];
        $updatedDocuments = [];
        $hasTextUpdates = false;
        $hasFileUpdates = false;
        
        // Early detection of file uploads
        $hasLogoFile = $request->hasFile('logo');
        $hasDocumentFiles = false;
        
        // Check for document files
        $allFiles = $request->allFiles();
        foreach ($allFiles as $key => $file) {
            if ($key === 'logo') {
                $hasLogoFile = true;
            }
            if (preg_match('/^documents\[\d+\]\[file\]$/', $key) || 
                preg_match('/^documents\.\d+\.file$/', $key)) {
                $hasDocumentFiles = true;
                break;
            }
        }
        
        $hasDocumentsInput = $request->has('documents') && is_array($request->input('documents'));

        try {
            DB::beginTransaction();

            // Process text/data field updates
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
                if ($logoFile && $logoFile->isValid()) {
                    $logoResult = $this->fileUploadService->uploadLogo($logoFile, $acc->id, 'acc', $acc->logo_url);
                    if ($logoResult['success']) {
                        $updateData['logo_url'] = $logoResult['url'];
                        $uploadedFiles[] = $logoResult['file_path'];
                        $hasFileUpdates = true;
                        $acc->update(['logo_url' => $logoResult['url']]);
                        $acc->refresh();
                    } else {
                        throw new \Exception($logoResult['error']);
                    }
                } else {
                    throw new \Exception('Invalid logo file uploaded');
                }
            }

            // Process primary contact passport upload
            if ($request->hasFile('primary_contact_passport')) {
                $passportFile = $request->file('primary_contact_passport');
                if ($passportFile && $passportFile->isValid()) {
                    $passportResult = $this->fileUploadService->uploadDocument(
                        $passportFile,
                        $acc->id,
                        'acc',
                        'primary_contact_passport'
                    );
                    if ($passportResult['success']) {
                        $oldFilesToDelete[] = $acc->primary_contact_passport_url;
                        $acc->update(['primary_contact_passport_url' => $passportResult['url']]);
                        $uploadedFiles[] = $passportResult['file_path'];
                        $hasFileUpdates = true;
                        $acc->refresh();
                    } else {
                        throw new \Exception($passportResult['error'] ?? 'Primary contact passport upload failed');
                    }
                }
            }

            // Process secondary contact passport upload
            if ($request->hasFile('secondary_contact_passport')) {
                $passportFile = $request->file('secondary_contact_passport');
                if ($passportFile && $passportFile->isValid()) {
                    $passportResult = $this->fileUploadService->uploadDocument(
                        $passportFile,
                        $acc->id,
                        'acc',
                        'secondary_contact_passport'
                    );
                    if ($passportResult['success']) {
                        $oldFilesToDelete[] = $acc->secondary_contact_passport_url;
                        $acc->update(['secondary_contact_passport_url' => $passportResult['url']]);
                        $uploadedFiles[] = $passportResult['file_path'];
                        $hasFileUpdates = true;
                        $acc->refresh();
                    } else {
                        throw new \Exception($passportResult['error'] ?? 'Secondary contact passport upload failed');
                    }
                }
            }

            // Process company registration certificate upload
            if ($request->hasFile('company_registration_certificate')) {
                $certFile = $request->file('company_registration_certificate');
                if ($certFile && $certFile->isValid()) {
                    $certResult = $this->fileUploadService->uploadDocument(
                        $certFile,
                        $acc->id,
                        'acc',
                        'registration_certificate'
                    );
                    if ($certResult['success']) {
                        $oldFilesToDelete[] = $acc->company_registration_certificate_url;
                        $acc->update(['company_registration_certificate_url' => $certResult['url']]);
                        $uploadedFiles[] = $certResult['file_path'];
                        $hasFileUpdates = true;
                        $acc->refresh();
                    } else {
                        throw new \Exception($certResult['error'] ?? 'Registration certificate upload failed');
                    }
                }
            }

            // Handle mailing address same as physical logic
            if ($request->has('mailing_same_as_physical') && $request->mailing_same_as_physical) {
                $acc->update([
                    'mailing_same_as_physical' => true,
                    'mailing_street' => $acc->physical_street ?? $request->input('physical_street'),
                    'mailing_city' => $acc->physical_city ?? $request->input('physical_city'),
                    'mailing_country' => $acc->physical_country ?? $request->input('physical_country'),
                    'mailing_postal_code' => $acc->physical_postal_code ?? $request->input('physical_postal_code'),
                ]);
                $acc->refresh();
            }

            // Process document uploads/updates
            if ($hasDocumentsInput || $hasDocumentFiles) {
                $docResult = $this->handleDocuments($request, $acc);
                $updatedDocuments = $docResult['updated_documents'];
                $uploadedFiles = array_merge($uploadedFiles, $docResult['uploaded_files']);
                $oldFilesToDelete = array_merge($oldFilesToDelete, $docResult['old_files']);
                if (!empty($updatedDocuments) || !empty($docResult['uploaded_files'])) {
                    $hasFileUpdates = true;
                }
            }
            
            if (!$hasTextUpdates && !$hasFileUpdates) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No changes provided. Profile remains unchanged.',
                    'profile' => $this->getProfile($acc)
                ];
            }

            // Commit transaction
            DB::commit();

            // Delete old files after successful commit
            $this->deleteOldFiles($oldFilesToDelete);

            // Reload ACC with documents
            $acc->load('documents.verifiedBy');

            return [
                'success' => true,
                'message' => 'Profile updated successfully',
                'profile' => $this->getProfile($acc)
            ];

        } catch (\Exception $e) {
            // Rollback transaction
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Cleanup uploaded files
            $this->fileUploadService->cleanupFiles($uploadedFiles);

            // Log error
            Log::error('ACC profile update failed', [
                'acc_id' => $acc->id ?? null,
                'user_email' => $user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process text/data field updates (supports partial updates)
     *
     * @param Request $request
     * @return array
     */
    private function processTextFields(Request $request): array
    {
        $updateData = [];
        $logoFileUploaded = $request->hasFile('logo');
        
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
        
        // Define all updatable text fields
        $textFields = [
            'name', 'legal_name', 'phone', 'fax', 'country', 'address',
            'mailing_street', 'mailing_city', 'mailing_country', 'mailing_postal_code', 'mailing_same_as_physical',
            'physical_street', 'physical_city', 'physical_country', 'physical_postal_code',
            'website', 'logo_url', 'stripe_account_id',
            'primary_contact_title', 'primary_contact_first_name', 'primary_contact_last_name',
            'primary_contact_email', 'primary_contact_country', 'primary_contact_mobile',
            'secondary_contact_title', 'secondary_contact_first_name', 'secondary_contact_last_name',
            'secondary_contact_email', 'secondary_contact_country', 'secondary_contact_mobile',
            'company_gov_registry_number', 'how_did_you_hear_about_us',
            'agreed_to_receive_communications', 'agreed_to_terms_and_conditions'
        ];
        
        // Fields that can be set to null (cleared)
        $nullableFields = [
            'website', 'logo_url', 'stripe_account_id', 'address', 'fax',
            'mailing_street', 'mailing_city', 'mailing_country', 'mailing_postal_code',
            'physical_street', 'physical_city', 'physical_country', 'physical_postal_code',
            'primary_contact_title', 'primary_contact_first_name', 'primary_contact_last_name',
            'primary_contact_email', 'primary_contact_country', 'primary_contact_mobile',
            'secondary_contact_title', 'secondary_contact_first_name', 'secondary_contact_last_name',
            'secondary_contact_email', 'secondary_contact_country', 'secondary_contact_mobile',
            'how_did_you_hear_about_us'
        ];
        
        foreach ($textFields as $field) {
            // Skip logo_url if logo file was uploaded (file takes precedence)
            if ($field === 'logo_url' && $logoFileUploaded) {
                continue;
            }
            
            // Check if field exists in request (works for both POST and PUT)
            if (!($request->has($field) || array_key_exists($field, $allRequestData))) {
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
     * Handle document uploads and updates
     *
     * @param Request $request
     * @param ACC $acc
     * @return array
     * @throws \Exception
     */
    private function handleDocuments(Request $request, ACC $acc): array
    {
        $updatedDocuments = [];
        $uploadedFiles = [];
        $oldFilesToDelete = [];
        
        $documents = $request->input('documents', []);
        $allFiles = $request->allFiles();
        
        // Build a map of file keys to their index
        $fileMap = [];
        foreach ($allFiles as $key => $file) {
            // Handle both formats: documents[0][file] and documents.0.file
            if (preg_match('/^documents\[(\d+)\]\[file\]$/', $key, $matches)) {
                $fileMap[(int)$matches[1]] = $key;
            } elseif (preg_match('/^documents\.(\d+)\.file$/', $key, $matches)) {
                $fileMap[(int)$matches[1]] = $key;
            }
        }
        
        foreach ($documents as $index => $docData) {
            if (!is_array($docData)) {
                continue;
            }

            $documentId = $docData['id'] ?? null;
            $documentType = $docData['document_type'] ?? null;
            
            // Get file using the mapped key or try standard formats
            $file = null;
            if (isset($fileMap[$index])) {
                $file = $request->file($fileMap[$index]);
            } else {
                // Try standard Laravel nested array formats
                $file = $request->file("documents.{$index}.file") ?? 
                        $request->file("documents[{$index}][file]");
            }
            
            // Handle file upload
            if ($file && $file->isValid()) {
                // Validate document type
                if (!$documentType || !in_array($documentType, ['license', 'registration', 'certificate', 'other'])) {
                    throw new \Exception("Invalid document type for document at index {$index}. Must be one of: license, registration, certificate, other");
                }

                // Upload document
                $uploadResult = $this->fileUploadService->uploadDocument($file, $acc->id, 'acc', $documentType);
                
                if (!$uploadResult['success']) {
                    throw new \Exception($uploadResult['error']);
                }
                
                $uploadedFiles[] = $uploadResult['file_path'];
                
                if ($documentId) {
                    // Update existing document
                    $document = ACCDocument::where('id', $documentId)
                        ->where('acc_id', $acc->id)
                        ->lockForUpdate()
                        ->first();
                    
                    if (!$document) {
                        throw new \Exception("Document with ID {$documentId} not found or does not belong to this ACC");
                    }
                    
                    // Track old file for deletion
                    if ($document->document_url) {
                        $oldFilePath = $this->extractFilePathFromUrl($document->document_url, 'acc', $acc->id, 'documents');
                        if ($oldFilePath) {
                            $oldFilesToDelete[] = $oldFilePath;
                        }
                    }
                    
                    $document->update([
                        'document_type' => $documentType,
                        'document_url' => $uploadResult['url'],
                        'uploaded_at' => now(),
                        'verified' => false,
                        'verified_by' => null,
                        'verified_at' => null,
                    ]);
                    $updatedDocuments[] = $document->id;
                    
                    Log::info('ACC document updated', [
                        'acc_id' => $acc->id,
                        'document_id' => $documentId,
                        'document_type' => $documentType,
                        'file_name' => $uploadResult['file_name']
                    ]);
                } else {
                    // Create new document
                    $newDocument = ACCDocument::create([
                        'acc_id' => $acc->id,
                        'document_type' => $documentType,
                        'document_url' => $uploadResult['url'],
                        'uploaded_at' => now(),
                        'verified' => false,
                    ]);
                    $updatedDocuments[] = $newDocument->id;
                    
                    Log::info('ACC document created', [
                        'acc_id' => $acc->id,
                        'document_id' => $newDocument->id,
                        'document_type' => $documentType,
                        'file_name' => $uploadResult['file_name']
                    ]);
                }
            } elseif ($documentId && isset($docData['document_type'])) {
                // Update document type only (no file upload)
                $document = ACCDocument::where('id', $documentId)
                    ->where('acc_id', $acc->id)
                    ->lockForUpdate()
                    ->first();
                
                if (!$document) {
                    throw new \Exception("Document with ID {$documentId} not found or does not belong to this ACC");
                }
                
                if (!in_array($docData['document_type'], ['license', 'registration', 'certificate', 'other'])) {
                    throw new \Exception("Invalid document type: {$docData['document_type']}. Must be one of: license, registration, certificate, other");
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
     * Extract file path from URL
     *
     * @param string $fileUrl
     * @param string $entityType
     * @param int $entityId
     * @param string $fileType
     * @return string|null
     */
    private function extractFilePathFromUrl(string $fileUrl, string $entityType, int $entityId, string $fileType): ?string
    {
        try {
            $urlParts = parse_url($fileUrl);
            $path = ltrim($urlParts['path'] ?? '', '/');
            
            // Try multiple patterns to extract file path
            if (preg_match('#' . $entityType . 's/\d+/' . $fileType . '/(.+)$#', $path, $matches)) {
                return $entityType . 's/' . $entityId . '/' . $fileType . '/' . $matches[1];
            } elseif (preg_match('#storage/' . $entityType . 's/\d+/' . $fileType . '/(.+)$#', $path, $matches)) {
                return $entityType . 's/' . $entityId . '/' . $fileType . '/' . $matches[1];
            } elseif (preg_match('#api/storage/' . $entityType . 's/\d+/' . $fileType . '/(.+)$#', $path, $matches)) {
                return $entityType . 's/' . $entityId . '/' . $fileType . '/' . $matches[1];
            } else {
                // Fallback: try to extract from URL directly
                $oldPath = str_replace(\Illuminate\Support\Facades\Storage::disk('public')->url(''), '', $fileUrl);
                $oldPath = ltrim($oldPath, '/storage/');
                $oldPath = ltrim($oldPath, 'storage/');
                if (strpos($oldPath, $entityType . 's/') === 0) {
                    return $oldPath;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to extract file path from URL', [
                'file_url' => $fileUrl,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete old files after successful commit
     *
     * @param array $oldFiles
     * @return void
     */
    private function deleteOldFiles(array $oldFiles): void
    {
        foreach ($oldFiles as $oldPath) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete old file', [
                    'path' => $oldPath,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

