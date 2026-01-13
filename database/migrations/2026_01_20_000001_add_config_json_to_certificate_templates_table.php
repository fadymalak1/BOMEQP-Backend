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
            // Add config_json field to store template designer configuration
            // This stores array of placeholders with coordinates (as percentages), styling, etc.
            if (!Schema::hasColumn('certificate_templates', 'config_json')) {
                $table->json('config_json')->nullable()->after('template_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('config_json');
        });
    }
};

