<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Services\ACCProfileService;
use App\Services\FileUploadService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    protected StripeService $stripeService;
    protected ACCProfileService $profileService;
    protected FileUploadService $fileUploadService;

    public function __construct(
        StripeService $stripeService,
        ACCProfileService $profileService,
        FileUploadService $fileUploadService
    ) {
        $this->stripeService = $stripeService;
        $this->profileService = $profileService;
        $this->fileUploadService = $fileUploadService;
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
            'profile' => $this->profileService->getProfile($acc)
        ]);
    }

    #[OA\Post(
        path: "/acc/profile",
        summary: "Update ACC profile",
        description: "Update the authenticated ACC's profile information including Stripe account ID. Use POST method for file uploads. Laravel's method spoofing with _method=PUT is supported for compatibility.",
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
                            new OA\Property(property: "_method", type: "string", example: "PUT", nullable: true, description: "HTTP method override (optional, for compatibility with PUT endpoints)"),
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

        // Validate file uploads (if any)
        if ($request->hasFile('logo')) {
            $logoFile = $request->file('logo');
            $validation = $this->fileUploadService->validateFile($logoFile, 5, ['image/jpeg', 'image/jpg', 'image/png']);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => $validation['message'],
                    'error_code' => $validation['error_code'] ?? null,
                    'hint' => $validation['hint'] ?? null
                ], 422);
            }
        }

        // Validate file uploads (if any)
        if ($request->hasFile('primary_contact_passport')) {
            $passportFile = $request->file('primary_contact_passport');
            $validation = $this->fileUploadService->validateFile($passportFile, 10, ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => $validation['message'],
                    'error_code' => $validation['error_code'] ?? null,
                    'hint' => $validation['hint'] ?? null
                ], 422);
            }
        }

        if ($request->hasFile('secondary_contact_passport')) {
            $passportFile = $request->file('secondary_contact_passport');
            $validation = $this->fileUploadService->validateFile($passportFile, 10, ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => $validation['message'],
                    'error_code' => $validation['error_code'] ?? null,
                    'hint' => $validation['hint'] ?? null
                ], 422);
            }
        }

        if ($request->hasFile('company_registration_certificate')) {
            $certFile = $request->file('company_registration_certificate');
            $validation = $this->fileUploadService->validateFile($certFile, 10, ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => $validation['message'],
                    'error_code' => $validation['error_code'] ?? null,
                    'hint' => $validation['hint'] ?? null
                ], 422);
            }
        }

        // Validate all input data
        try {
            $request->validate([
                // Basic Information
                'name' => 'sometimes|nullable|string|max:255',
                'legal_name' => 'sometimes|nullable|string|max:255',
                'phone' => 'sometimes|nullable|string|max:255',
                'fax' => 'nullable|string|max:255',
                'country' => 'sometimes|nullable|string|max:255',
                'address' => 'sometimes|nullable|string',
                
                // Mailing Address
                'mailing_same_as_physical' => 'sometimes|boolean',
                'mailing_street' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
                'mailing_city' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
                'mailing_country' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
                'mailing_postal_code' => 'nullable|string|max:20|required_if:mailing_same_as_physical,false',
                
                // Physical Address
                'physical_street' => 'sometimes|nullable|string|max:255',
                'physical_city' => 'sometimes|nullable|string|max:255',
                'physical_country' => 'sometimes|nullable|string|max:255',
                'physical_postal_code' => 'sometimes|nullable|string|max:20',
                
                // Primary Contact
                'primary_contact_title' => 'sometimes|in:Mr.,Mrs.,Eng.,Prof.',
                'primary_contact_first_name' => 'sometimes|string|max:255',
                'primary_contact_last_name' => 'sometimes|string|max:255',
                'primary_contact_email' => 'sometimes|email|max:255',
                'primary_contact_country' => 'sometimes|string|max:255',
                'primary_contact_mobile' => 'sometimes|string|max:255',
                'primary_contact_passport' => 'sometimes|nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
                
                // Secondary Contact (Required)
                'secondary_contact_title' => 'sometimes|in:Mr.,Mrs.,Eng.,Prof.',
                'secondary_contact_first_name' => 'sometimes|string|max:255',
                'secondary_contact_last_name' => 'sometimes|string|max:255',
                'secondary_contact_email' => 'sometimes|email|max:255',
                'secondary_contact_country' => 'sometimes|string|max:255',
                'secondary_contact_mobile' => 'sometimes|string|max:255',
                'secondary_contact_passport' => 'sometimes|nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
                
                // Additional Information
                'company_gov_registry_number' => 'sometimes|string|max:255',
                'company_registration_certificate' => 'sometimes|nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
                'how_did_you_hear_about_us' => 'nullable|string',
                'agreed_to_receive_communications' => 'sometimes|boolean',
                'agreed_to_terms_and_conditions' => 'sometimes|boolean',
                
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

        // Process updates using service
        try {
            $result = $this->profileService->updateProfile($request, $acc, $user);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'profile' => $result['profile']
                ], 200);
            }

            return response()->json([
                'message' => $result['message'],
                'profile' => $result['profile']
            ]);

        } catch (\Exception $e) {
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

