<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Allow both values temporarily, then normalize data.
        DB::statement("
            ALTER TABLE training_centers
            MODIFY training_provider_type ENUM('Training Provider', 'Training Center', 'Institute', 'University') NULL
        ");

        DB::table('training_centers')
            ->where('training_provider_type', 'Training Center')
            ->update(['training_provider_type' => 'Training Provider']);

        // Keep only canonical values.
        DB::statement("
            ALTER TABLE training_centers
            MODIFY training_provider_type ENUM('Training Provider', 'Institute', 'University') NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE training_centers
            MODIFY training_provider_type ENUM('Training Provider', 'Training Center', 'Institute', 'University') NULL
        ");

        DB::table('training_centers')
            ->where('training_provider_type', 'Training Provider')
            ->update(['training_provider_type' => 'Training Center']);

        DB::statement("
            ALTER TABLE training_centers
            MODIFY training_provider_type ENUM('Training Center', 'Institute', 'University') NULL
        ");
    }
};
