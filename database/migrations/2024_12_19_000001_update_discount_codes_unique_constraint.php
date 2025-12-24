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
        // Drop the existing unique constraint on 'code' column
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        // Add composite unique constraint on (acc_id, code)
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->unique(['acc_id', 'code'], 'discount_codes_acc_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the composite unique constraint
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropUnique('discount_codes_acc_code_unique');
        });

        // Restore the original unique constraint on 'code' column
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};

