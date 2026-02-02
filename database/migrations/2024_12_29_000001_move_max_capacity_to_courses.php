<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add max_capacity to courses table (nullable first to allow data migration)
        // Only add if it doesn't already exist
        if (!Schema::hasColumn('courses', 'max_capacity')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->integer('max_capacity')->nullable()->after('duration_hours');
            });
        }

        // Copy max_capacity from training_classes to courses (if any data exists)
        // This will set the max_capacity for each course based on the maximum max_capacity from training classes
        if (Schema::hasTable('training_classes') && Schema::hasColumn('training_classes', 'max_capacity')) {
            // Get max capacity per course from training_classes
            $maxCapacities = \DB::table('training_classes')
                ->select('course_id', \DB::raw('MAX(max_capacity) as max_capacity'))
                ->whereNotNull('max_capacity')
                ->groupBy('course_id')
                ->get();

            // Update courses with max_capacity from training_classes
            foreach ($maxCapacities as $row) {
                \DB::table('courses')
                    ->where('id', $row->course_id)
                    ->whereNull('max_capacity')
                    ->update(['max_capacity' => $row->max_capacity]);
            }
        }

        // Set default value for courses without max_capacity (if any)
        if (Schema::hasColumn('courses', 'max_capacity')) {
            \DB::table('courses')->whereNull('max_capacity')->update(['max_capacity' => 20]);

            // Make max_capacity required (not nullable) after data migration
            // Only change if column exists
            Schema::table('courses', function (Blueprint $table) {
                $table->integer('max_capacity')->nullable(false)->default(20)->change();
            });
        }

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
        $courses = \DB::table('courses')->select('id', 'max_capacity')->get();
        foreach ($courses as $course) {
            \DB::table('training_classes')
                ->where('course_id', $course->id)
                ->update(['max_capacity' => $course->max_capacity]);
        }

        // Remove max_capacity from courses
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('max_capacity');
        });
    }
};

