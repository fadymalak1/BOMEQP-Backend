<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\TrainingCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
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

    #[OA\Put(
        path: "/training-center/profile",
        summary: "Update training center profile",
        description: "Update the authenticated training center's profile information. All fields are optional.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
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
                    new OA\Property(property: "logo_url", type: "string", format: "url", example: "https://example.com/logo.png", nullable: true),
                ]
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
            new OA\Response(response: 404, description: "Training center not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request)
    {
        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Validate input
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|string|max:255',
            'registration_number' => 'sometimes|string|max:255|unique:training_centers,registration_number,' . $trainingCenter->id,
            'country' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:training_centers,email,' . $trainingCenter->id,
            'website' => 'nullable|string|url|max:255',
            'logo_url' => 'nullable|string|url|max:500',
        ]);

        try {
            // Get only the fillable fields that are present in the request
            $updateData = $request->only([
                'name',
                'legal_name',
                'registration_number',
                'country',
                'city',
                'address',
                'phone',
                'email',
                'website',
                'logo_url',
            ]);

            // Filter out null values to only update provided fields
            $updateData = array_filter($updateData, function ($value) {
                return $value !== null;
            });

            $trainingCenter->update($updateData);

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

