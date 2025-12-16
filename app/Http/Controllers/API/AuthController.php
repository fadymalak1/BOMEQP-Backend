<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'pending',
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
                'status' => 'pending',
            ]);
        }

        // Create ACC record if role is acc_admin
        if ($request->role === 'acc_admin') {
            \App\Models\ACC::create([
                'name' => $request->name,
                'legal_name' => $request->name,
                'registration_number' => 'ACC-' . strtoupper(Str::random(8)),
                'country' => $request->country ?? 'Unknown',
                'address' => $request->address ?? '',
                'phone' => $request->phone ?? '',
                'email' => $request->email,
                'status' => 'pending',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

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

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active'], 403);
        }

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

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
        // Implementation for forgot password
        return response()->json(['message' => 'Password reset link sent to your email']);
    }

    public function resetPassword(Request $request)
    {
        // Implementation for reset password
        return response()->json(['message' => 'Password reset successfully']);
    }

    public function verifyEmail($token)
    {
        // Implementation for email verification
        return response()->json(['message' => 'Email verified successfully']);
    }
}

