<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Drop the existing foreign key constraint for category_id
            $table->dropForeign(['category_id']);
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            // Make category_id nullable (can be set if template applies to entire category)
            $table->foreignId('category_id')->nullable()->change();
            
            // Recreate the foreign key constraint for category_id
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            
            // Add course_id (can be set if template applies to specific course)
            $table->foreignId('course_id')->nullable()->after('category_id')->constrained('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
            // Note: We don't revert category_id to non-nullable to avoid breaking existing data
            // If needed, this can be handled separately
        });
    }
};

