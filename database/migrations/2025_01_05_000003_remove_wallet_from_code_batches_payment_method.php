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

        // Now remove 'wallet' from the ENUM - database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE `code_batches` MODIFY COLUMN `payment_method` ENUM('credit_card', 'manual_payment') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Laravel creates CHECK constraints for enums
            // Find all CHECK constraints on the payment_method column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'code_batches' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'payment_method'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE code_batches DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE code_batches ADD CONSTRAINT code_batches_payment_method_check CHECK (payment_method IN ('credit_card', 'manual_payment'))");
            DB::statement("ALTER TABLE code_batches ALTER COLUMN payment_method SET NOT NULL");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add 'wallet' back to the ENUM - database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE `code_batches` MODIFY COLUMN `payment_method` ENUM('credit_card', 'wallet', 'manual_payment') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Find all CHECK constraints on the payment_method column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'code_batches' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'payment_method'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE code_batches DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE code_batches ADD CONSTRAINT code_batches_payment_method_check CHECK (payment_method IN ('credit_card', 'wallet', 'manual_payment'))");
            DB::statement("ALTER TABLE code_batches ALTER COLUMN payment_method SET NOT NULL");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }
};

