<?php

namespace Database\Seeders;

use App\Models\TrainingCenter;
use Illuminate\Database\Seeder;

class TrainingCenterSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = [
            [
                'name' => 'Elite Training Solutions',
                'legal_name' => 'Elite Training Solutions LLC',
                'registration_number' => 'ETS-2024-001',
                'country' => 'United Arab Emirates',
                'city' => 'Dubai',
                'address' => '100 Training Center Road, Dubai, UAE',
                'phone' => '+971-4-123-4567',
                'email' => 'info@elitetraining.ae',
                'website' => 'https://elitetraining.ae',
                'logo_url' => 'https://example.com/logos/elite.png',
                'referred_by_group' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Professional Development Hub',
                'legal_name' => 'Professional Development Hub Ltd.',
                'registration_number' => 'PDH-2024-002',
                'country' => 'Saudi Arabia',
                'city' => 'Jeddah',
                'address' => '200 Education Street, Jeddah 21461',
                'phone' => '+966-12-345-6789',
                'email' => 'contact@pdhub.sa',
                'website' => 'https://pdhub.sa',
                'logo_url' => 'https://example.com/logos/pdhub.png',
                'referred_by_group' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Advanced Skills Academy',
                'legal_name' => 'Advanced Skills Academy Inc.',
                'registration_number' => 'ASA-2024-003',
                'country' => 'Qatar',
                'city' => 'Doha',
                'address' => '300 Skills Avenue, Doha, Qatar',
                'phone' => '+974-1234-5678',
                'email' => 'info@advancedskills.qa',
                'website' => 'https://advancedskills.qa',
                'logo_url' => 'https://example.com/logos/asa.png',
                'referred_by_group' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Global Learning Center',
                'legal_name' => 'Global Learning Center',
                'registration_number' => 'GLC-2024-004',
                'country' => 'Kuwait',
                'city' => 'Kuwait City',
                'address' => '400 Learning Boulevard, Kuwait City',
                'phone' => '+965-1234-5678',
                'email' => 'contact@globallearning.kw',
                'website' => 'https://globallearning.kw',
                'logo_url' => 'https://example.com/logos/glc.png',
                'referred_by_group' => false,
                'status' => 'active',
            ],
        ];

        foreach ($trainingCenters as $center) {
            TrainingCenter::create($center);
        }
    }
}

