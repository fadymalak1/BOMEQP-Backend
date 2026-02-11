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
        // We'll use PHP to backfill more accurately after migration
        // For now, set all to trainee (default), then run the backfill command
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

