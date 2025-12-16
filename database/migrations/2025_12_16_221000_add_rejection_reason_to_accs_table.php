<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_by');
        });

        // Update enum to include 'rejected' status
        DB::statement("ALTER TABLE accs MODIFY COLUMN status ENUM('pending', 'active', 'suspended', 'expired', 'rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        // Revert enum to original values
        DB::statement("ALTER TABLE accs MODIFY COLUMN status ENUM('pending', 'active', 'suspended', 'expired') DEFAULT 'pending'");
    }
};

