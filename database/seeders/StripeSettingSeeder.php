<?php

namespace Database\Seeders;

use App\Models\StripeSetting;
use Illuminate\Database\Seeder;

class StripeSettingSeeder extends Seeder
{
    public function run(): void
    {
        // Sandbox settings (for testing)
        StripeSetting::create([
            'environment' => 'sandbox',
            'publishable_key' => 'pk_test_51ExampleKey',
            'secret_key' => 'sk_test_51ExampleSecretKey',
            'webhook_secret' => 'whsec_exampleWebhookSecret',
            'is_active' => true,
            'description' => 'Stripe sandbox/test environment settings',
        ]);

        // Live settings (disabled by default - admin needs to configure)
        StripeSetting::create([
            'environment' => 'live',
            'publishable_key' => null,
            'secret_key' => null,
            'webhook_secret' => null,
            'is_active' => false,
            'description' => 'Stripe live/production environment settings',
        ]);
    }
}

