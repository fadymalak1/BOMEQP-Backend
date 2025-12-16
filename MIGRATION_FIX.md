# Migration Fix Guide

## Problem
Duplicate migration files caused a conflict. The tables `code_batches`, `discount_codes`, and `certificate_codes` were already created, but there were duplicate migration files with conflicting timestamps.

## Solution

The duplicate files have been removed and the correct order is now:
1. `2024_01_01_000017_create_discount_codes_table.php` ✓
2. `2024_01_01_000018_create_code_batches_table.php` ✓
3. `2024_01_01_000019_create_certificate_codes_table.php` ✓

## Steps to Fix

Since the tables already exist from the previous migration run, you have two options:

### Option 1: Fresh Start (Recommended for Development)

If you're in development and don't have important data:

```bash
# Rollback all migrations
php artisan migrate:rollback

# Or drop and recreate the database
# Then run migrations again
php artisan migrate
```

### Option 2: Manual Fix (If you have data)

Since the tables already exist, you can manually mark the migrations as run:

```bash
# Check which migrations have run
php artisan migrate:status

# The migrations for code_batches, discount_codes, and certificate_codes should show as "Ran"
# If they do, you can continue with the remaining migrations
php artisan migrate
```

### Option 3: Fix Migration Table Manually

If migrations show as not run but tables exist:

1. Check your database's `migrations` table
2. Ensure these entries exist:
   - `2024_01_01_000017_create_discount_codes_table`
   - `2024_01_01_000018_create_code_batches_table`
   - `2024_01_01_000019_create_certificate_codes_table`

3. If they don't exist, insert them manually or run:
```bash
php artisan migrate --pretend
```

## Verification

After fixing, verify the migration status:

```bash
php artisan migrate:status
```

All migrations should show as "Ran" without any conflicts.

## Current Migration Order

The correct order is now:
1. Users table
2. ACCs table
3. ACC Subscriptions
4. ACC Documents
5. Categories
6. Sub Categories
7. Courses
8. Classes
9. Certificate Templates
10. Certificate Pricing
11. Certificates
12. Training Centers
13. Training Center ACC Authorization
14. Training Center Wallet
15. Instructors
16. Instructor ACC Authorization
17. Instructor Course Authorization
18. **Discount Codes** (000017)
19. **Code Batches** (000018)
20. **Certificate Codes** (000019)
21. Transactions
22. Commission Ledger
23. Monthly Settlements
24. ACC Materials
25. Training Center Purchases
26. Training Classes
27. Class Completion
28. Add Certificate Foreign Key

## Notes

- The dependency order is correct: discount_codes → code_batches → certificate_codes
- Certificate codes references both discount_codes and code_batches
- The foreign key constraint for certificates is added in migration 000027

