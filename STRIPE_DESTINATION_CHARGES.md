# Stripe Destination Charges Implementation

## Overview

This implementation uses **Stripe Destination Charges** to automatically split payments between providers (ACCs) and the platform (Admin). Money goes directly to the provider's Stripe Connect account, while the platform commission is automatically deducted.

## How It Works

### Payment Flow

1. **Customer pays** → Total amount (e.g., 1000 EGP)
2. **Stripe splits automatically**:
   - Provider (ACC) receives: `Total Amount - Commission` (e.g., 900 EGP)
   - Platform (Admin) receives: `Commission Amount` (e.g., 100 EGP)
3. **All automatic** ✅ - No manual transfers needed!

### Example

```php
// Total payment: 1000 EGP
// Commission: 10% = 100 EGP
// Provider receives: 900 EGP
// Admin receives: 100 EGP

$paymentIntent = \Stripe\PaymentIntent::create([
    'amount' => 100000, // 1000 EGP in cents
    'currency' => 'egp',
    
    // Admin commission (100 EGP)
    'application_fee_amount' => 10000,
    
    // Money goes to provider's Stripe Connect account
    'transfer_data' => [
        'destination' => $provider->stripe_account_id,
    ],
]);
```

## Implementation Details

### 1. Database Changes

**Migration**: `2025_12_30_000001_add_stripe_account_id_to_accs_table.php`

Adds `stripe_account_id` field to `accs` table to store the Stripe Connect account ID for each ACC.

### 2. StripeService Updates

**New Method**: `createDestinationChargePaymentIntent()`

```php
public function createDestinationChargePaymentIntent(
    float $amount,
    string $providerStripeAccountId,
    float $commissionAmount,
    string $currency = 'egp',
    array $metadata = []
): array
```

**Parameters**:
- `$amount`: Total payment amount
- `$providerStripeAccountId`: Stripe Connect account ID of the ACC/provider
- `$commissionAmount`: Commission amount for platform/admin
- `$currency`: Currency code (default: 'egp')
- `$metadata`: Additional metadata

**Returns**:
```php
[
    'success' => true,
    'client_secret' => 'pi_xxx_secret_xxx',
    'payment_intent_id' => 'pi_xxx',
    'amount' => 1000.00,
    'commission_amount' => 100.00,
    'provider_amount' => 900.00,
    'currency' => 'egp',
]
```

### 3. Updated Payment Flows

#### Code Purchase (`CodeController`)

**Endpoint**: `POST /api/training-center/codes/create-payment-intent`

**Changes**:
- Automatically uses destination charges if ACC has `stripe_account_id`
- Calculates commission based on `ACC->commission_percentage`
- Falls back to standard payment if no Stripe account configured

**Response includes**:
```json
{
    "payment_type": "destination_charge",
    "commission_amount": "100.00",
    "provider_amount": "900.00"
}
```

#### Instructor Authorization (`InstructorController`)

**Endpoint**: `POST /api/training-center/instructors/{id}/create-authorization-payment-intent`

**Changes**:
- Automatically uses destination charges if ACC has `stripe_account_id`
- Calculates commission based on `InstructorAccAuthorization->commission_percentage`
- Falls back to standard payment if no Stripe account configured

**Response includes**:
```json
{
    "payment_type": "destination_charge",
    "commission_amount": "100.00",
    "provider_amount": "900.00"
}
```

## Setup Requirements

### 1. Stripe Connect Setup

Each ACC (provider) needs a Stripe Connect account:

1. **Create Stripe Connect Account** for each ACC
2. **Get Account ID** (starts with `acct_`)
3. **Store in Database**: Update ACC record with `stripe_account_id`

### 2. ACC Configuration

```php
// Update ACC with Stripe account ID
$acc = ACC::find($accId);
$acc->stripe_account_id = 'acct_xxxxxxxxxxxxx';
$acc->commission_percentage = 10; // 10% commission
$acc->save();
```

### 3. Platform Stripe Account

The platform (admin) Stripe account must:
- Have Stripe Connect enabled
- Be configured in `StripeSetting` or `.env`

## Commission Calculation

### Code Purchases

```php
$groupCommissionPercentage = $acc->commission_percentage ?? 0;
$groupCommissionAmount = ($finalAmount * $groupCommissionPercentage) / 100;
```

### Instructor Authorization

```php
$groupCommissionPercentage = $authorization->commission_percentage ?? 0;
$groupCommissionAmount = ($authorization->authorization_price * $groupCommissionPercentage) / 100;
```

## Fallback Behavior

If ACC doesn't have `stripe_account_id` or commission is 0:
- Uses standard `createPaymentIntent()` method
- Payment goes to platform account
- Commission handled manually through transactions/ledger

## Benefits

✅ **Automatic Split**: Money automatically goes to provider and platform  
✅ **No Manual Transfers**: Stripe handles everything  
✅ **Real-time**: Funds available immediately  
✅ **Secure**: Stripe handles all security  
✅ **Transparent**: Clear commission breakdown in response  

## Testing

### Test Payment Intent Creation

```php
// Test destination charge
$result = $stripeService->createDestinationChargePaymentIntent(
    1000.00,                    // Total: 1000 EGP
    'acct_test123',             // Provider account
    100.00,                     // Commission: 100 EGP
    'egp'
);

// Expected result:
// - Provider receives: 900 EGP
// - Platform receives: 100 EGP
```

### Verify Payment Split

After payment succeeds:
1. Check Stripe Dashboard → Provider account should show 900 EGP
2. Check Stripe Dashboard → Platform account should show 100 EGP
3. Verify commission ledger entries in database

## API Response Examples

### Success Response (Destination Charge)

```json
{
    "success": true,
    "client_secret": "pi_xxx_secret_xxx",
    "payment_intent_id": "pi_xxx",
    "amount": 1000.00,
    "currency": "egp",
    "commission_amount": "100.00",
    "provider_amount": "900.00",
    "payment_type": "destination_charge"
}
```

### Success Response (Standard Payment - Fallback)

```json
{
    "success": true,
    "client_secret": "pi_xxx_secret_xxx",
    "payment_intent_id": "pi_xxx",
    "amount": 1000.00,
    "currency": "egp",
    "payment_type": "standard"
}
```

## Error Handling

### Missing Stripe Account ID

If ACC doesn't have `stripe_account_id`:
- Falls back to standard payment
- Commission handled through transaction ledger
- No error thrown

### Invalid Stripe Account ID

If `stripe_account_id` is invalid:
- Stripe will return error
- Error returned to client
- Payment intent not created

### Commission Exceeds Amount

If `commission_amount >= total_amount`:
- Exception thrown: "Commission amount cannot be greater than or equal to total amount"
- Payment intent not created

## Migration Guide

### For Existing ACCs

1. **Set up Stripe Connect** for each ACC
2. **Update database**:
   ```sql
   UPDATE accs SET stripe_account_id = 'acct_xxx' WHERE id = X;
   ```
3. **Set commission percentage**:
   ```sql
   UPDATE accs SET commission_percentage = 10 WHERE id = X;
   ```

### For New ACCs

1. Create ACC record
2. Set up Stripe Connect account
3. Update ACC with `stripe_account_id` and `commission_percentage`

## Security Notes

- Stripe handles all PCI compliance
- No card data stored on platform
- All sensitive operations through Stripe API
- Webhook verification recommended for production

## Support

For issues or questions:
1. Check Stripe Dashboard for payment status
2. Review application logs for errors
3. Verify ACC has valid `stripe_account_id`
4. Ensure commission percentage is set correctly

