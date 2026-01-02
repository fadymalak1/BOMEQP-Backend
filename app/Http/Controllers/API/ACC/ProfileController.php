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
                            new OA\Property(property: "website", type: "string", nullable: true, example: "https://example.com"),
                            new OA\Property(property: "logo_url", type: "string", nullable: true, example: "https://example.com/logo.png"),
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
                            new OA\Property(property: "website", type: "string", nullable: true),
                            new OA\Property(property: "logo_url", type: "string", nullable: true),
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

        // All fields are optional - validate only if provided
        $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'legal_name' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string',
            'website' => 'sometimes|nullable|url|max:255',
            'logo_url' => 'sometimes|nullable|url|max:255',
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
            'documents' => 'sometimes|nullable|array',
            'documents.*.id' => 'sometimes|nullable|integer|exists:acc_documents,id',
            'documents.*.document_type' => 'sometimes|nullable|in:license,registration,certificate,other',
            'documents.*.file' => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
        ]);

        // Track uploaded files for rollback
        $uploadedFiles = [];
        $oldFilesToDelete = [];

        try {
            DB::beginTransaction();

            $updateData = [];

            // Only include fields that are actually provided
            $fields = ['name', 'legal_name', 'phone', 'country', 'address', 'website', 'logo_url', 'stripe_account_id'];
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    // Allow null for nullable fields (website, logo_url, stripe_account_id)
                    if (in_array($field, ['website', 'logo_url', 'stripe_account_id'])) {
                        $updateData[$field] = $value === '' ? null : $value;
                    } else {
                        // Only add if value is not null and not empty string
                        if ($value !== null && $value !== '') {
                            $updateData[$field] = $value;
                        }
                    }
                }
            }

            // Update ACC profile if there's data to update
            if (!empty($updateData)) {
                // Log Stripe account ID changes
                if (isset($updateData['stripe_account_id'])) {
                    Log::info('ACC Stripe account ID updated', [
                        'acc_id' => $acc->id,
                        'old_stripe_account_id' => $acc->stripe_account_id,
                        'new_stripe_account_id' => $updateData['stripe_account_id'],
                    ]);
                }

                $acc->update($updateData);
                // Refresh the model to ensure we have the latest data
                $acc->refresh();
            }

            // Handle documents upload/update
            if ($request->has('documents') && is_array($request->input('documents'))) {
                $documents = $request->input('documents', []);
                
                foreach ($documents as $index => $docData) {
                    if (!is_array($docData)) {
                        continue; // Skip invalid entries
                    }

                    $documentId = $docData['id'] ?? null;
                    $documentType = $docData['document_type'] ?? null;
                    $fileKey = "documents.{$index}.file";
                    
                    // Check if file is uploaded
                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);
                        
                        if ($file && $file->isValid()) {
                            // Validate document type if provided
                            if (!$documentType || !in_array($documentType, ['license', 'registration', 'certificate', 'other'])) {
                                throw new \Exception("Invalid document type for document at index {$index}. Must be one of: license, registration, certificate, other");
                            }

                            // Create directory path: accs/{acc_id}/documents/
                            $directory = 'accs/' . $acc->id . '/documents';
                            $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
                            
                            // Store file in public storage
                            $path = $file->storeAs($directory, $fileName, 'public');
                            $url = Storage::disk('public')->url($path);
                            
                            // Track uploaded file for potential rollback
                            $uploadedFiles[] = $path;
                            
                            if ($documentId) {
                                // Update existing document
                                $document = ACCDocument::where('id', $documentId)
                                    ->where('acc_id', $acc->id)
                                    ->first();
                                
                                if ($document) {
                                    // Track old file for deletion after successful commit
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
                                        'verified' => false, // Reset verification when document is updated
                                        'verified_by' => null,
                                        'verified_at' => null,
                                    ]);
                                } else {
                                    throw new \Exception("Document with ID {$documentId} not found or does not belong to this ACC");
                                }
                            } else {
                                // Create new document
                                ACCDocument::create([
                                    'acc_id' => $acc->id,
                                    'document_type' => $documentType,
                                    'document_url' => $url,
                                    'uploaded_at' => now(),
                                    'verified' => false,
                                ]);
                            }
                        }
                    } elseif ($documentId && isset($docData['document_type'])) {
                        // Update document type only (no file upload)
                        $document = ACCDocument::where('id', $documentId)
                            ->where('acc_id', $acc->id)
                            ->first();
                        
                        if ($document) {
                            // Validate document type
                            if (!in_array($docData['document_type'], ['license', 'registration', 'certificate', 'other'])) {
                                throw new \Exception("Invalid document type: {$docData['document_type']}");
                            }

                            $document->update([
                                'document_type' => $docData['document_type'],
                            ]);
                        } else {
                            throw new \Exception("Document with ID {$documentId} not found or does not belong to this ACC");
                        }
                    }
                }
            }

            // Update user account name if name changed
            if (isset($updateData['name'])) {
                $userAccount = User::where('email', $user->email)->first();
                if ($userAccount) {
                    $userAccount->update(['name' => $acc->name]);
                }
            }

            // Commit transaction
            DB::commit();

            // Delete old files after successful commit
            foreach ($oldFilesToDelete as $oldPath) {
                try {
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                } catch (\Exception $e) {
                    // Log but don't fail if file deletion fails
                    Log::warning('Failed to delete old document file', [
                        'path' => $oldPath,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Reload ACC with documents
            $acc->load('documents.verifiedBy');

            return response()->json([
                'message' => 'Profile updated successfully',
                'profile' => $this->formatAccProfile($acc)
            ]);

        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            // Delete uploaded files on rollback
            foreach ($uploadedFiles as $filePath) {
                try {
                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                } catch (\Exception $deleteException) {
                    Log::error('Failed to delete uploaded file during rollback', [
                        'path' => $filePath,
                        'error' => $deleteException->getMessage()
                    ]);
                }
            }

            Log::error('ACC profile update failed', [
                'acc_id' => $acc->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Profile update failed: ' . $e->getMessage()
            ], 500);
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

