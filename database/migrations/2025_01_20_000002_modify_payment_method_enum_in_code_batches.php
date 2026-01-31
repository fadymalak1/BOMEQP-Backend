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
        // Database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE code_batches MODIFY COLUMN payment_method ENUM('wallet', 'credit_card', 'manual_payment') NOT NULL");
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
            
            DB::statement("ALTER TABLE code_batches ADD CONSTRAINT code_batches_payment_method_check CHECK (payment_method IN ('wallet', 'credit_card', 'manual_payment'))");
            DB::statement("ALTER TABLE code_batches ALTER COLUMN payment_method SET NOT NULL");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }

    public function down(): void
    {
        // Revert to original enum values
        // Database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE code_batches MODIFY COLUMN payment_method ENUM('wallet', 'credit_card') NOT NULL");
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
            
            DB::statement("ALTER TABLE code_batches ADD CONSTRAINT code_batches_payment_method_check CHECK (payment_method IN ('wallet', 'credit_card'))");
            DB::statement("ALTER TABLE code_batches ALTER COLUMN payment_method SET NOT NULL");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }
};

