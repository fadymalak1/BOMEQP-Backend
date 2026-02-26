<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Whether this certificate template should include a card page in the generated PDF
            $table->boolean('include_card')->default(false)->after('status');
            // Card design — mirrors the certificate template structure
            $table->longText('card_template_html')->nullable()->after('include_card');
            $table->string('card_background_image_url')->nullable()->after('card_template_html');
            $table->json('card_config_json')->nullable()->after('card_background_image_url');
        });

        // One card template per ACC: enforced at application level (one active per ACC)
        // No unique DB constraint needed since the card lives on the certificate_template row itself
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['include_card', 'card_template_html', 'card_background_image_url', 'card_config_json']);
        });
    }
};
