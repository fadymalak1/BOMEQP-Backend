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
        // Modify the enum to include 'approved' status
        DB::statement("ALTER TABLE accs MODIFY COLUMN status ENUM('pending', 'approved', 'active', 'suspended', 'expired', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum (without 'approved')
        DB::statement("ALTER TABLE accs MODIFY COLUMN status ENUM('pending', 'active', 'suspended', 'expired', 'rejected') DEFAULT 'pending'");
    }
};

