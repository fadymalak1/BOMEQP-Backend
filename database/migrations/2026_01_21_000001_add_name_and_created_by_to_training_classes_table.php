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
        Schema::table('training_classes', function (Blueprint $table) {
            // Make class_id nullable (no longer required)
            $table->foreignId('class_id')->nullable()->change();
            
            // Add name field (required for class name)
            $table->string('name')->after('course_id');
            
            // Add created_by field (to track who created the class)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_classes', function (Blueprint $table) {
            // Remove added fields
            $table->dropForeign(['created_by']);
            $table->dropColumn(['name', 'created_by']);
            
            // Make class_id required again
            $table->foreignId('class_id')->nullable(false)->change();
        });
    }
};

