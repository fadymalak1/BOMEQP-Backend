<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add max_capacity to courses table (nullable first to allow data migration)
        Schema::table('courses', function (Blueprint $table) {
            $table->integer('max_capacity')->nullable()->after('duration_hours');
        });

        // Copy max_capacity from training_classes to courses (if any data exists)
        // This will set the max_capacity for each course based on the first training class
        if (Schema::hasTable('training_classes') && Schema::hasColumn('training_classes', 'max_capacity')) {
            \DB::statement('
                UPDATE courses c
                INNER JOIN (
                    SELECT course_id, MAX(max_capacity) as max_capacity
                    FROM training_classes
                    WHERE max_capacity IS NOT NULL
                    GROUP BY course_id
                ) tc ON c.id = tc.course_id
                SET c.max_capacity = tc.max_capacity
                WHERE c.max_capacity IS NULL
            ');
        }

        // Set default value for courses without max_capacity (if any)
        \DB::table('courses')->whereNull('max_capacity')->update(['max_capacity' => 20]);

        // Make max_capacity required (not nullable) after data migration
        Schema::table('courses', function (Blueprint $table) {
            $table->integer('max_capacity')->nullable(false)->default(20)->change();
        });

        // Remove max_capacity from training_classes table
        if (Schema::hasTable('training_classes') && Schema::hasColumn('training_classes', 'max_capacity')) {
            Schema::table('training_classes', function (Blueprint $table) {
                $table->dropColumn('max_capacity');
            });
        }
    }

    public function down(): void
    {
        // Add max_capacity back to training_classes
        Schema::table('training_classes', function (Blueprint $table) {
            $table->integer('max_capacity')->after('schedule_json');
        });

        // Copy max_capacity from courses to training_classes
        DB::statement('
            UPDATE training_classes tc
            INNER JOIN courses c ON tc.course_id = c.id
            SET tc.max_capacity = c.max_capacity
        ');

        // Remove max_capacity from courses
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('max_capacity');
        });
    }
};

