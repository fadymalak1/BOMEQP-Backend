<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->longText('card_back_template_html')->nullable()->after('card_template_html');
            $table->string('card_back_background_image_url')->nullable()->after('card_background_image_url');
            $table->json('card_back_config_json')->nullable()->after('card_config_json');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn([
                'card_back_template_html',
                'card_back_background_image_url',
                'card_back_config_json',
            ]);
        });
    }
};
