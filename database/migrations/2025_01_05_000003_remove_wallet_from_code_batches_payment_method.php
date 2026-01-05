<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any existing records with 'wallet' to 'credit_card' (or handle as needed)
        // Note: This assumes wallet payments should be converted to credit_card
        // If you have a different requirement, adjust this logic
        DB::table('code_batches')
            ->where('payment_method', 'wallet')
            ->update(['payment_method' => 'credit_card']);

        // Now remove 'wallet' from the ENUM
        DB::statement("ALTER TABLE `code_batches` MODIFY COLUMN `payment_method` ENUM('credit_card', 'manual_payment') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add 'wallet' back to the ENUM
        DB::statement("ALTER TABLE `code_batches` MODIFY COLUMN `payment_method` ENUM('credit_card', 'wallet', 'manual_payment') NOT NULL");
    }
};

