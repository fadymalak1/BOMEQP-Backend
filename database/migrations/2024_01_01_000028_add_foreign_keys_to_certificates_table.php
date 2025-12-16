<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Add foreign key for training_center_id (training_centers table exists at migration 000011)
            $table->foreign('training_center_id')
                  ->references('id')
                  ->on('training_centers')
                  ->onDelete('cascade');

            // Add foreign key for instructor_id (instructors table exists at migration 000014)
            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('set null');

            // Add foreign key for code_used_id (certificate_codes table exists at migration 000019)
            // This is already handled in migration 000027, but we'll keep it here for clarity
            // Actually, migration 000027 adds it to certificate_codes, not certificates
            // So we need to add it here
            $table->foreign('code_used_id')
                  ->references('id')
                  ->on('certificate_codes')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['training_center_id']);
            $table->dropForeign(['instructor_id']);
            $table->dropForeign(['code_used_id']);
        });
    }
};

