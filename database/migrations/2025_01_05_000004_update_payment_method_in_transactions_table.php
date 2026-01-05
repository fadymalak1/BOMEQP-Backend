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
        // First, update any existing records with 'wallet' to 'credit_card'
        DB::table('transactions')
            ->where('payment_method', 'wallet')
            ->update(['payment_method' => 'credit_card']);

        // Update the payment_method ENUM to include all needed payment methods
        // Remove 'wallet', add 'manual_payment', and include other common methods
        DB::statement("ALTER TABLE `transactions` MODIFY COLUMN `payment_method` ENUM('credit_card', 'debit_card', 'stripe', 'bank_transfer', 'manual_payment', 'cash', 'other') NOT NULL DEFAULT 'credit_card'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values (with wallet, without manual_payment and other new methods)
        DB::statement("ALTER TABLE `transactions` MODIFY COLUMN `payment_method` ENUM('wallet', 'credit_card', 'bank_transfer') NOT NULL DEFAULT 'credit_card'");
    }
};

