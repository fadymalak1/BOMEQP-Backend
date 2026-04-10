<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * `name` is the short display name (acronym-style); full legal titles are in SpecialACCAccountsSeeder legal_name for ACC rows.
     */
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'IAOSH-UK',
                'email' => 'support@iaoshuk.com',
            ],
            [
                'name' => 'RSLES',
                'email' => 'support@rsles.com',
            ],
            [
                'name' => 'BSECA',
                'email' => 'support@bseca.com',
            ],
            [
                'name' => 'BILPM',
                'email' => 'support@bilpm.com',
            ],
            [
                'name' => 'BSAPE',
                'email' => 'support@bsape.com',
            ],
            [
                'name' => 'BOCAQ',
                'email' => 'support@bocaq.com',
            ],
            [
                'name' => 'BIHHM',
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
