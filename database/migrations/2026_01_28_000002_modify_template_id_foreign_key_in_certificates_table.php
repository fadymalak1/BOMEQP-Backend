<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['template_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            // Make template_id nullable to allow null when template is deleted
            $table->unsignedBigInteger('template_id')->nullable()->change();
        });

        Schema::table('certificates', function (Blueprint $table) {
            // Recreate the foreign key with nullOnDelete to preserve certificates when template is deleted
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

