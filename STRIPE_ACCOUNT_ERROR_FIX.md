# Stripe Account Error Fix - "No such destination" Error

## Problem

When creating payment intents with destination charges, the error `"No such destination: 'acct_xxxxxxxxxxxxx'"` was occurring. This happens when:

1. The Stripe account ID doesn't exist
2. The Stripe account is not connected to the platform account
3. The Stripe account is not activated/verified
4. The account ID is incorrect

## Solution

### 1. Added Account Verification

Before creating a payment intent with destination charges, the system now verifies that the Stripe account exists and is valid.

**New Method**: `StripeService::verifyStripeAccount()`

```php
$verification = $stripeService->verifyStripeAccount($accountId);
if (!$verification['valid']) {
    // Handle error or fallback
}
```

### 2. Improved Error Messages

The error messages are now more descriptive and helpful:

**Before**:
```
"No such destination: 'acct_1SkFIjGhbsu3HqEZ'"
```

**After**:
```
"Stripe account 'acct_1SkFIjGhbsu3HqEZ' not found or not connected. Please verify the account ID is correct and the account is properly connected to the platform."
```

### 3. Automatic Fallback

If destination charge fails, the system automatically falls back to standard payment:

```php
// Try destination charge
$result = $stripeService->createDestinationChargePaymentIntent(...);

// If fails, fallback to standard payment
if (!$result['success']) {
    $result = $stripeService->createPaymentIntent(...);
}
```

### 4. Account Verification Endpoint

Added new endpoint for ACCs to verify their Stripe account ID before using it:

**Endpoint**: `POST /api/acc/profile/verify-stripe-account`

**Request**:
```json
{
    "stripe_account_id": "acct_xxxxxxxxxxxxx"
}
```

**Response (Valid)**:
```json
{
    "valid": true,
    "account": {
        "id": "acct_xxxxxxxxxxxxx",
        "type": "standard",
        "charges_enabled": true,
        "payouts_enabled": true,
        "details_submitted": true
    },
    "message": "Stripe account is valid and connected"
}
```

**Response (Invalid)**:
```json
{
    "valid": false,
    "error": "No such account: 'acct_xxxxxxxxxxxxx'",
    "message": "Stripe account verification failed. Please check that the account ID is correct and the account is properly connected to the platform."
}
```

## How It Works Now

### Payment Flow

1. **Check if ACC has Stripe account ID**
   - If yes → Try destination charge
   - If no → Use standard payment

2. **Verify Stripe account** (if destination charge)
   - Verify account exists
   - Check account is connected
   - If invalid → Fallback to standard payment

3. **Create payment intent**
   - Destination charge (if valid)
   - Standard payment (if invalid or no account)

4. **Handle errors gracefully**
   - Clear error messages
   - Automatic fallback
   - Logging for debugging

## Frontend Implementation

### Verify Account Before Saving

```javascript
// Verify Stripe account ID before saving
async function verifyAndSaveStripeAccount(stripeAccountId) {
    // First verify
    const verifyResponse = await fetch('/api/acc/profile/verify-stripe-account', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ stripe_account_id: stripeAccountId }),
    });
    
    const verifyResult = await verifyResponse.json();
    
    if (!verifyResult.valid) {
        // Show error to user
        alert(`Invalid Stripe account: ${verifyResult.error}`);
        return false;
    }
    
    // If valid, save it
    const saveResponse = await fetch('/api/acc/profile', {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ stripe_account_id: stripeAccountId }),
    });
    
    return saveResponse.ok;
}
```

### Handle Payment Intent Errors

```javascript
try {
    const response = await fetch('/api/training-center/instructors/authorizations/30/payment-intent', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
        },
    });
    
    const result = await response.json();
    
    if (!response.ok) {
        // Check error code
        if (result.error_code === 'invalid_stripe_account') {
            // Show helpful message
            alert('The ACC\'s Stripe account is not valid. Payment will use standard flow.');
        } else {
            alert(`Payment error: ${result.error}`);
        }
    }
} catch (error) {
    console.error('Payment error:', error);
}
```

## Common Issues and Solutions

### Issue 1: Account Not Connected

**Error**: `"No such destination: 'acct_xxx'"`

**Solution**:
1. Verify account ID is correct
2. Ensure account is connected to platform via Stripe Connect
3. Check account is activated in Stripe Dashboard

### Issue 2: Account Not Activated

**Error**: Account verification fails

**Solution**:
1. Complete Stripe Connect onboarding
2. Submit required business information
3. Verify bank account details

### Issue 3: Wrong Account ID Format

**Error**: Validation error

**Solution**:
- Account ID must start with `acct_`
- Use the verification endpoint to check format

## Testing

### Test Account Verification

```javascript
// Test valid account
const validAccount = await verifyStripeAccount('acct_valid123');
console.log(validAccount.valid); // Should be true

// Test invalid account
const invalidAccount = await verifyStripeAccount('acct_invalid');
console.log(invalidAccount.valid); // Should be false
```

### Test Payment Intent with Invalid Account

1. Set invalid Stripe account ID for ACC
2. Try to create payment intent
3. Should automatically fallback to standard payment
4. Should log warning but not fail

## Error Codes

- `invalid_stripe_account`: Stripe account ID is invalid or not connected
- `stripe_api_error`: General Stripe API error
- `unknown_error`: Unknown error occurred

## Logging

All errors are logged with context:

```php
Log::error('Stripe Destination Charge PaymentIntent creation failed', [
    'error' => $errorMessage,
    'stripe_error' => $originalStripeError,
    'provider_stripe_account_id' => $accountId,
    // ... other context
]);
```

## Summary

✅ **Account verification** before creating payment intent  
✅ **Better error messages** for debugging  
✅ **Automatic fallback** to standard payment  
✅ **Verification endpoint** for ACCs to check account  
✅ **Graceful error handling** - payments don't fail completely  

The system now handles invalid Stripe accounts gracefully and provides clear feedback to help resolve issues!

