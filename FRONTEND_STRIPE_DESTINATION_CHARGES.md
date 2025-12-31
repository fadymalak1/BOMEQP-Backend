# Stripe Destination Charges - Frontend Developer Guide

## Overview

The payment system now uses **Stripe Destination Charges** to automatically split payments between providers (ACCs) and the platform. This means money goes directly to the provider's account, while the platform commission is automatically deducted - all handled by Stripe!

## What Changed?

### Before
- All payments went to platform account
- Commission handled manually through transactions
- Manual transfers needed to providers

### After
- Payments automatically split between provider and platform
- Provider receives money directly in their Stripe account
- Platform commission automatically deducted
- **No changes required in frontend code** - API handles everything!

## API Response Changes

### Payment Intent Creation Responses

Both endpoints now return additional fields showing the payment split:

#### Code Purchase Payment Intent

**Endpoint**: `POST /api/training-center/codes/create-payment-intent`

**New Response Fields**:
```json
{
    "success": true,
    "client_secret": "pi_xxx_secret_xxx",
    "payment_intent_id": "pi_xxx",
    "amount": 1000.00,
    "currency": "egp",
    "total_amount": "1000.00",
    "discount_amount": "0.00",
    "final_amount": "1000.00",
    "unit_price": "100.00",
    "quantity": 10,
    
    // NEW FIELDS
    "commission_amount": "100.00",        // Platform commission
    "provider_amount": "900.00",           // Amount provider receives
    "payment_type": "destination_charge"  // or "standard"
}
```

#### Instructor Authorization Payment Intent

**Endpoint**: `POST /api/training-center/instructors/{id}/create-authorization-payment-intent`

**New Response Fields**:
```json
{
    "success": true,
    "client_secret": "pi_xxx_secret_xxx",
    "payment_intent_id": "pi_xxx",
    "amount": 1000.00,
    "currency": "usd",
    
    // NEW FIELDS
    "commission_amount": "100.00",        // Platform commission
    "provider_amount": "900.00",           // Amount provider receives
    "payment_type": "destination_charge"  // or "standard"
}
```

## Understanding Payment Types

### `payment_type: "destination_charge"`

- **When**: ACC has Stripe Connect account configured
- **Behavior**: Payment automatically split
- **Provider receives**: `provider_amount` directly in their Stripe account
- **Platform receives**: `commission_amount` automatically

### `payment_type: "standard"`

- **When**: ACC doesn't have Stripe Connect account OR commission is 0%
- **Behavior**: Standard payment to platform account
- **Provider receives**: Nothing (handled through internal transactions)
- **Platform receives**: Full amount

## Frontend Implementation

### No Code Changes Required! ✅

The existing frontend code will continue to work. The new fields are **additive** - they provide additional information but don't break existing functionality.

### Optional: Display Payment Breakdown

You can optionally display the payment breakdown to users:

```javascript
// Example: Display payment breakdown
const createPaymentIntent = async (data) => {
    const response = await fetch('/api/training-center/codes/create-payment-intent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
        // Existing code - still works!
        const { client_secret, payment_intent_id } = result;
        
        // NEW: Optional - Display payment breakdown
        if (result.payment_type === 'destination_charge') {
            console.log('Payment Breakdown:');
            console.log(`Total: ${result.amount} ${result.currency}`);
            console.log(`Provider receives: ${result.provider_amount} ${result.currency}`);
            console.log(`Platform commission: ${result.commission_amount} ${result.currency}`);
        }
        
        // Continue with Stripe payment confirmation...
        return { client_secret, payment_intent_id };
    }
};
```

### React Component Example

```jsx
import { useState } from 'react';

function PaymentIntentDisplay({ paymentIntent }) {
    const { 
        amount, 
        currency, 
        commission_amount, 
        provider_amount, 
        payment_type 
    } = paymentIntent;
    
    return (
        <div className="payment-breakdown">
            <h3>Payment Summary</h3>
            <div className="amount-row">
                <span>Total Amount:</span>
                <strong>{amount} {currency.toUpperCase()}</strong>
            </div>
            
            {payment_type === 'destination_charge' && (
                <>
                    <div className="amount-row">
                        <span>Provider Receives:</span>
                        <strong>{provider_amount} {currency.toUpperCase()}</strong>
                    </div>
                    <div className="amount-row">
                        <span>Platform Commission:</span>
                        <strong>{commission_amount} {currency.toUpperCase()}</strong>
                    </div>
                </>
            )}
        </div>
    );
}
```

### Vue Component Example

```vue
<template>
    <div class="payment-breakdown">
        <h3>Payment Summary</h3>
        <div class="amount-row">
            <span>Total Amount:</span>
            <strong>{{ paymentIntent.amount }} {{ currency }}</strong>
        </div>
        
        <template v-if="paymentIntent.payment_type === 'destination_charge'">
            <div class="amount-row">
                <span>Provider Receives:</span>
                <strong>{{ paymentIntent.provider_amount }} {{ currency }}</strong>
            </div>
            <div class="amount-row">
                <span>Platform Commission:</span>
                <strong>{{ paymentIntent.commission_amount }} {{ currency }}</strong>
            </div>
        </template>
    </div>
</template>

<script>
export default {
    props: {
        paymentIntent: Object
    },
    computed: {
        currency() {
            return this.paymentIntent.currency?.toUpperCase() || 'USD';
        }
    }
}
</script>
```

## Payment Flow (No Changes Needed)

The payment flow remains exactly the same:

