<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
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
                        new OA\Property(property: "profile", type: "object")
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
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $userAccount = User::where('email', $user->email)->first();

        return response()->json([
            'profile' => [
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
                'user' => $userAccount ? [
                    'id' => $userAccount->id,
                    'name' => $userAccount->name,
                    'email' => $userAccount->email,
                    'role' => $userAccount->role,
                    'status' => $userAccount->status,
                ] : null,
                'created_at' => $acc->created_at,
                'updated_at' => $acc->updated_at,
            ]
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
            content: new OA\MediaType(
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
                        new OA\Property(property: "stripe_account_id", type: "string", nullable: true, example: "acct_xxxxxxxxxxxxx", description: "Stripe Connect account ID (starts with 'acct_')")
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

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
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
        ]);

        $updateData = [];

        // Only include fields that are actually provided and not empty
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

        // Only update if there's data to update
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

        // Update user account name if name changed
        if (isset($updateData['name'])) {
            $userAccount = User::where('email', $user->email)->first();
            if ($userAccount) {
                $userAccount->update(['name' => $acc->name]);
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => [
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
                'created_at' => $acc->created_at,
                'updated_at' => $acc->updated_at,
            ]
        ]);
    }
}

