<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable();
        });

        // Update enum to include 'rejected' status - database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Modify enum directly
            DB::statement("ALTER TABLE accs MODIFY COLUMN status ENUM('pending', 'active', 'suspended', 'expired', 'rejected') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Laravel creates CHECK constraints for enums
            // Find all CHECK constraints on the status column and drop them
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'accs' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'status'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE accs DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE accs ADD CONSTRAINT accs_status_check CHECK (status IN ('pending', 'active', 'suspended', 'expired', 'rejected'))");
            DB::statement("ALTER TABLE accs ALTER COLUMN status SET DEFAULT 'pending'");
        }
        // For SQLite, enum is just a string with CHECK constraint, no action needed
    }

    public function down(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        // Revert enum to original values - database-specific handling
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE accs MODIFY COLUMN status ENUM('pending', 'active', 'suspended', 'expired') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            // Find all CHECK constraints on the status column and drop them, then add original constraint back
            $constraints = DB::select("
                SELECT tc.constraint_name 
                FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage ccu 
                    ON tc.constraint_name = ccu.constraint_name
                WHERE tc.table_name = 'accs' 
                AND tc.constraint_type = 'CHECK'
                AND ccu.column_name = 'status'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE accs DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            DB::statement("ALTER TABLE accs ADD CONSTRAINT accs_status_check CHECK (status IN ('pending', 'active', 'suspended', 'expired'))");
            DB::statement("ALTER TABLE accs ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }
};

