<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\User;
use Illuminate\Database\Seeder;

class ACCSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'group_admin')->first();

        $accs = [
            [
                'name' => 'International Safety Council',
                'legal_name' => 'International Safety Council Ltd.',
                'registration_number' => 'ISC-2024-001',
                'country' => 'United States',
                'address' => '123 Safety Avenue, New York, NY 10001',
                'phone' => '+1-555-0101',
                'email' => 'contact@internationalsafety.org',
                'website' => 'https://internationalsafety.org',
                'logo_url' => 'https://example.com/logos/isc.png',
                'status' => 'active',
                'registration_fee_paid' => true,
                'registration_fee_amount' => 5000.00,
                'registration_paid_at' => now()->subMonths(6),
                'approved_at' => now()->subMonths(6),
                'approved_by' => $admin->id,
            ],
            [
                'name' => 'Global Technical Academy',
                'legal_name' => 'Global Technical Academy Inc.',
                'registration_number' => 'GTA-2024-002',
                'country' => 'United Kingdom',
                'address' => '456 Tech Street, London, SW1A 1AA',
                'phone' => '+44-20-7946-0958',
                'email' => 'info@globaltechnical.ac.uk',
                'website' => 'https://globaltechnical.ac.uk',
                'logo_url' => 'https://example.com/logos/gta.png',
                'status' => 'active',
                'registration_fee_paid' => true,
                'registration_fee_amount' => 4500.00,
                'registration_paid_at' => now()->subMonths(4),
                'approved_at' => now()->subMonths(4),
                'approved_by' => $admin->id,
            ],
            [
                'name' => 'Arab Quality Institute',
                'legal_name' => 'Arab Quality Institute',
                'registration_number' => 'AQI-2024-003',
                'country' => 'Saudi Arabia',
                'address' => '789 Quality Boulevard, Riyadh 11564',
                'phone' => '+966-11-234-5678',
                'email' => 'contact@arabquality.sa',
                'website' => 'https://arabquality.sa',
                'logo_url' => 'https://example.com/logos/aqi.png',
                'status' => 'active',
                'registration_fee_paid' => true,
                'registration_fee_amount' => 6000.00,
                'registration_paid_at' => now()->subMonths(3),
                'approved_at' => now()->subMonths(3),
                'approved_by' => $admin->id,
            ],
            [
                'name' => 'European Management Board',
                'legal_name' => 'European Management Board GmbH',
                'registration_number' => 'EMB-2024-004',
                'country' => 'Germany',
                'address' => '321 Management Plaza, Berlin 10115',
                'phone' => '+49-30-1234-5678',
                'email' => 'info@europeanmanagement.de',
                'website' => 'https://europeanmanagement.de',
                'logo_url' => 'https://example.com/logos/emb.png',
                'status' => 'pending',
                'registration_fee_paid' => false,
                'registration_fee_amount' => 5500.00,
                'registration_paid_at' => null,
                'approved_at' => null,
                'approved_by' => null,
            ],
        ];

        foreach ($accs as $acc) {
            ACC::firstOrCreate(
                ['registration_number' => $acc['registration_number']],
                $acc
            );
        }
    }
}

