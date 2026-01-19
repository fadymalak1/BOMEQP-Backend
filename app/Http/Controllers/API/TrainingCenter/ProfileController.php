<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\TrainingCenter;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
    #[OA\Get(
        path: "/training-center/profile",
        summary: "Get training center profile",
        description: "Get the authenticated training center admin's profile information including all training center data.",
        tags: ["Training Center"],
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
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function show(Request $request)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        return response()->json([
            'profile' => $trainingCenter
        ]);
    }

    #[OA\Post(
        path: "/training-center/profile",
        summary: "Update training center profile",
        description: "Update the authenticated training center's profile information. Use POST method for file uploads. Laravel's method spoofing with _method=PUT is supported for compatibility. All fields are optional.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: [
                new OA\MediaType(
                    mediaType: "application/json",
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: "name", type: "string", example: "Training Center Name", nullable: true),
                            new OA\Property(property: "legal_name", type: "string", example: "Training Center Legal Name Inc.", nullable: true),
                            new OA\Property(property: "registration_number", type: "string", example: "REG123456", nullable: true),
                            new OA\Property(property: "country", type: "string", example: "Egypt", nullable: true),
                            new OA\Property(property: "city", type: "string", example: "Cairo", nullable: true),
                            new OA\Property(property: "address", type: "string", example: "123 Main Street", nullable: true),
                            new OA\Property(property: "phone", type: "string", example: "+201234567890", nullable: true),
                            new OA\Property(property: "email", type: "string", format: "email", example: "info@trainingcenter.com", nullable: true),
                            new OA\Property(property: "website", type: "string", format: "url", example: "https://www.trainingcenter.com", nullable: true),
                            new OA\Property(property: "logo_url", type: "string", format: "url", example: "https://example.com/logo.png", nullable: true, description: "Logo URL (optional if logo file is uploaded)"),
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
                            new OA\Property(property: "registration_number", type: "string", nullable: true),
                            new OA\Property(property: "country", type: "string", nullable: true),
                            new OA\Property(property: "city", type: "string", nullable: true),
                            new OA\Property(property: "address", type: "string", nullable: true),
                            new OA\Property(property: "phone", type: "string", nullable: true),
                            new OA\Property(property: "email", type: "string", format: "email", nullable: true),
                            new OA\Property(property: "website", type: "string", format: "url", nullable: true),
                            new OA\Property(property: "logo_url", type: "string", nullable: true, description: "Logo URL (optional if logo file is uploaded)"),
                            new OA\Property(property: "logo", type: "string", format: "binary", nullable: true, description: "Logo file to upload (image file: jpg, jpeg, png, max 5MB)"),
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
            new OA\Response(response: 404, description: "Training center not found"),
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
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
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

        if ($request->hasFile('facility_floorplan')) {
            $floorplanFile = $request->file('facility_floorplan');
            $validation = $this->fileUploadService->validateFile($floorplanFile, 10, ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => $validation['message'],
                    'error_code' => $validation['error_code'] ?? null,
                    'hint' => $validation['hint'] ?? null
                ], 422);
            }
        }

        // Validate input
        try {
            $request->validate([
                // Company Information
                'name' => 'sometimes|string|max:255',
                'website' => 'nullable|string|url|max:255',
                'email' => 'sometimes|email|max:255|unique:training_centers,email,' . $trainingCenter->id,
                'phone' => 'sometimes|string|max:255',
                'fax' => 'nullable|string|max:255',
                'training_provider_type' => 'sometimes|in:Training Center,Institute,University',
                // Physical Address
                'address' => 'sometimes|string',
                'city' => 'sometimes|string|max:255',
                'country' => 'sometimes|string|max:255',
                'physical_postal_code' => 'sometimes|string|max:255',
                // Mailing Address
                'mailing_same_as_physical' => 'sometimes|boolean',
                'mailing_address' => 'nullable|string|required_if:mailing_same_as_physical,false',
                'mailing_city' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
                'mailing_country' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
                'mailing_postal_code' => 'nullable|string|max:255|required_if:mailing_same_as_physical,false',
                // Primary Contact
                'primary_contact_title' => 'sometimes|in:Mr.,Mrs.,Eng.,Prof.',
                'primary_contact_first_name' => 'sometimes|string|max:255',
                'primary_contact_last_name' => 'sometimes|string|max:255',
                'primary_contact_email' => 'sometimes|email|max:255',
                'primary_contact_country' => 'sometimes|string|max:255',
                'primary_contact_mobile' => 'sometimes|string|max:255',
                // Secondary Contact
                'has_secondary_contact' => 'sometimes|boolean',
                'secondary_contact_title' => 'nullable|in:Mr.,Mrs.,Eng.,Prof.|required_if:has_secondary_contact,true',
                'secondary_contact_first_name' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
                'secondary_contact_last_name' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
                'secondary_contact_email' => 'nullable|email|max:255|required_if:has_secondary_contact,true',
                'secondary_contact_country' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
                'secondary_contact_mobile' => 'nullable|string|max:255|required_if:has_secondary_contact,true',
                // Additional Information
                'company_gov_registry_number' => 'sometimes|string|max:255',
                'company_registration_certificate' => 'sometimes|nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
                'facility_floorplan' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
                'interested_fields' => 'nullable|array',
                'interested_fields.*' => 'string|in:QHSE,Food Safety,Management',
                'how_did_you_hear_about_us' => 'nullable|string',
                // Legacy fields
                'legal_name' => 'sometimes|string|max:255',
                'registration_number' => 'sometimes|string|max:255|unique:training_centers,registration_number,' . $trainingCenter->id,
                'logo_url' => 'nullable|string|url|max:500',
                'logo' => 'sometimes|nullable|image|mimes:jpeg,jpg,png|max:5120', // Max 5MB
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            // Process logo file upload if provided
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                if ($logoFile && $logoFile->isValid()) {
                    $logoResult = $this->fileUploadService->uploadLogo($logoFile, $trainingCenter->id, 'training_center', $trainingCenter->logo_url);
                    if ($logoResult['success']) {
                        $trainingCenter->logo_url = $logoResult['url'];
                        $trainingCenter->save();
                    } else {
                        throw new \Exception($logoResult['error'] ?? 'Logo upload failed');
                    }
                }
            }

            // Process company registration certificate file upload
            if ($request->hasFile('company_registration_certificate')) {
                $certFile = $request->file('company_registration_certificate');
                if ($certFile && $certFile->isValid()) {
                    $certResult = $this->fileUploadService->uploadDocument(
                        $certFile,
                        $trainingCenter->id,
                        'training_center',
                        'registration_certificate'
                    );
                    if ($certResult['success']) {
                        $trainingCenter->company_registration_certificate_url = $certResult['url'];
                        $trainingCenter->save();
                    } else {
                        throw new \Exception($certResult['error'] ?? 'Registration certificate upload failed');
                    }
                }
            }

            // Process facility floorplan file upload
            if ($request->hasFile('facility_floorplan')) {
                $floorplanFile = $request->file('facility_floorplan');
                if ($floorplanFile && $floorplanFile->isValid()) {
                    $floorplanResult = $this->fileUploadService->uploadDocument(
                        $floorplanFile,
                        $trainingCenter->id,
                        'training_center',
                        'floorplan'
                    );
                    if ($floorplanResult['success']) {
                        $trainingCenter->facility_floorplan_url = $floorplanResult['url'];
                        $trainingCenter->save();
                    } else {
                        throw new \Exception($floorplanResult['error'] ?? 'Floorplan upload failed');
                    }
                }
            }

            // Handle mailing address - if same as physical, copy physical address fields
            $updateData = [];
            if ($request->has('mailing_same_as_physical') && $request->mailing_same_as_physical) {
                $updateData['mailing_same_as_physical'] = true;
                $updateData['mailing_address'] = $request->input('address', $trainingCenter->address);
                $updateData['mailing_city'] = $request->input('city', $trainingCenter->city);
                $updateData['mailing_country'] = $request->input('country', $trainingCenter->country);
                $updateData['mailing_postal_code'] = $request->input('physical_postal_code', $trainingCenter->physical_postal_code);
            }

            // Get all fillable fields from request
            $fillableFields = [
                'name', 'legal_name', 'registration_number', 'country', 'city', 'address',
                'phone', 'email', 'website', 'fax', 'training_provider_type',
                'physical_postal_code',
                'mailing_same_as_physical', 'mailing_address', 'mailing_city', 'mailing_country', 'mailing_postal_code',
                'primary_contact_title', 'primary_contact_first_name', 'primary_contact_last_name',
                'primary_contact_email', 'primary_contact_country', 'primary_contact_mobile',
                'has_secondary_contact', 'secondary_contact_title', 'secondary_contact_first_name',
                'secondary_contact_last_name', 'secondary_contact_email', 'secondary_contact_country', 'secondary_contact_mobile',
                'company_gov_registry_number', 'interested_fields', 'how_did_you_hear_about_us',
            ];

            foreach ($fillableFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    // Handle array fields (interested_fields)
                    if ($field === 'interested_fields' && is_string($value)) {
                        $value = json_decode($value, true);
                    }
                    $updateData[$field] = $value;
                }
            }

            // Only include logo_url if logo file was NOT uploaded
            if (!$request->hasFile('logo') && $request->has('logo_url')) {
                $updateData['logo_url'] = $request->logo_url;
            }

            // Filter out null values to only update provided fields
            $updateData = array_filter($updateData, function ($value) {
                return $value !== null && $value !== '';
            });

            if (!empty($updateData)) {
                $trainingCenter->update($updateData);
                
                // Sync user name if training center name was updated
                if (isset($updateData['name']) && $updateData['name']) {
                    $userAccount = User::where('email', $user->email)->first();
                    if ($userAccount && $userAccount->name !== $updateData['name']) {
                        $userAccount->update(['name' => $updateData['name']]);
                    }
                }
            }

            // Refresh the model to get updated data
            $trainingCenter->refresh();

            return response()->json([
                'message' => 'Profile updated successfully',
                'profile' => $trainingCenter
            ], 200);

        } catch (\Exception $e) {
            Log::error('Training center profile update failed', [
                'training_center_id' => $trainingCenter->id ?? null,
                'user_email' => $user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => config('app.debug') ? 'Profile update failed: ' . $e->getMessage() : 'Profile update failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Update failed',
                'error_code' => 'update_failed'
            ], 500);
        }
    }
}

