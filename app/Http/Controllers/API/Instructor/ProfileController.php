<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
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
                'certificates' => $instructor->certificates_json ?? [],
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
                        new OA\Property(property: "first_name", type: "string", nullable: true, example: "John"),
                        new OA\Property(property: "last_name", type: "string", nullable: true, example: "Doe"),
                        new OA\Property(property: "phone", type: "string", nullable: true, example: "+1234567890"),
                        new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt"),
                        new OA\Property(property: "city", type: "string", nullable: true, example: "Cairo"),
                        new OA\Property(property: "cv", type: "string", format: "binary", nullable: true, description: "CV file (PDF, max 10MB)"),
                        new OA\Property(property: "certificates_json", type: "array", nullable: true, items: new OA\Items(type: "object")),
                        new OA\Property(property: "specializations", type: "array", nullable: true, items: new OA\Items(type: "string"))
                    ]
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
            'phone' => 'sometimes|string',
            'country' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'cv' => 'nullable|file|mimes:pdf|max:10240',
            'certificates_json' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $updateData = $request->only([
            'first_name', 'last_name', 'phone', 'country', 'city', 'specializations'
        ]);

        // Handle CV file upload
        if ($request->hasFile('cv')) {
            try {
                // Delete old CV file if exists
                if ($instructor->cv_url) {
                    $urlParts = parse_url($instructor->cv_url);
                    $path = ltrim($urlParts['path'] ?? '', '/');
                    if (preg_match('#instructors/cv/(.+)$#', $path, $matches)) {
                        $oldFileName = $matches[1];
                        $oldFilePath = 'instructors/cv/' . $oldFileName;
                        if (Storage::disk('public')->exists($oldFilePath)) {
                            Storage::disk('public')->delete($oldFilePath);
                        }
                    }
                }

                // Upload new CV file
                $cvFile = $request->file('cv');
                $fileName = time() . '_' . $instructor->training_center_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $cvFile->getClientOriginalName());
                $cvPath = $cvFile->storeAs('instructors/cv', $fileName, 'public');
                
                if ($cvPath) {
                    // Generate URL using the API route (route is /storage/instructors/cv/{filename} in api.php, so it becomes /api/storage/instructors/cv/{filename})
                    $updateData['cv_url'] = url('/api/storage/instructors/cv/' . $fileName);
                } else {
                    \Log::error('Failed to store CV file', ['instructor_id' => $instructor->id, 'file_name' => $fileName]);
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

        if ($request->has('certificates_json') || $request->has('certificates')) {
            $updateData['certificates_json'] = $request->certificates_json ?? $request->certificates;
        }

        $instructor->update($updateData);

        // Update user account name if first_name or last_name changed
        if ($request->has('first_name') || $request->has('last_name')) {
            $userAccount = User::where('email', $user->email)->first();
            if ($userAccount) {
                $fullName = ($request->first_name ?? $instructor->first_name) . ' ' . ($request->last_name ?? $instructor->last_name);
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
                'certificates_json' => $instructor->certificates_json,
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

