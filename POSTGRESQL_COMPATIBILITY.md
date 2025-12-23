# PostgreSQL Compatibility Guide

This document outlines PostgreSQL compatibility considerations for the BOMEQP Accreditation Management System.

## Overview

The application is designed to work with both MySQL/MariaDB and PostgreSQL databases. Laravel's Schema Builder handles most database differences automatically, but there are some considerations when using PostgreSQL.

## Database Configuration

To use PostgreSQL, update your `.env` file:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Key Differences Handled

### 1. ENUM Types

**MySQL/MariaDB:**
- Native ENUM types are used
- Can be modified using `ALTER TABLE ... MODIFY COLUMN ... ENUM()`

**PostgreSQL:**
- Laravel creates CHECK constraints for enum columns (not native ENUM types)
- CHECK constraints need to be dropped and recreated to modify allowed values
- The migrations handle this automatically based on the database driver

**Implementation:**
- All enum columns use Laravel's `$table->enum()` method which works cross-database
- When modifying enums (e.g., adding 'rejected' status), the migration detects the driver and uses appropriate SQL

### 2. Column Positioning

**MySQL/MariaDB:**
- Supports `->after('column_name')` in ALTER TABLE statements

**PostgreSQL:**
- Does NOT support `AFTER` clause in ALTER TABLE
- Columns are always added at the end

**Implementation:**
- Removed all `->after()` clauses from migrations for PostgreSQL compatibility
- Column order doesn't affect functionality

### 3. JSON Columns

**Both Databases:**
- Both support JSON columns
- Laravel handles JSON serialization/deserialization automatically
- No changes needed

### 4. Boolean Columns

**Both Databases:**
- Both support boolean types
- Laravel casts them correctly
- No changes needed

### 5. Decimal/Numeric Columns

**Both Databases:**
- Both support DECIMAL/NUMERIC types
- Precision and scale are handled identically
- No changes needed

### 6. Timestamps

**Both Databases:**
- Both support TIMESTAMP types
- Laravel's `$table->timestamps()` works identically
- No changes needed

## Testing PostgreSQL Compatibility

1. **Install PostgreSQL** and create a database
2. **Update `.env`** with PostgreSQL credentials
3. **Run migrations:**
   ```bash
   php artisan migrate:fresh --seed
   ```
4. **Test the application** to ensure all functionality works

## Known Compatibility Notes

### Migrations

All migrations are now PostgreSQL-compatible:
- ✅ No `->after()` clauses
- ✅ Database driver detection for enum modifications
- ✅ Cross-database SQL generation

### Eloquent Models

All Eloquent models work identically on both databases:
- ✅ Relationships
- ✅ Casts
- ✅ Fillable/guarded attributes
- ✅ Scopes

### Controllers

All controllers use Eloquent ORM, which is database-agnostic:
- ✅ No raw SQL queries (except in migrations with driver detection)
- ✅ All queries use Query Builder or Eloquent

## Migration Notes

The following migrations include database-specific code for enum modifications:

1. **`2025_12_16_221000_add_rejection_reason_to_accs_table.php`**
   - Adds 'rejected' status to accs.status enum
   - Uses driver detection to apply correct SQL for MySQL vs PostgreSQL

## Application Code Notes

### Password Reset (Fixed)
The password reset functionality in `AuthController` has been updated for cross-database compatibility:
- Uses `Carbon::parse()` to ensure timestamp handling works correctly with both MySQL and PostgreSQL
- All `DB::table()` queries are database-agnostic

## Recommendations

1. **Always use Laravel's Schema Builder** instead of raw SQL when possible
2. **Test migrations on both databases** before deploying
3. **Use Eloquent ORM** instead of raw queries for database-agnostic code
4. **When raw SQL is necessary**, detect the database driver and use appropriate syntax

## Troubleshooting

### Issue: "Column does not exist" after migration
**Solution:** Ensure you're running migrations on a fresh database or have proper migration rollback

### Issue: "Constraint already exists" 
**Solution:** The migration handles this with `IF EXISTS` clauses

### Issue: Enum values not updating
**Solution:** Check that the database driver is correctly detected in the migration

## Additional Resources

- [Laravel Database Migrations](https://laravel.com/docs/migrations)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [MySQL to PostgreSQL Migration Guide](https://www.postgresql.org/docs/current/migration.html)

