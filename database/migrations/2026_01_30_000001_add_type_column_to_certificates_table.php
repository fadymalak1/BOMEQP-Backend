<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->enum('type', ['instructor', 'trainee'])->default('trainee')->after('instructor_id');
        });

        // Backfill existing certificates with their type
        // Instructor certificates: instructor_id is set AND trainee_name matches instructor's name
        DB::statement("
            UPDATE certificates c
            INNER JOIN instructors i ON c.instructor_id = i.id
            SET c.type = 'instructor'
            WHERE c.instructor_id IS NOT NULL
            AND LOWER(TRIM(CONCAT(COALESCE(i.first_name, ''), ' ', COALESCE(i.last_name, '')))) = LOWER(TRIM(c.trainee_name))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

