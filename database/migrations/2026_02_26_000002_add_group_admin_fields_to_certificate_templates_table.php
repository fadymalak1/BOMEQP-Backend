<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Allow null acc_id so group admin can create templates not tied to any ACC
            $table->unsignedBigInteger('created_by')->nullable()->after('acc_id');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // Distinguish group-admin-owned templates from ACC-owned ones
            $table->boolean('is_group_admin_template')->default(false)->after('created_by');
        });

        // Make acc_id nullable so group admin templates can exist without an ACC
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('acc_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'is_group_admin_template']);
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('acc_id')->nullable(false)->change();
        });
    }
};
