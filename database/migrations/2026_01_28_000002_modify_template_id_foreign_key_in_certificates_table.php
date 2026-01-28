<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop the existing foreign key constraint first
        // This allows us to clean up invalid data without constraint violations
        try {
            Schema::table('certificates', function (Blueprint $table) {
                $table->dropForeign(['template_id']);
            });
        } catch (\Exception $e) {
            // Try alternative method if Laravel's method fails
            try {
                DB::statement('ALTER TABLE certificates DROP FOREIGN KEY certificates_template_id_foreign');
            } catch (\Exception $e2) {
                // Query for the actual constraint name
                $constraint = DB::selectOne("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'certificates' 
                    AND COLUMN_NAME = 'template_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    LIMIT 1
                ");
                
                if ($constraint && isset($constraint->CONSTRAINT_NAME)) {
                    DB::statement("ALTER TABLE certificates DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
                }
            }
        }

        // Step 2: Clean up any invalid template_id values (orphaned records)
        // Use LEFT JOIN approach to avoid MySQL subquery limitations
        DB::statement('UPDATE certificates c
                       LEFT JOIN certificate_templates ct ON c.template_id = ct.id
                       SET c.template_id = NULL
                       WHERE c.template_id IS NOT NULL 
                       AND ct.id IS NULL');

        // Step 3: Make template_id nullable
        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->change();
        });

        // Step 4: Recreate the foreign key with nullOnDelete
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('template_id')
                  ->references('id')
                  ->on('certificate_templates')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['template_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            // Make template_id non-nullable again (note: this may fail if there are null values)
            $table->unsignedBigInteger('template_id')->nullable(false)->change();
        });

        Schema::table('certificates', function (Blueprint $table) {
            // Recreate the original foreign key constraint (without nullOnDelete)
            $table->foreign('template_id')
                  ->references('id')
                  ->on('certificate_templates');
        });
    }
};

