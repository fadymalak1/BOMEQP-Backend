<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Services\InstructorProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    protected InstructorProfileService $profileService;

    public function __construct(InstructorProfileService $profileService)
    {
        $this->profileService = $profileService;
    }
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

        return response()->json([
            'profile' => $this->profileService->getProfile($instructor)
        ]);
    }

    #[OA\Post(
        path: "/instructor/profile",
        summary: "Update instructor profile",
        description: "Update the authenticated instructor's profile information. Use POST method for file uploads. Laravel's method spoofing with _method=PUT is supported for compatibility.",
        tags: ["Instructor"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "_method", type: "string", example: "PUT", nullable: true, description: "HTTP method override (optional, for compatibility with PUT endpoints)"),
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

        // Validate input
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
        ]);

        // Process updates using service
        try {
            $result = $this->profileService->updateProfile($request, $instructor, $user);
            
            return response()->json([
                'message' => $result['message'],
                'profile' => $result['profile']
            ]);

        } catch (\Exception $e) {
            Log::error('Instructor profile update failed', [
                'instructor_id' => $instructor->id ?? null,
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