```javascript
// 1. Create payment intent (unchanged)
const createPaymentIntent = async () => {
    const response = await fetch('/api/training-center/codes/create-payment-intent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
            acc_id: 1,
            course_id: 2,
            quantity: 10,
            discount_code: null
        })
    });
    
    return await response.json();
};

// 2. Confirm payment with Stripe (unchanged)
const confirmPayment = async (clientSecret) => {
    const { error } = await stripe.confirmPayment({
        clientSecret,
        confirmParams: {
            return_url: 'https://yourapp.com/payment-success',
        },
    });
    
    if (error) {
        console.error('Payment failed:', error);
    }
};

// 3. Complete purchase (unchanged)
const completePurchase = async (paymentIntentId) => {
    const response = await fetch('/api/training-center/codes/purchase', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
            acc_id: 1,
            course_id: 2,
            quantity: 10,
            payment_method: 'credit_card',
            payment_intent_id: paymentIntentId
        })
    });
    
    return await response.json();
};
```

## TypeScript Types

If you're using TypeScript, update your types:

```typescript
interface PaymentIntentResponse {
    success: boolean;
    client_secret: string;
    payment_intent_id: string;
    amount: number;
    currency: string;
    
    // New fields
    commission_amount?: string;
    provider_amount?: string | null;
    payment_type: 'destination_charge' | 'standard';
    
    // Code purchase specific
    total_amount?: string;
    discount_amount?: string;
    final_amount?: string;
    unit_price?: string;
    quantity?: number;
}
```

## UI/UX Recommendations

### 1. Show Payment Breakdown (Optional)

Display the payment split to provide transparency:

```
┌─────────────────────────────┐
│   Payment Summary           │
├─────────────────────────────┤
│ Total Amount:     1,000 EGP │
│                             │
│ Provider Receives:  900 EGP │
│ Platform Fee:      100 EGP  │
└─────────────────────────────┘
```

### 2. Handle Both Payment Types

```javascript
const displayPaymentInfo = (paymentIntent) => {
    if (paymentIntent.payment_type === 'destination_charge') {
        // Show detailed breakdown
        return {
            total: paymentIntent.amount,
            providerAmount: paymentIntent.provider_amount,
            commission: paymentIntent.commission_amount
        };
    } else {
        // Show standard payment info
        return {
            total: paymentIntent.amount
        };
    }
};
```

### 3. Error Handling (Unchanged)

Error handling remains the same:

```javascript
try {
    const result = await createPaymentIntent(data);
    
    if (!result.success) {
        // Handle error
        console.error('Failed to create payment intent:', result.error);
        return;
    }
    
    // Continue with payment...
} catch (error) {
    console.error('Error:', error);
}
```

## Testing

### Test Scenarios

1. **Destination Charge Payment**
   - ACC with Stripe account configured
   - Should see `payment_type: "destination_charge"`
   - Should see `commission_amount` and `provider_amount`

2. **Standard Payment (Fallback)**
   - ACC without Stripe account
   - Should see `payment_type: "standard"`
   - `provider_amount` will be `null`

### Example Test

```javascript
describe('Payment Intent Creation', () => {
    it('should return destination charge details when ACC has Stripe account', async () => {
        const response = await createPaymentIntent({
            acc_id: 1, // ACC with Stripe account
            course_id: 2,
            quantity: 10
        });
        
        expect(response.success).toBe(true);
        expect(response.payment_type).toBe('destination_charge');
        expect(response.commission_amount).toBeDefined();
        expect(response.provider_amount).toBeDefined();
    });
    
    it('should return standard payment when ACC has no Stripe account', async () => {
        const response = await createPaymentIntent({
            acc_id: 2, // ACC without Stripe account
            course_id: 2,
            quantity: 10
        });
        
        expect(response.success).toBe(true);
        expect(response.payment_type).toBe('standard');
        expect(response.provider_amount).toBeNull();
    });
});
```

## Migration Checklist

- [x] **No breaking changes** - Existing code continues to work
- [x] **New fields are optional** - Can be ignored if not needed
- [ ] **Optional**: Update TypeScript types to include new fields
- [ ] **Optional**: Add UI to display payment breakdown
- [ ] **Optional**: Update tests to check new fields

## FAQ

### Q: Do I need to change my existing code?

**A**: No! The changes are backward compatible. Your existing code will continue to work.

### Q: What if `provider_amount` is `null`?

**A**: This means the payment is using standard flow (ACC doesn't have Stripe account). You can ignore this field or handle it gracefully.

### Q: Should I display the commission to users?

**A**: Optional. It's up to your UX design. Some platforms show it for transparency, others don't.

### Q: Will the payment amount change?

**A**: No. The `amount` field remains the same. The new fields just show how it's split.

### Q: What happens if Stripe account is removed?

**A**: The system automatically falls back to standard payment. No errors, seamless transition.

## Support

If you encounter any issues:

1. Check the API response for `payment_type` field
2. Verify `commission_amount` and `provider_amount` are present for destination charges
3. Ensure you're handling `null` values for `provider_amount` in standard payments
4. Check browser console for any errors

## Summary

✅ **No code changes required** - Everything works as before  
✅ **New fields available** - Use them if you want to show payment breakdown  
✅ **Backward compatible** - Existing implementations continue to work  
✅ **Automatic fallback** - System handles both payment types seamlessly  

The payment system is now more transparent and efficient, but your frontend code doesn't need any changes unless you want to take advantage of the new payment breakdown information!

