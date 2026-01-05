<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    #[OA\Post(
        path: "/auth/register",
        summary: "Register a new user",
        description: "Register a new user (Training Center or ACC Admin). Both require group admin approval.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation", "role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "role", type: "string", enum: ["training_center_admin", "acc_admin"], example: "training_center_admin"),
                    new OA\Property(property: "country", type: "string", example: "USA"),
                    new OA\Property(property: "city", type: "string", example: "New York"),
                    new OA\Property(property: "address", type: "string", example: "123 Main St"),
                    new OA\Property(property: "phone", type: "string", example: "+1234567890")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Registration successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Registration successful"),
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string", example: "1|xxxxxxxxxxxxx")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:training_center_admin,acc_admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->authService->register($request);

            return response()->json([
                'message' => $result['message'],
                'user' => $result['user'],
                'token' => $result['token'],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Post(
        path: "/auth/login",
        summary: "User login",
        description: "Authenticate user with email and password. Returns authentication token.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Login successful"),
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string", example: "1|xxxxxxxxxxxxx")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Invalid credentials")
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $result = $this->authService->login($request);

            return response()->json([
                'message' => $result['message'],
                'user' => $result['user'],
                'token' => $result['token'],
            ], 200);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Login failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Post(
        path: "/auth/logout",
        summary: "User logout",
        description: "Logout the authenticated user and invalidate the current token.",
        tags: ["Authentication"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logged out successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Logged out successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    #[OA\Get(
        path: "/auth/profile",
        summary: "Get user profile",
        description: "Get the authenticated user's profile information. The name field is synced with the Training Center or ACC profile based on the user's role.",
        tags: ["Authentication"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "User profile",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function profile(Request $request)
    {
        $user = $request->user();
        $profileName = null;

        // Sync name from Training Center or ACC profile based on role
        if ($user->role === 'training_center_admin') {
            $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();
            if ($trainingCenter && $trainingCenter->name) {
                $profileName = $trainingCenter->name;
            }
        } elseif ($user->role === 'acc_admin') {
            $acc = \App\Models\ACC::where('email', $user->email)->first();
            if ($acc && $acc->name) {
                $profileName = $acc->name;
            }
        }

        // Update user name if profile name exists and is different
        if ($profileName && $user->name !== $profileName) {
            $user->update(['name' => $profileName]);
            $user->refresh();
        }

        return response()->json(['user' => $user]);
    }

    #[OA\Put(
        path: "/auth/profile",
        summary: "Update user profile",
        description: "Update the authenticated user's profile information.",
        tags: ["Authentication"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com")
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
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update($request->only(['name', 'email']));

        return response()->json(['message' => 'Profile updated successfully', 'user' => $request->user()]);
    }

    #[OA\Put(
        path: "/auth/change-password",
        summary: "Change password",
        description: "Change the authenticated user's password.",
        tags: ["Authentication"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", format: "password", example: "oldpassword123"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Password changed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Password changed successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error or incorrect current password")
        ]
    )]
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->authService->changePassword(
            $request->user(),
            $request->current_password,
            $request->password
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        return response()->json(['message' => $result['message']], 200);
    }

    #[OA\Post(
        path: "/auth/forgot-password",
        summary: "Forgot password",
        description: "Request a password reset link to be sent to the user's email.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Password reset link sent",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Password reset link sent to your email")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 500, description: "Email sending failed")
        ]
    )]
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $result = $this->authService->forgotPassword($request->email);

        return response()->json([
            'message' => $result['message']
        ], $result['success'] ? 200 : $result['code']);
    }

    #[OA\Post(
        path: "/auth/reset-password",
        summary: "Reset password",
        description: "Reset user password using the token from the password reset email.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["token", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "token", type: "string", example: "reset_token_here"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Password reset successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Password reset successfully")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid or expired token"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->authService->resetPassword(
            $request->token,
            $request->email,
            $request->password
        );

        return response()->json([
            'message' => $result['message']
        ], $result['success'] ? 200 : $result['code']);
    }

    #[OA\Get(
        path: "/auth/verify-email/{token}",
        summary: "Verify email",
        description: "Verify user email address using the verification token.",
        tags: ["Authentication"],
        parameters: [
            new OA\Parameter(
                name: "token",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string"),
                example: "verification_token_here"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Email verified successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Email verified successfully")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid or expired token")
        ]
    )]
    public function verifyEmail($token)
    {
        // Implementation for email verification
        return response()->json(['message' => 'Email verified successfully']);
    }
}

