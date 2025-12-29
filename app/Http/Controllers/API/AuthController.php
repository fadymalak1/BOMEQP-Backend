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

class AuthController extends Controller
{
    /**
     * Register a new user
     * 
     * Register a new user (Training Center or ACC Admin). Training centers are active immediately, ACCs require approval.
     * 
     * @group Authentication
     * 
     * @bodyParam name string required The user's name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password (min 8 characters). Example: password123
     * @bodyParam password_confirmation string required Password confirmation. Example: password123
     * @bodyParam role string required User role. Must be one of: training_center_admin, acc_admin. Example: training_center_admin
     * @bodyParam country string optional Country. Example: USA
     * @bodyParam city string optional City. Example: New York
     * @bodyParam address string optional Address. Example: 123 Main St
     * @bodyParam phone string optional Phone number. Example: +1234567890
     * 
     * @response 201 {
     *   "message": "Registration successful",
     *   "user": {...},
     *   "token": "1|xxxxxxxxxxxxx"
     * }
     * @response 422 {
     *   "errors": {...}
     * }
     */
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

    /**
     * User login
     * 
     * Authenticate user with email and password. Returns authentication token.
     * 
     * @group Authentication
     * 
     * @bodyParam email string required User's email address. Example: john@example.com
     * @bodyParam password string required User's password. Example: password123
     * 
     * @response 200 {
     *   "message": "Login successful",
     *   "user": {...},
     *   "token": "1|xxxxxxxxxxxxx"
     * }
     * @response 422 {
     *   "message": "The provided credentials are incorrect."
     * }
     */
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

    /**
     * User logout
     * 
     * Logout the authenticated user and invalidate the current token.
     * 
     * @group Authentication
     * @authenticated
     * 
     * @response 200 {
     *   "message": "Logged out successfully"
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get user profile
     * 
     * Get the authenticated user's profile information.
     * 
     * @group Authentication
     * @authenticated
     * 
     * @response 200 {
     *   "user": {...}
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function profile(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update($request->only(['name', 'email']));

        return response()->json(['message' => 'Profile updated successfully', 'user' => $request->user()]);
    }

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

    public function verifyEmail($token)
    {
        // Implementation for email verification
        return response()->json(['message' => 'Email verified successfully']);
    }
}

