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
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('group_admin', 'acc_admin', 'competency_admin', 'training_center_admin', 'instructor') DEFAULT 'training_center_admin'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('group_admin', 'acc_admin', 'training_center_admin', 'instructor') DEFAULT 'training_center_admin'");
    }
};
