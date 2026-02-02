<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            // Company Information - Fax
            $table->string('fax')->nullable()->after('phone');
            
            // Mailing Address - Same as Physical checkbox
            $table->boolean('mailing_same_as_physical')->default(false)->after('mailing_postal_code');
            
            // Primary Contact
            $table->enum('primary_contact_title', ['Mr.', 'Mrs.', 'Eng.', 'Prof.'])->nullable()->after('mailing_same_as_physical');
            $table->string('primary_contact_first_name')->nullable()->after('primary_contact_title');
            $table->string('primary_contact_last_name')->nullable()->after('primary_contact_first_name');
            $table->string('primary_contact_email')->nullable()->after('primary_contact_last_name');
            $table->string('primary_contact_country')->nullable()->after('primary_contact_email');
            $table->string('primary_contact_mobile')->nullable()->after('primary_contact_country');
            $table->string('primary_contact_passport_url')->nullable()->after('primary_contact_mobile');
            
            // Secondary Contact (Required)
            $table->enum('secondary_contact_title', ['Mr.', 'Mrs.', 'Eng.', 'Prof.'])->nullable()->after('primary_contact_passport_url');
            $table->string('secondary_contact_first_name')->nullable()->after('secondary_contact_title');
            $table->string('secondary_contact_last_name')->nullable()->after('secondary_contact_first_name');
            $table->string('secondary_contact_email')->nullable()->after('secondary_contact_last_name');
            $table->string('secondary_contact_country')->nullable()->after('secondary_contact_email');
            $table->string('secondary_contact_mobile')->nullable()->after('secondary_contact_country');
            $table->string('secondary_contact_passport_url')->nullable()->after('secondary_contact_mobile');
            
            // Additional Information
            $table->string('company_gov_registry_number')->nullable()->after('secondary_contact_passport_url');
            $table->string('company_registration_certificate_url')->nullable()->after('company_gov_registry_number');
            $table->text('how_did_you_hear_about_us')->nullable()->after('company_registration_certificate_url');
            
            // Agreement Checkboxes
            $table->boolean('agreed_to_receive_communications')->default(false)->after('how_did_you_hear_about_us');
            $table->boolean('agreed_to_terms_and_conditions')->default(false)->after('agreed_to_receive_communications');
        });
    }

    public function down(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->dropColumn([
                'fax',
                'mailing_same_as_physical',
                'primary_contact_title',
                'primary_contact_first_name',
                'primary_contact_last_name',
                'primary_contact_email',
                'primary_contact_country',
                'primary_contact_mobile',
                'primary_contact_passport_url',
                'secondary_contact_title',
                'secondary_contact_first_name',
                'secondary_contact_last_name',
                'secondary_contact_email',
                'secondary_contact_country',
                'secondary_contact_mobile',
                'secondary_contact_passport_url',
                'company_gov_registry_number',
                'company_registration_certificate_url',
                'how_did_you_hear_about_us',
                'agreed_to_receive_communications',
                'agreed_to_terms_and_conditions',
            ]);
        });
    }
};

