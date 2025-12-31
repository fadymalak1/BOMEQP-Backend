<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum to add 'instructor_authorization'
        DB::statement("ALTER TABLE transactions MODIFY COLUMN transaction_type ENUM('subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement', 'instructor_authorization') NOT NULL");
    }

    public function down(): void
    {
        // Remove 'instructor_authorization' from enum
        DB::statement("ALTER TABLE transactions MODIFY COLUMN transaction_type ENUM('subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement') NOT NULL");
    }
};

