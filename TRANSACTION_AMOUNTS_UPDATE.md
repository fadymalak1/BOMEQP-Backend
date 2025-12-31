# Transaction Amounts Update - Commission & Provider Amounts

## Overview

Transactions now properly track and display separate amounts for:
- **Commission Amount**: Amount received by platform/admin
- **Provider Amount**: Amount received by ACC/provider
- **Total Amount**: Original transaction amount

## Database Changes

### Migration: `2025_12_30_000002_add_commission_and_provider_amounts_to_transactions.php`

Added three new fields to `transactions` table:
- `commission_amount` (decimal 10,2, nullable) - Platform/Admin commission
- `provider_amount` (decimal 10,2, nullable) - Provider (ACC) received amount
- `payment_type` (string 50, nullable) - 'destination_charge' or 'standard'

## How It Works

### Destination Charge Payments

When using Stripe Destination Charges:
- **Total Amount**: 1000 EGP (customer pays)
- **Commission Amount**: 100 EGP (goes to platform/admin)
- **Provider Amount**: 900 EGP (goes to ACC)

### Standard Payments

When using standard payment flow:
- **Total Amount**: 1000 EGP (customer pays)
- **Commission Amount**: 100 EGP (calculated, handled through ledger)
- **Provider Amount**: 900 EGP (calculated, handled through ledger)

## API Changes

### ACC Dashboard

**Endpoint**: `GET /api/acc/dashboard`

**Response**:
```json
{
    "revenue": {
        "monthly": 90000.00,  // Amount ACC received this month
        "total": 450000.00    // Total amount ACC received
    }
}
```

**Changes**:
- Now shows `provider_amount` (amount ACC actually received)
- For destination charges: Shows actual amount received
- For standard payments: Shows calculated amount after commission

### Admin Dashboard

**Endpoint**: `GET /api/admin/dashboard`

**Response**:
```json
{
    "revenue": {
        "monthly": 10000.00,  // Commission received this month
        "total": 50000.00     // Total commission received
    }
}
```

**Changes**:
- Now shows `commission_amount` (amount admin/platform received)
- Sums commission from all transactions
- Includes both destination charges and standard payments

### ACC Financial Transactions

**Endpoint**: `GET /api/acc/financial/transactions`

**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "transaction_type": "code_purchase",
            "amount": 1000.00,           // Total amount
            "commission_amount": 100.00, // Platform commission
            "provider_amount": 900.00,   // ACC received
            "received_amount": 900.00,    // Amount ACC received (same as provider_amount)
            "payment_type": "destination_charge",
            "currency": "USD",
            "status": "completed"
        }
    ],
    "summary": {
        "total_received": 90000.00,      // Total amount ACC received
        "completed_received": 90000.00   // Completed transactions received
    }
}
```

**New Fields**:
- `commission_amount`: Platform commission
- `provider_amount`: Amount ACC received
- `received_amount`: Same as provider_amount (for ACC transactions)
- `payment_type`: 'destination_charge' or 'standard'

### Admin Financial Transactions

**Endpoint**: `GET /api/admin/financial/transactions`

**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "transaction_type": "code_purchase",
            "amount": 1000.00,           // Total amount
            "commission_amount": 100.00, // Platform commission
            "provider_amount": 900.00,   // ACC received
            "payment_type": "destination_charge",
            "currency": "USD",
            "status": "completed"
        }
    ],
    "summary": {
        "total_commission": 10000.00,    // Total commission received
        "completed_commission": 10000.00 // Completed transactions commission
    }
}
```

**New Fields**:
- `commission_amount`: Platform commission
- `provider_amount`: Amount ACC received
- `payment_type`: 'destination_charge' or 'standard'

### Admin Financial Dashboard

**Endpoint**: `GET /api/admin/financial/dashboard`

**Response**:
```json
{
    "total_revenue": 50000.00,        // Total commission received
    "this_month_revenue": 10000.00,   // Commission this month
    "pending_settlements": 5000.00,
    "active_accs": 10
}
```

**Changes**:
- `total_revenue`: Sum of all commission amounts
- `this_month_revenue`: Sum of commission amounts this month

