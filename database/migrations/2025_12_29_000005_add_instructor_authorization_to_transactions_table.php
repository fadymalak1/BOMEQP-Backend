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
        // Database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE transactions MODIFY COLUMN transaction_type ENUM('subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement', 'instructor_authorization') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Laravel creates CHECK constraints for enums
            // Find all CHECK constraints on the transaction_type column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'transactions' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'transaction_type'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_transaction_type_check CHECK (transaction_type IN ('subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement', 'instructor_authorization'))");
            DB::statement("ALTER TABLE transactions ALTER COLUMN transaction_type SET NOT NULL");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }

    public function down(): void
    {
        // Remove 'instructor_authorization' from enum
        // Database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE transactions MODIFY COLUMN transaction_type ENUM('subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement') NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Find all CHECK constraints on the transaction_type column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'transactions' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'transaction_type'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_transaction_type_check CHECK (transaction_type IN ('subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement'))");
            DB::statement("ALTER TABLE transactions ALTER COLUMN transaction_type SET NOT NULL");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }
};

