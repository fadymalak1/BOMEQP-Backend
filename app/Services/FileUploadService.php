<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload logo file for ACC or Training Center
     *
     * @param UploadedFile $file
     * @param int $entityId
     * @param string $entityType 'acc' or 'training_center'
     * @param string|null $oldLogoUrl
     * @return array
     */
    public function uploadLogo(UploadedFile $file, int $entityId, string $entityType = 'acc', ?string $oldLogoUrl = null): array
    {
        try {
            // Delete old logo if exists
            if ($oldLogoUrl) {
                $this->deleteOldFile($oldLogoUrl, $entityType, $entityId, 'logo');
            }

            // Prepare file name
            $originalName = $file->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $fileName = time() . '_' . $entityId . '_' . $sanitizedName;
            
            // Ensure directory exists
            $directory = $entityType . 's/' . $entityId . '/logo';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            // Store file
            $filePath = $file->storeAs($directory, $fileName, 'public');
            
            // Verify file was stored
            $fullPath = Storage::disk('public')->path($filePath);
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                throw new \Exception('Failed to store logo file');
            }
            
            $fileUrl = Storage::disk('public')->url($filePath);
            
            Log::info("{$entityType} logo uploaded successfully", [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'file_name' => $fileName,
                'logo_url' => $fileUrl,
            ]);
            
            return [
                'success' => true,
                'url' => $fileUrl,
                'file_path' => $filePath
            ];
            
        } catch (\Exception $e) {
            Log::error("Error uploading {$entityType} logo", [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload document file
     *
     * @param UploadedFile $file
     * @param int $entityId
     * @param string $entityType 'acc' or 'instructor'
     * @param string $documentType
     * @return array
     */
    public function uploadDocument(UploadedFile $file, int $entityId, string $entityType = 'acc', string $documentType = 'other'): array
    {
        try {
            // Ensure directory exists
            $directory = $entityType . 's/' . $entityId . '/documents';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Store file
            $originalName = $file->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $fileName = time() . '_' . $entityId . '_' . Str::random(10) . '_' . $sanitizedName;
            $path = $file->storeAs($directory, $fileName, 'public');
            
            // Verify file was stored
            if (!$path || !Storage::disk('public')->exists($path)) {
                throw new \Exception("Failed to store document file");
            }
            
            $url = Storage::disk('public')->url($path);
            
            Log::info("{$entityType} document uploaded successfully", [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'document_type' => $documentType,
                'file_name' => $fileName,
            ]);
            
            return [
                'success' => true,
                'url' => $url,
                'file_path' => $path,
                'file_name' => $fileName
            ];
            
        } catch (\Exception $e) {
            Log::error("Error uploading {$entityType} document", [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete old file from storage
     *
     * @param string $fileUrl
     * @param string $entityType
     * @param int $entityId
     * @param string $fileType 'logo' or 'documents'
     * @return bool
     */
    public function deleteOldFile(string $fileUrl, string $entityType, int $entityId, string $fileType = 'logo'): bool
    {
        try {
            $urlParts = parse_url($fileUrl);
            $path = ltrim($urlParts['path'] ?? '', '/');
            
            // Try multiple patterns to extract file path
            $oldFilePath = null;
            $pattern = '#' . $entityType . 's/\d+/' . $fileType . '/(.+)$#';
            
            if (preg_match($pattern, $path, $matches)) {
                $oldFilePath = $entityType . 's/' . $entityId . '/' . $fileType . '/' . $matches[1];
            } elseif (preg_match('#storage/' . $entityType . 's/\d+/' . $fileType . '/(.+)$#', $path, $matches)) {
                $oldFilePath = $entityType . 's/' . $entityId . '/' . $fileType . '/' . $matches[1];
            } elseif (preg_match('#api/storage/' . $entityType . 's/\d+/' . $fileType . '/(.+)$#', $path, $matches)) {
                $oldFilePath = $entityType . 's/' . $entityId . '/' . $fileType . '/' . $matches[1];
            } else {
                // Fallback: try to extract from URL directly
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $fileUrl);
                $oldPath = ltrim($oldPath, '/storage/');
                $oldPath = ltrim($oldPath, 'storage/');
                if (strpos($oldPath, $entityType . 's/') === 0) {
                    $oldFilePath = $oldPath;
                }
            }
            
            if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                Storage::disk('public')->delete($oldFilePath);
                Log::info("Deleted old {$entityType} {$fileType}", [
                    'entity_id' => $entityId,
                    'entity_type' => $entityType,
                    'file_type' => $fileType,
                    'file_path' => $oldFilePath
                ]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::warning("Failed to delete old {$entityType} {$fileType}", [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'file_type' => $fileType,
                'file_url' => $fileUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate file upload
     *
     * @param UploadedFile|null $file
     * @param int $maxSizeInMB
     * @param array $allowedMimes
     * @return array
     */
    public function validateFile(?UploadedFile $file, int $maxSizeInMB = 5, array $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png']): array
    {
        if (!$file) {
            return [
                'valid' => false,
                'message' => 'No file provided'
            ];
        }

        if (!$file->isValid()) {
            $errorCode = $file->getError();
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
            
            return [
                'valid' => false,
                'message' => 'File upload failed: ' . $errorMessage,
                'error_code' => $errorCode,
                'hint' => "Please check file size limits. Maximum allowed: {$maxSizeInMB}MB"
            ];
        }
        
        // Check file size
        $fileSize = $file->getSize();
        $maxSize = $maxSizeInMB * 1024 * 1024;
        
        if ($fileSize > $maxSize) {
            return [
                'valid' => false,
                'message' => "File size exceeds maximum allowed size of {$maxSizeInMB}MB",
                'hint' => "Maximum file size: {$maxSizeInMB}MB. Your file: " . round($fileSize / 1024 / 1024, 2) . ' MB'
            ];
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimes)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedMimes)
            ];
        }
        
        return ['valid' => true];
    }

    /**
     * Cleanup uploaded files on rollback
     *
     * @param array $filePaths
     * @return void
     */
    public function cleanupFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
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
}

