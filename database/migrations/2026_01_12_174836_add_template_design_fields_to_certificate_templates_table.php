<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Add template_config JSON field to store design configuration
            $table->json('template_config')->nullable()->after('template_html');
            // Make template_html nullable since we'll generate it automatically
            $table->longText('template_html')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('template_config');
            $table->longText('template_html')->nullable(false)->change();
        });
    }
};
