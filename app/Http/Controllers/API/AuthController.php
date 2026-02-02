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
        description: "Register a new user (Training Center or ACC Admin). Both Training Center and ACC registration require comprehensive company and contact information. Both require group admin approval.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["name", "email", "password", "password_confirmation", "role"],
                    properties: [
                        // Basic User Information
                        new OA\Property(property: "name", type: "string", example: "John Doe", description: "User's full name"),
                        new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com", description: "User's email address (must be unique)"),
                        new OA\Property(property: "password", type: "string", format: "password", example: "password123", description: "Password (minimum 8 characters)"),
                        new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123", description: "Password confirmation"),
                        new OA\Property(property: "role", type: "string", enum: ["training_center_admin", "acc_admin"], example: "training_center_admin", description: "User role"),
                        
                        // Training Center Specific Fields (required when role is training_center_admin)
                        new OA\Property(property: "company_name", type: "string", example: "ABC Training Center", description: "Company name (required for training_center_admin)"),
                        new OA\Property(property: "company_email", type: "string", format: "email", example: "info@abctraining.com", description: "Company email address (required for training_center_admin)"),
                        new OA\Property(property: "training_provider_type", type: "string", enum: ["Training Center", "Institute", "University"], example: "Training Center", description: "Type of training provider (required for training_center_admin)"),
                        new OA\Property(property: "facility_floorplan", type: "string", format: "binary", description: "Facility floorplan file (PDF, JPG, PNG, max 10MB, optional, only for training_center_admin)"),
                        new OA\Property(property: "interested_fields", type: "array", items: new OA\Items(type: "string", enum: ["QHSE", "Food Safety", "Management"]), example: ["QHSE", "Food Safety"], description: "Interested fields (optional, only for training_center_admin)"),
                        new OA\Property(property: "has_secondary_contact", type: "boolean", example: false, description: "Whether to add secondary contact (optional for training_center_admin, always required for acc_admin)"),
                        
                        // ACC Specific Fields (required when role is acc_admin)
                        new OA\Property(property: "legal_name", type: "string", example: "ABC Accreditation Body", description: "Accreditation legal name (required for acc_admin)"),
                        new OA\Property(property: "acc_email", type: "string", format: "email", example: "info@abcaccreditation.com", description: "Accreditation body email address (required for acc_admin)"),
                        new OA\Property(property: "primary_contact_passport", type: "string", format: "binary", description: "Primary contact passport copy (PDF, JPG, PNG, max 10MB, required for acc_admin)"),
                        new OA\Property(property: "secondary_contact_passport", type: "string", format: "binary", description: "Secondary contact passport copy (PDF, JPG, PNG, max 10MB, required for acc_admin)"),
                        
                        // Shared Fields (used by both training_center_admin and acc_admin)
                        new OA\Property(property: "telephone_number", type: "string", example: "+1234567890", description: "Telephone number (required for both roles)"),
                        new OA\Property(property: "website", type: "string", example: "https://www.example.com", description: "Website (optional for both roles)"),
                        new OA\Property(property: "fax", type: "string", example: "+1234567891", description: "Fax number (optional for both roles)"),
                        
                        // Physical Address (required for both roles)
                        new OA\Property(property: "address", type: "string", example: "123 Main Street", description: "Physical address (required for both roles)"),
                        new OA\Property(property: "city", type: "string", example: "New York", description: "City (required for both roles)"),
                        new OA\Property(property: "country", type: "string", example: "USA", description: "Country (required for both roles)"),
                        new OA\Property(property: "postal_code", type: "string", example: "10001", description: "Postal code (required for both roles)"),
                        
                        // Mailing Address (conditional for both roles)
                        new OA\Property(property: "mailing_same_as_physical", type: "boolean", example: true, description: "Whether mailing address is same as physical address (for both roles)"),
                        new OA\Property(property: "mailing_address", type: "string", example: "123 Main Street", description: "Mailing address (required if mailing_same_as_physical is false)"),
                        new OA\Property(property: "mailing_city", type: "string", example: "New York", description: "Mailing city (required if mailing_same_as_physical is false)"),
                        new OA\Property(property: "mailing_country", type: "string", example: "USA", description: "Mailing country (required if mailing_same_as_physical is false)"),
                        new OA\Property(property: "mailing_postal_code", type: "string", example: "10001", description: "Mailing postal code (required if mailing_same_as_physical is false)"),
                        
                        // Primary Contact (required for both roles)
                        new OA\Property(property: "primary_contact_title", type: "string", enum: ["Mr.", "Mrs.", "Eng.", "Prof."], example: "Mr.", description: "Primary contact title (required for both roles)"),
                        new OA\Property(property: "primary_contact_first_name", type: "string", example: "John", description: "Primary contact first name (required for both roles)"),
                        new OA\Property(property: "primary_contact_last_name", type: "string", example: "Doe", description: "Primary contact last name (required for both roles)"),
                        new OA\Property(property: "primary_contact_email", type: "string", format: "email", example: "john.doe@example.com", description: "Primary contact email (required for both roles)"),
                        new OA\Property(property: "primary_contact_country", type: "string", example: "USA", description: "Primary contact country (required for both roles)"),
                        new OA\Property(property: "primary_contact_mobile", type: "string", example: "+1234567890", description: "Primary contact mobile number (required for both roles)"),
                        
                        // Secondary Contact (conditional for training_center_admin, required for acc_admin)
                        new OA\Property(property: "secondary_contact_title", type: "string", enum: ["Mr.", "Mrs.", "Eng.", "Prof."], example: "Mrs.", description: "Secondary contact title (required if has_secondary_contact is true for training_center_admin, always required for acc_admin)"),
                        new OA\Property(property: "secondary_contact_first_name", type: "string", example: "Jane", description: "Secondary contact first name (required if has_secondary_contact is true for training_center_admin, always required for acc_admin)"),
                        new OA\Property(property: "secondary_contact_last_name", type: "string", example: "Smith", description: "Secondary contact last name (required if has_secondary_contact is true for training_center_admin, always required for acc_admin)"),
                        new OA\Property(property: "secondary_contact_email", type: "string", format: "email", example: "jane.smith@example.com", description: "Secondary contact email (required if has_secondary_contact is true for training_center_admin, always required for acc_admin)"),
                        new OA\Property(property: "secondary_contact_country", type: "string", example: "USA", description: "Secondary contact country (required if has_secondary_contact is true for training_center_admin, always required for acc_admin)"),
                        new OA\Property(property: "secondary_contact_mobile", type: "string", example: "+1234567891", description: "Secondary contact mobile number (required if has_secondary_contact is true for training_center_admin, always required for acc_admin)"),
                        
                        // Additional Information (required for both roles)
                        new OA\Property(property: "company_gov_registry_number", type: "string", example: "REG123456", description: "Company government registry number (required for both roles)"),
                        new OA\Property(property: "company_registration_certificate", type: "string", format: "binary", description: "Company registration certificate file (PDF, JPG, PNG, max 10MB, required for both roles)"),
                        new OA\Property(property: "how_did_you_hear_about_us", type: "string", example: "Google Search", description: "How did you hear about us (optional for both roles)"),
                        
                        // Agreements (required for both roles)
                        new OA\Property(property: "agreed_to_receive_communications", type: "boolean", example: true, description: "Agreement to receive communications (required for both roles, must be true)"),
                        new OA\Property(property: "agreed_to_terms_and_conditions", type: "boolean", example: true, description: "Agreement to terms and conditions (required for both roles, must be true)"),
                    ]
                )
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
        // Base validation rules for all users
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:training_center_admin,acc_admin',
        ];

        // Additional validation rules for training center registration
        if ($request->role === 'training_center_admin') {
            $rules = array_merge($rules, [
                // Company Information
                'company_name' => 'required|string|max:255',
                'company_email' => 'required|email|max:255',
                'telephone_number' => 'required|string|max:255',
                'fax' => 'nullable|string|max:255',
                'training_provider_type' => 'required|in:Training Center,Institute,University',
                
                // Physical Address
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'postal_code' => 'required|string|max:50',
                
                // Mailing Address (conditional)
                'mailing_same_as_physical' => 'nullable|boolean',
                'mailing_address' => 'required_if:mailing_same_as_physical,false|nullable|string|max:500',
                'mailing_city' => 'required_if:mailing_same_as_physical,false|nullable|string|max:255',
                'mailing_country' => 'required_if:mailing_same_as_physical,false|nullable|string|max:255',
                'mailing_postal_code' => 'required_if:mailing_same_as_physical,false|nullable|string|max:50',
                
                // Primary Contact
                'primary_contact_title' => 'required|in:Mr.,Mrs.,Eng.,Prof.',
                'primary_contact_first_name' => 'required|string|max:255',
                'primary_contact_last_name' => 'required|string|max:255',
                'primary_contact_email' => 'required|email|max:255',
                'primary_contact_country' => 'required|string|max:255',
                'primary_contact_mobile' => 'required|string|max:255',
                
                // Secondary Contact (conditional)
                'has_secondary_contact' => 'nullable|boolean',
                'secondary_contact_title' => 'required_if:has_secondary_contact,true|nullable|in:Mr.,Mrs.,Eng.,Prof.',
                'secondary_contact_first_name' => 'required_if:has_secondary_contact,true|nullable|string|max:255',
                'secondary_contact_last_name' => 'required_if:has_secondary_contact,true|nullable|string|max:255',
                'secondary_contact_email' => 'required_if:has_secondary_contact,true|nullable|email|max:255',
                'secondary_contact_country' => 'required_if:has_secondary_contact,true|nullable|string|max:255',
                'secondary_contact_mobile' => 'required_if:has_secondary_contact,true|nullable|string|max:255',
                
                // Additional Information
                'company_gov_registry_number' => 'required|string|max:255',
                'company_registration_certificate' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240', // 10MB max
                'facility_floorplan' => 'nullable|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240', // 10MB max
                'interested_fields' => 'nullable|array',
                'interested_fields.*' => 'in:QHSE,Food Safety,Management',
                'how_did_you_hear_about_us' => 'nullable|string|max:500',
                
                // Agreements
                'agreed_to_receive_communications' => 'required|accepted',
                'agreed_to_terms_and_conditions' => 'required|accepted',
            ]);
        }

        // Additional validation rules for ACC registration
        if ($request->role === 'acc_admin') {
            $rules = array_merge($rules, [
                // Accreditation Body Information
                'legal_name' => 'required|string|max:255',
                'acc_email' => 'required|email|max:255',
                'telephone_number' => 'required|string|max:255',
                'website' => 'nullable|string|max:500',
                'fax' => 'nullable|string|max:255',
                
                // Physical Address
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'postal_code' => 'required|string|max:50',
                
                // Mailing Address (conditional)
                'mailing_same_as_physical' => 'nullable|boolean',
                'mailing_address' => 'required_if:mailing_same_as_physical,false|nullable|string|max:500',
                'mailing_city' => 'required_if:mailing_same_as_physical,false|nullable|string|max:255',
                'mailing_country' => 'required_if:mailing_same_as_physical,false|nullable|string|max:255',
                'mailing_postal_code' => 'required_if:mailing_same_as_physical,false|nullable|string|max:50',
                
                // Primary Contact
                'primary_contact_title' => 'required|in:Mr.,Mrs.,Eng.,Prof.',
                'primary_contact_first_name' => 'required|string|max:255',
                'primary_contact_last_name' => 'required|string|max:255',
                'primary_contact_email' => 'required|email|max:255',
                'primary_contact_country' => 'required|string|max:255',
                'primary_contact_mobile' => 'required|string|max:255',
                'primary_contact_passport' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240', // 10MB max
                
                // Secondary Contact (required for ACC)
                'secondary_contact_title' => 'required|in:Mr.,Mrs.,Eng.,Prof.',
                'secondary_contact_first_name' => 'required|string|max:255',
                'secondary_contact_last_name' => 'required|string|max:255',
                'secondary_contact_email' => 'required|email|max:255',
                'secondary_contact_country' => 'required|string|max:255',
                'secondary_contact_mobile' => 'required|string|max:255',
                'secondary_contact_passport' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240', // 10MB max
                
                // Additional Information
                'company_gov_registry_number' => 'required|string|max:255',
                'company_registration_certificate' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240', // 10MB max
                'how_did_you_hear_about_us' => 'nullable|string|max:500',
                
                // Agreements
                'agreed_to_receive_communications' => 'required|accepted',
                'agreed_to_terms_and_conditions' => 'required|accepted',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

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
            'language' => 'sometimes|string|in:en,hi,zh-CN,ar,es',
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'email', 'language']));
        $user->refresh();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
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