## Transaction Creation

### Code Purchase

When creating transactions for code purchases:

```php
Transaction::create([
    'amount' => 1000.00,              // Total amount
    'commission_amount' => 100.00,    // Platform commission
    'provider_amount' => 900.00,      // ACC received
    'payment_type' => 'destination_charge', // or 'standard'
    // ... other fields
]);
```

### Instructor Authorization

When creating transactions for instructor authorization:

```php
Transaction::create([
    'amount' => 1000.00,              // Total amount
    'commission_amount' => 100.00,    // Platform commission
    'provider_amount' => 900.00,      // ACC received
    'payment_type' => 'destination_charge', // or 'standard'
    // ... other fields
]);
```

## Frontend Implementation

### ACC Dashboard

Display the amount ACC received:

```javascript
// ACC Dashboard
const dashboard = await fetch('/api/acc/dashboard');
const data = await dashboard.json();

// Show revenue ACC received
console.log(`Monthly Revenue: ${data.revenue.monthly}`); // Amount ACC received
console.log(`Total Revenue: ${data.revenue.total}`);     // Total ACC received
```

### Admin Dashboard

Display the commission amount:

```javascript
// Admin Dashboard
const dashboard = await fetch('/api/admin/dashboard');
const data = await dashboard.json();

// Show commission received
console.log(`Monthly Commission: ${data.revenue.monthly}`); // Commission received
console.log(`Total Commission: ${data.revenue.total}`);     // Total commission
```

### Transaction List

Display both amounts in transaction lists:

```javascript
// ACC Transactions
transactions.forEach(transaction => {
    console.log(`Total: ${transaction.amount}`);
    console.log(`Received: ${transaction.received_amount || transaction.provider_amount}`);
    console.log(`Commission: ${transaction.commission_amount}`);
});

// Admin Transactions
transactions.forEach(transaction => {
    console.log(`Total: ${transaction.amount}`);
    console.log(`Commission: ${transaction.commission_amount}`);
    console.log(`Provider Received: ${transaction.provider_amount}`);
});
```

## Summary Fields

### ACC Financial Summary

- `total_received`: Sum of `provider_amount` for ACC transactions
- `completed_received`: Sum of `provider_amount` for completed transactions

### Admin Financial Summary

- `total_commission`: Sum of `commission_amount` from all transactions
- `completed_commission`: Sum of `commission_amount` for completed transactions

## Migration Notes

1. **Run Migration**:
   ```bash
   php artisan migrate
   ```

2. **Existing Transactions**:
   - Old transactions will have `null` for `commission_amount` and `provider_amount`
   - System falls back to calculating from commission ledgers
   - New transactions will have proper amounts

3. **Backward Compatibility**:
   - If `provider_amount` is null, system uses `amount`
   - If `commission_amount` is null, system calculates from commission ledgers
   - All existing functionality continues to work

## Benefits

✅ **Clear Separation**: Commission and provider amounts are clearly separated  
✅ **Accurate Reporting**: Dashboards show correct amounts  
✅ **Transparency**: Both parties can see exactly what they received  
✅ **Destination Charge Support**: Properly tracks split payments  
✅ **Backward Compatible**: Works with existing transactions  

## Example Scenarios

### Scenario 1: Destination Charge Payment

**Transaction**: Code purchase of 1000 EGP with 10% commission

- Total Amount: 1000 EGP
- Commission Amount: 100 EGP (stored in transaction)
- Provider Amount: 900 EGP (stored in transaction)
- Payment Type: 'destination_charge'

**ACC Dashboard**: Shows 900 EGP received  
**Admin Dashboard**: Shows 100 EGP commission

### Scenario 2: Standard Payment

**Transaction**: Code purchase of 1000 EGP with 10% commission

- Total Amount: 1000 EGP
- Commission Amount: 100 EGP (stored in transaction)
- Provider Amount: 900 EGP (stored in transaction)
- Payment Type: 'standard'

**ACC Dashboard**: Shows 900 EGP received  
**Admin Dashboard**: Shows 100 EGP commission

Both scenarios now show the correct amounts in their respective dashboards!

