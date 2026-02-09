<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SpecialACCAccountsSeeder extends Seeder
{
    /**
     * List of special ACC accounts that will get lifetime subscriptions
     */
    private const SPECIAL_ACC_EMAILS = [
        'support@iaoshuk.com',
        'support@rsles.com',
        'support@bseca.com',
        'support@bilpm.com',
        'support@bsape.com',
        'support@bocaq.com',
        'support@bihhm.com',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'International Organisation of Occupational Safety and Health – UK (IAOSH-UK)',
                'legal_name' => 'International Organisation of Occupational Safety and Health – UK (IAOSH-UK)',
                'email' => 'support@iaoshuk.com',
            ],
            [
                'name' => 'Royal Society for Lifting Engineering Standards (RSLES)',
                'legal_name' => 'Royal Society for Lifting Engineering Standards (RSLES)',
                'email' => 'support@rsles.com',
            ],
            [
                'name' => 'British Society of Evaluation and Certification Auditors (BSECA)',
                'legal_name' => 'British Society of Evaluation and Certification Auditors (BSECA)',
                'email' => 'support@bseca.com',
            ],
            [
                'name' => 'British Institute of Leadership and Project Management (BILPM)',
                'legal_name' => 'British Institute of Leadership and Project Management (BILPM)',
                'email' => 'support@bilpm.com',
            ],
            [
                'name' => 'British Society for Accreditation of Professional Engineers (BSAPE)',
                'legal_name' => 'British Society for Accreditation of Professional Engineers (BSAPE)',
                'email' => 'support@bsape.com',
            ],
            [
                'name' => 'British Organisation for Competence Assurance and Qualifications (BOCAQ)',
                'legal_name' => 'British Organisation for Competence Assurance and Qualifications (BOCAQ)',
                'email' => 'support@bocaq.com',
            ],
            [
                'name' => 'British Institute of Healthcare and Hospital Management (BIHHM)',
                'legal_name' => 'British Institute of Healthcare and Hospital Management (BIHHM)',
                'email' => 'support@bihhm.com',
            ],
        ];

        foreach ($accounts as $accountData) {
            // Check if user already exists
            $user = User::where('email', $accountData['email'])->first();
            
            if (!$user) {
                // Create user account
                $user = User::create([
                    'name' => $accountData['name'],
                    'email' => $accountData['email'],
                    'password' => Hash::make('Password123'),
                    'role' => 'acc_admin',
                    'status' => 'pending', // Will be activated after admin approval
                ]);

                $this->command->info("Created user: {$accountData['email']}");
            } else {
                $this->command->warn("User already exists: {$accountData['email']}");
            }

            // Check if ACC already exists
            $acc = ACC::where('email', $accountData['email'])->first();
            
            if (!$acc) {
                // Create ACC record with minimal required data
                // Users will complete their profiles after login
                $acc = ACC::create([
                    'name' => $accountData['name'],
                    'legal_name' => $accountData['legal_name'],
                    'registration_number' => 'ACC-' . strtoupper(Str::random(8)),
                    'email' => $accountData['email'],
                    'country' => 'United Kingdom', // Default, can be updated later
                    'address' => 'Address to be completed', // Placeholder, will be updated by user
                    'phone' => '+44-000-000-0000', // Placeholder, will be updated by user
                    'status' => 'pending', // Will appear in admin applications list
                    
                    // Set minimal required fields for ACC registration
                    'physical_street' => 'Address to be completed',
                    'physical_city' => 'City to be completed',
                    'physical_country' => 'United Kingdom',
                    'physical_postal_code' => '00000',
                    
                    'mailing_same_as_physical' => true,
                    'mailing_street' => 'Address to be completed',
                    'mailing_city' => 'City to be completed',
                    'mailing_country' => 'United Kingdom',
                    'mailing_postal_code' => '00000',
                    
                    // Primary Contact (minimal required fields)
                    'primary_contact_title' => 'Mr.',
                    'primary_contact_first_name' => 'Contact',
                    'primary_contact_last_name' => 'Person',
                    'primary_contact_email' => $accountData['email'],
                    'primary_contact_country' => 'United Kingdom',
                    'primary_contact_mobile' => '+44-000-000-0000',
                    
                    // Secondary Contact (required for ACC)
                    'secondary_contact_title' => 'Mrs.',
                    'secondary_contact_first_name' => 'Secondary',
                    'secondary_contact_last_name' => 'Contact',
                    'secondary_contact_email' => $accountData['email'],
                    'secondary_contact_country' => 'United Kingdom',
                    'secondary_contact_mobile' => '+44-000-000-0000',
                    
                    // Additional Information
                    'company_gov_registry_number' => 'REG-' . strtoupper(Str::random(8)),
                    
                    // Agreement Checkboxes
                    'agreed_to_receive_communications' => true,
                    'agreed_to_terms_and_conditions' => true,
                ]);

                $this->command->info("Created ACC: {$accountData['name']}");
            } else {
                $this->command->warn("ACC already exists: {$accountData['email']}");
            }
        }

        $this->command->info('Special ACC accounts seeder completed!');
        $this->command->info('These accounts can login with password: Password123');
        $this->command->info('They will appear in admin applications list for approval.');
        $this->command->info('After approval, they will automatically receive lifetime subscriptions.');
    }
}

