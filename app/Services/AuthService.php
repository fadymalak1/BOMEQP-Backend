<?php

namespace App\Services;

use App\Models\User;
use App\Mail\ResetPasswordMail;
use App\Services\FileUploadService;
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
    protected FileUploadService $fileUploadService;

    public function __construct(NotificationService $notificationService, FileUploadService $fileUploadService)
    {
        $this->notificationService = $notificationService;
        $this->fileUploadService = $fileUploadService;
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
                // Handle mailing address - if same as physical, copy physical address fields
                $mailingAddress = $request->mailing_address;
                $mailingCity = $request->mailing_city;
                $mailingCountry = $request->mailing_country;
                $mailingPostalCode = $request->mailing_postal_code;
                
                if ($request->mailing_same_as_physical) {
                    $mailingAddress = $request->address;
                    $mailingCity = $request->city;
                    $mailingCountry = $request->country;
                    $mailingPostalCode = $request->postal_code;
                }
                
                // Create training center first to get the ID
                $trainingCenter = \App\Models\TrainingCenter::create([
                    'name' => $request->company_name,
                    'legal_name' => $request->company_name,
                    'registration_number' => 'TC-' . strtoupper(Str::random(8)),
                    'country' => $request->country,
                    'city' => $request->city,
                    'address' => $request->address,
                    'phone' => $request->telephone_number,
                    'email' => $request->company_email,
                    'website' => $request->website ?? null,
                    'status' => 'pending', // Training centers require group admin approval
                    
                    // Company Information
                    'fax' => $request->fax ?? null,
                    'training_provider_type' => $request->training_provider_type,
                    
                    // Physical Address
                    'physical_postal_code' => $request->postal_code,
                    
                    // Mailing Address
                    'mailing_same_as_physical' => $request->mailing_same_as_physical ?? false,
                    'mailing_address' => $mailingAddress,
                    'mailing_city' => $mailingCity,
                    'mailing_country' => $mailingCountry,
                    'mailing_postal_code' => $mailingPostalCode,
                    
                    // Primary Contact
                    'primary_contact_title' => $request->primary_contact_title,
                    'primary_contact_first_name' => $request->primary_contact_first_name,
                    'primary_contact_last_name' => $request->primary_contact_last_name,
                    'primary_contact_email' => $request->primary_contact_email,
                    'primary_contact_country' => $request->primary_contact_country,
                    'primary_contact_mobile' => $request->primary_contact_mobile,
                    
                    // Secondary Contact
                    'has_secondary_contact' => $request->has_secondary_contact ?? false,
                    'secondary_contact_title' => $request->secondary_contact_title ?? null,
                    'secondary_contact_first_name' => $request->secondary_contact_first_name ?? null,
                    'secondary_contact_last_name' => $request->secondary_contact_last_name ?? null,
                    'secondary_contact_email' => $request->secondary_contact_email ?? null,
                    'secondary_contact_country' => $request->secondary_contact_country ?? null,
                    'secondary_contact_mobile' => $request->secondary_contact_mobile ?? null,
                    
                    // Additional Information
                    'company_gov_registry_number' => $request->company_gov_registry_number,
                    'interested_fields' => $request->interested_fields ?? null,
                    'how_did_you_hear_about_us' => $request->how_did_you_hear_about_us ?? null,
                    
                    // Agreement Checkboxes
                    'agreed_to_receive_communications' => $request->agreed_to_receive_communications ?? false,
                    'agreed_to_terms_and_conditions' => $request->agreed_to_terms_and_conditions ?? false,
                ]);
                
                // Handle file uploads after training center is created
                $updateData = [];
                
                if ($request->hasFile('company_registration_certificate')) {
                    $certResult = $this->fileUploadService->uploadDocument(
                        $request->file('company_registration_certificate'),
                        $trainingCenter->id,
                        'training_center',
                        'registration_certificate'
                    );
                    if ($certResult['success']) {
                        $updateData['company_registration_certificate_url'] = $certResult['url'];
                    }
                }
                
                if ($request->hasFile('facility_floorplan')) {
                    $floorplanResult = $this->fileUploadService->uploadDocument(
                        $request->file('facility_floorplan'),
                        $trainingCenter->id,
                        'training_center',
                        'floorplan'
                    );
                    if ($floorplanResult['success']) {
                        $updateData['facility_floorplan_url'] = $floorplanResult['url'];
                    }
                }
                
                // Update training center with file URLs if any files were uploaded
                if (!empty($updateData)) {
                    $trainingCenter->update($updateData);
                }

                // Notify admin about new training center application
                $this->notificationService->notifyAdminNewTrainingCenterApplication($trainingCenter->id, $trainingCenter->name);
            }

            // Create ACC record if role is acc_admin
            if ($request->role === 'acc_admin') {
                // Handle mailing address - if same as physical, copy physical address fields
                $mailingAddress = $request->mailing_address;
                $mailingCity = $request->mailing_city;
                $mailingCountry = $request->mailing_country;
                $mailingPostalCode = $request->mailing_postal_code;
                
                if ($request->mailing_same_as_physical) {
                    $mailingAddress = $request->address;
                    $mailingCity = $request->city;
                    $mailingCountry = $request->country;
                    $mailingPostalCode = $request->postal_code;
                }
                
                // Create ACC first to get the ID
                $acc = \App\Models\ACC::create([
                    'name' => $request->legal_name,
                    'legal_name' => $request->legal_name,
                    'registration_number' => 'ACC-' . strtoupper(Str::random(8)),
                    'email' => $request->acc_email,
                    'phone' => $request->telephone_number,
                    'website' => $request->website ?? null,
                    'status' => 'pending',
                    
                    // Company Information
                    'fax' => $request->fax ?? null,
                    
                    // Physical Address
                    'address' => $request->address, // Legacy field for backward compatibility
                    'country' => $request->country, // Legacy field for backward compatibility
                    'physical_street' => $request->address,
                    'physical_city' => $request->city,
                    'physical_country' => $request->country,
                    'physical_postal_code' => $request->postal_code,
                    
                    // Mailing Address
                    'mailing_same_as_physical' => $request->mailing_same_as_physical ?? false,
                    'mailing_street' => $mailingAddress,
                    'mailing_city' => $mailingCity,
                    'mailing_country' => $mailingCountry,
                    'mailing_postal_code' => $mailingPostalCode,
                    
                    // Primary Contact
                    'primary_contact_title' => $request->primary_contact_title,
                    'primary_contact_first_name' => $request->primary_contact_first_name,
                    'primary_contact_last_name' => $request->primary_contact_last_name,
                    'primary_contact_email' => $request->primary_contact_email,
                    'primary_contact_country' => $request->primary_contact_country,
                    'primary_contact_mobile' => $request->primary_contact_mobile,
                    
                    // Secondary Contact (required for ACC)
                    'secondary_contact_title' => $request->secondary_contact_title,
                    'secondary_contact_first_name' => $request->secondary_contact_first_name,
                    'secondary_contact_last_name' => $request->secondary_contact_last_name,
                    'secondary_contact_email' => $request->secondary_contact_email,
                    'secondary_contact_country' => $request->secondary_contact_country,
                    'secondary_contact_mobile' => $request->secondary_contact_mobile,
                    
                    // Additional Information
                    'company_gov_registry_number' => $request->company_gov_registry_number,
                    'how_did_you_hear_about_us' => $request->how_did_you_hear_about_us ?? null,
                    
                    // Agreement Checkboxes
                    'agreed_to_receive_communications' => $request->agreed_to_receive_communications ?? false,
                    'agreed_to_terms_and_conditions' => $request->agreed_to_terms_and_conditions ?? false,
                ]);
                
                // Handle file uploads after ACC is created
                $updateData = [];
                
                // Primary contact passport
                if ($request->hasFile('primary_contact_passport')) {
                    $passportResult = $this->fileUploadService->uploadDocument(
                        $request->file('primary_contact_passport'),
                        $acc->id,
                        'acc',
                        'passport'
                    );
                    if ($passportResult['success']) {
                        $updateData['primary_contact_passport_url'] = $passportResult['url'];
                    }
                }
                
                // Secondary contact passport
                if ($request->hasFile('secondary_contact_passport')) {
                    $passportResult = $this->fileUploadService->uploadDocument(
                        $request->file('secondary_contact_passport'),
                        $acc->id,
                        'acc',
                        'passport'
                    );
                    if ($passportResult['success']) {
                        $updateData['secondary_contact_passport_url'] = $passportResult['url'];
                    }
                }
                
                // Company registration certificate
                if ($request->hasFile('company_registration_certificate')) {
                    $certResult = $this->fileUploadService->uploadDocument(
                        $request->file('company_registration_certificate'),
                        $acc->id,
                        'acc',
                        'registration_certificate'
                    );
                    if ($certResult['success']) {
                        $updateData['company_registration_certificate_url'] = $certResult['url'];
                    }
                }
                
                // Update ACC with file URLs if any files were uploaded
                if (!empty($updateData)) {
                    $acc->update($updateData);
                }

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

