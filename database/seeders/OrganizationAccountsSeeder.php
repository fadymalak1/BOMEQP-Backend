<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'International Organisation of Occupational Safety and Health – UK (IAOSH-UK)',
                'email' => 'support@iaoshuk.com',
            ],
            [
                'name' => 'Royal Society for Lifting Engineering Standards (RSLES)',
                'email' => 'support@rsles.com',
            ],
            [
                'name' => 'British Society of Evaluation and Certification Auditors (BSECA)',
                'email' => 'support@bseca.com',
            ],
            [
                'name' => 'British Institute of Leadership and Project Management (BILPM)',
                'email' => 'support@bilpm.com',
            ],
            [
                'name' => 'British Society for Accreditation of Professional Engineers (BSAPE)',
                'email' => 'support@bsape.com',
            ],
            [
                'name' => 'British Organisation for Competence Assurance and Qualifications (BOCAQ)',
                'email' => 'support@bocaq.com',
            ],
            [
                'name' => 'British Institute of Healthcare and Hospital Management (BIHHM)',
                'email' => 'support@bihhm.com',
            ],
        ];

        foreach ($organizations as $org) {
            if (!User::where('email', $org['email'])->exists()) {
                User::create([
                    'name' => $org['name'],
                    'email' => $org['email'],
                    'password' => Hash::make('Password123'),
                    'role' => 'acc_admin',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $this->command->info("Created account for: {$org['name']}");
                $this->command->info("Email: {$org['email']}");
                $this->command->info("Password: Password123");
                $this->command->info('---');
            } else {
                $this->command->warn("Account already exists for: {$org['email']}");
            }
        }

        $this->command->info('Organization accounts seeding completed!');
    }
}

