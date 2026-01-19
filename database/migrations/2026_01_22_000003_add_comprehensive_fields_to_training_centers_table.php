<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_centers', function (Blueprint $table) {
            // Company Information
            $table->string('fax')->nullable()->after('phone');
            $table->enum('training_provider_type', ['Training Center', 'Institute', 'University'])->nullable()->after('website');
            
            // Physical Address - using existing address, city, country, adding postal code
            $table->string('physical_postal_code')->nullable()->after('address');
            
            // Mailing Address
            $table->boolean('mailing_same_as_physical')->default(false)->after('physical_postal_code');
            $table->text('mailing_address')->nullable()->after('mailing_same_as_physical');
            $table->string('mailing_city')->nullable()->after('mailing_address');
            $table->string('mailing_country')->nullable()->after('mailing_city');
            $table->string('mailing_postal_code')->nullable()->after('mailing_country');
            
            // Primary Contact
            $table->enum('primary_contact_title', ['Mr.', 'Mrs.', 'Eng.', 'Prof.'])->nullable()->after('mailing_postal_code');
            $table->string('primary_contact_first_name')->nullable()->after('primary_contact_title');
            $table->string('primary_contact_last_name')->nullable()->after('primary_contact_first_name');
            $table->string('primary_contact_email')->nullable()->after('primary_contact_last_name');
            $table->string('primary_contact_country')->nullable()->after('primary_contact_email');
            $table->string('primary_contact_mobile')->nullable()->after('primary_contact_country');
            
            // Secondary Contact
            $table->boolean('has_secondary_contact')->default(false)->after('primary_contact_mobile');
            $table->enum('secondary_contact_title', ['Mr.', 'Mrs.', 'Eng.', 'Prof.'])->nullable()->after('has_secondary_contact');
            $table->string('secondary_contact_first_name')->nullable()->after('secondary_contact_title');
            $table->string('secondary_contact_last_name')->nullable()->after('secondary_contact_first_name');
            $table->string('secondary_contact_email')->nullable()->after('secondary_contact_last_name');
            $table->string('secondary_contact_country')->nullable()->after('secondary_contact_email');
            $table->string('secondary_contact_mobile')->nullable()->after('secondary_contact_country');
            
            // Additional Information
            $table->string('company_gov_registry_number')->nullable()->after('secondary_contact_mobile');
            $table->string('company_registration_certificate_url')->nullable()->after('company_gov_registry_number');
            $table->string('facility_floorplan_url')->nullable()->after('company_registration_certificate_url');
            $table->json('interested_fields')->nullable()->after('facility_floorplan_url');
            $table->text('how_did_you_hear_about_us')->nullable()->after('interested_fields');
        });
    }

    public function down(): void
    {
        Schema::table('training_centers', function (Blueprint $table) {
            $table->dropColumn([
                'fax',
                'training_provider_type',
                'physical_postal_code',
                'mailing_same_as_physical',
                'mailing_address',
                'mailing_city',
                'mailing_country',
                'mailing_postal_code',
                'primary_contact_title',
                'primary_contact_first_name',
                'primary_contact_last_name',
                'primary_contact_email',
                'primary_contact_country',
                'primary_contact_mobile',
                'has_secondary_contact',
                'secondary_contact_title',
                'secondary_contact_first_name',
                'secondary_contact_last_name',
                'secondary_contact_email',
                'secondary_contact_country',
                'secondary_contact_mobile',
                'company_gov_registry_number',
                'company_registration_certificate_url',
                'facility_floorplan_url',
                'interested_fields',
                'how_did_you_hear_about_us',
            ]);
        });
    }
};

