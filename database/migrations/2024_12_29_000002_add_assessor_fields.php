<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_assessor field to instructors table
        Schema::table('instructors', function (Blueprint $table) {
            $table->boolean('is_assessor')->default(false)->after('status');
        });

        // Add assessor_required field to courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('assessor_required')->default(false)->after('max_capacity');
        });
    }

    public function down(): void
    {
        // Remove assessor_required from courses
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('assessor_required');
        });

        // Remove is_assessor from instructors
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('is_assessor');
        });
    }
};

