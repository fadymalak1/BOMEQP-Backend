<?php

namespace App\Services;

use App\Models\User;
use App\Mail\ResetPasswordMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Register a new user
     *
     * @param Request $request
     * @return array
     */
    public function register(Request $request): array
    {
        try {
            DB::beginTransaction();

            // Both training centers and ACCs require approval (pending)
            $userStatus = 'pending';
            
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $userStatus,
            ]);

            // Create Training Center record if role is training_center_admin
            if ($request->role === 'training_center_admin') {
                $trainingCenter = \App\Models\TrainingCenter::create([
                    'name' => $request->name,
                    'legal_name' => $request->name,
                    'registration_number' => 'TC-' . strtoupper(Str::random(8)),
                    'country' => $request->country ?? 'Unknown',
                    'city' => $request->city ?? 'Unknown',
                    'address' => $request->address ?? '',
                    'phone' => $request->phone ?? '',
                    'email' => $request->email,
                    'status' => 'pending', // Training centers require group admin approval
                ]);

                // Notify admin about new training center application
                $this->notificationService->notifyAdminNewTrainingCenterApplication($trainingCenter->id, $trainingCenter->name);
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
                $this->notificationService->notifyAdminNewAccApplication($acc->id, $acc->name);
            }

            DB::commit();

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
                'message' => 'Registration successful'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Login user
     *
     * @param Request $request
     * @return array
     * @throws ValidationException
     */
    public function login(Request $request): array
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful'
        ];
    }

    /**
     * Change password
     *
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     * @return array
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect',
                'code' => 422
            ];
        }

        $user->update(['password' => Hash::make($newPassword)]);

        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    }

    /**
     * Request password reset
     *
     * @param string $email
     * @return array
     */
    public function forgotPassword(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'We could not find a user with that email address.',
                'code' => 404
            ];
        }

        // Generate reset token
        $token = Str::random(64);
        
        // Store token in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        try {
            // Send reset password email
            Mail::to($user->email)->send(new ResetPasswordMail($token, $user->email));
            
            return [
                'success' => true,
                'message' => 'Password reset link sent to your email'
            ];
        } catch (\Exception $e) {
            Log::error('Password reset email failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to send password reset email. Please try again later.',
                'code' => 500
            ];
        }
    }

    /**
     * Reset password using token
     *
     * @param string $token
     * @param string $email
     * @param string $password
     * @return array
     */
    public function resetPassword(string $token, string $email, string $password): array
    {
        // Get password reset record
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$passwordReset) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token',
                'code' => 400
            ];
        }

        // Check token expiration (60 minutes)
        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        $tokenAge = now()->diffInMinutes($createdAt);
        if ($tokenAge > 60) {
            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return [
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
                'code' => 400
            ];
        }

        // Verify token
        if (!Hash::check($token, $passwordReset->token)) {
            return [
                'success' => false,
                'message' => 'Invalid reset token',
                'code' => 400
            ];
        }

        // Update user password
        $user = User::where('email', $email)->first();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found',
                'code' => 404
            ];
        }

        $user->password = Hash::make($password);
        $user->save();

        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return [
            'success' => true,
            'message' => 'Password reset successfully'
        ];
    }
}

