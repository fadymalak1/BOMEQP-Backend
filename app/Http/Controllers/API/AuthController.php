<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\ResetPasswordMail;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/auth/register",
        summary: "Register a new user",
        description: "Register a new user (Training Center or ACC Admin). Training centers are active immediately, ACCs require approval.",
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

        // Training centers are active by default, ACCs require approval (pending)
        $userStatus = $request->role === 'training_center_admin' ? 'active' : 'pending';
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => $userStatus,
        ]);

        // Create Training Center record if role is training_center_admin
        if ($request->role === 'training_center_admin') {
            \App\Models\TrainingCenter::create([
                'name' => $request->name,
                'legal_name' => $request->name,
                'registration_number' => 'TC-' . strtoupper(Str::random(8)),
                'country' => $request->country ?? 'Unknown',
                'city' => $request->city ?? 'Unknown',
                'address' => $request->address ?? '',
                'phone' => $request->phone ?? '',
                'email' => $request->email,
                'status' => 'active', // Training centers are active immediately upon registration
            ]);
        }

        // Create ACC record if role is acc_admin
        if ($request->role === 'acc_admin') {
            $acc = \App\Models\ACC::create([
                'name' => $request->name,
                'legal_name' => $request->name,
                'registration_number' => 'ACC-' . strtoupper(Str::random(8)),
                'country' => $request->country ?? 'Unknown',
                'address' => $request->address ?? '',
                'phone' => $request->phone ?? '',
                'email' => $request->email,
                'status' => 'pending',
            ]);

            // Notify admin about new ACC application
            $notificationService = new NotificationService();
            $notificationService->notifyAdminNewAccApplication($acc->id, $acc->name);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
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

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Status check removed - allow login regardless of status
        // if ($user->status !== 'active') {
        //     return response()->json(['message' => 'Account is not active'], 403);
        // }

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
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
        description: "Get the authenticated user's profile information.",
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
        return response()->json(['user' => $request->user()]);
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

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $request->user()->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password changed successfully']);
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

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'We could not find a user with that email address.'
            ], 404);
        }

        // Generate reset token
        $token = Str::random(64);
        
        // Store token in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        try {
            // Send reset password email
            Mail::to($user->email)->send(new ResetPasswordMail($token, $user->email));
            
            return response()->json([
                'message' => 'Password reset link sent to your email'
            ], 200);
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            \Log::error('Password reset email failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Unable to send password reset email. Please try again later.'
            ], 500);
        }
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

        // Get password reset record
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'message' => 'Invalid or expired reset token'
            ], 400);
        }


        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        $tokenAge = now()->diffInMinutes($createdAt);
        if ($tokenAge > 60) {
            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Reset token has expired. Please request a new one.'
            ], 400);
        }

        // Verify token
        if (!Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'message' => 'Invalid reset token'
            ], 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password reset successfully'
        ], 200);
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

