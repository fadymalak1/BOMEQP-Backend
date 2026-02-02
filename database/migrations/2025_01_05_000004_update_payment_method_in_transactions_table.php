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
        // Database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE `transactions` MODIFY COLUMN `payment_method` ENUM('credit_card', 'debit_card', 'stripe', 'bank_transfer', 'manual_payment', 'cash', 'other') NOT NULL DEFAULT 'credit_card'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Laravel creates CHECK constraints for enums
            // Find all CHECK constraints on the payment_method column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'transactions' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'payment_method'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_method_check CHECK (payment_method IN ('credit_card', 'debit_card', 'stripe', 'bank_transfer', 'manual_payment', 'cash', 'other'))");
            DB::statement("ALTER TABLE transactions ALTER COLUMN payment_method SET NOT NULL");
            DB::statement("ALTER TABLE transactions ALTER COLUMN payment_method SET DEFAULT 'credit_card'");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values (with wallet, without manual_payment and other new methods)
        // Database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE `transactions` MODIFY COLUMN `payment_method` ENUM('wallet', 'credit_card', 'bank_transfer') NOT NULL DEFAULT 'credit_card'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Find all CHECK constraints on the payment_method column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'transactions' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'payment_method'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_payment_method_check CHECK (payment_method IN ('wallet', 'credit_card', 'bank_transfer'))");
            DB::statement("ALTER TABLE transactions ALTER COLUMN payment_method SET NOT NULL");
            DB::statement("ALTER TABLE transactions ALTER COLUMN payment_method SET DEFAULT 'credit_card'");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }
};

