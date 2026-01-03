<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify payment_method enum to include 'manual_payment'
        // Note: MySQL doesn't support ALTER ENUM directly, so we need to use raw SQL
        DB::statement("ALTER TABLE code_batches MODIFY COLUMN payment_method ENUM('wallet', 'credit_card', 'manual_payment') NOT NULL");
    }

    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE code_batches MODIFY COLUMN payment_method ENUM('wallet', 'credit_card') NOT NULL");
    }
};

