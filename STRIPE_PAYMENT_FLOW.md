# Stripe Payment Flow Implementation Guide

## Overview
This document describes the complete Stripe payment integration for all payment mechanisms in the BOMEQP system. Each payment type follows a two-step flow: creating a payment intent and then completing the payment after Stripe confirmation.

---

## Payment Flow Architecture

```
Frontend                          Backend                          Stripe
   |                                |                                |
   |---(1) Create Payment Intent--->|                                |
   |                                |---Create Payment Intent------->|
   |<---(client_secret)-------------|<---(payment_intent_id)---------|
   |                                |                                |
   |---(2) Confirm with Stripe)---->|                                |
   |                                |                            (Stripe API)
   |<---(Payment Success)-----------|                                |
   |                                |                                |
   |---(3) Complete Payment-------->|                                |
   |    (with payment_intent_id)    |                                |
   |                                |---Verify Payment Intent------->|
   |                                |---Create Transaction            |
   |                                |---Update Records                |
   |<---(Success Response)----------|                                |
```

---

## Prerequisites

### 1. Stripe Configuration

The system uses `StripeSetting` model to manage Stripe configuration. Ensure that:
- Active Stripe settings exist in the database
- `is_active` is set to `true`
- `secret_key`, `publishable_key`, and `webhook_secret` are configured

### 2. StripeService

The `StripeService` class handles all Stripe operations:
- `createPaymentIntent()` - Creates a Stripe payment intent
- `verifyPaymentIntent()` - Verifies payment intent status, amount, and metadata
- `retrievePaymentIntent()` - Retrieves a payment intent
- `isConfigured()` - Checks if Stripe is properly configured

---

## Payment Types Implementation

### 1. ACC Subscription Payment

#### A. Create Payment Intent

**Endpoint:** `POST /api/acc/subscription/payment-intent`

**Request:**
```json
{
  "amount": 10000.00
}
```

**Response:**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 10000.00,
  "currency": "USD"
}
```

**Metadata:**
- `acc_id`: ACC ID
- `user_id`: User ID
- `type`: "subscription"
- `amount`: Payment amount

#### B. Complete Payment

**Endpoint:** `POST /api/acc/subscription/payment`

**Request:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx"
}
```

**Flow:**
1. Verifies payment intent with Stripe
2. Creates transaction record
3. Creates/updates subscription
4. Reactivates ACC if suspended

---

### 2. ACC Subscription Renewal

#### A. Create Payment Intent

**Endpoint:** `POST /api/acc/subscription/renew-payment-intent`

**Request:**
```json
{
  "amount": 10000.00
}
```

**Response:**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 10000.00,
  "currency": "USD"
}
```

**Metadata:**
- `acc_id`: ACC ID
- `user_id`: User ID
- `type`: "subscription_renewal"
- `subscription_id`: Current subscription ID
- `amount`: Payment amount

#### B. Complete Renewal

**Endpoint:** `PUT /api/acc/subscription/renew`

**Request:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx",
  "auto_renew": false
}
```

**Flow:**
1. Verifies payment intent with Stripe
2. Creates transaction record
3. Creates new subscription record
4. Reactivates ACC if suspended

---

### 3. Instructor Authorization Payment

#### A. Create Payment Intent

**Endpoint:** `POST /api/training-center/instructors/authorizations/{id}/payment-intent`

**Response:**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 500.00,
  "currency": "USD",
  "authorization": { ... }
}
```

**Metadata:**
- `authorization_id`: Authorization ID
- `training_center_id`: Training Center ID
- `acc_id`: ACC ID
- `instructor_id`: Instructor ID
- `type`: "instructor_authorization"
- `amount`: Authorization price

#### B. Complete Payment

**Endpoint:** `POST /api/training-center/instructors/authorizations/{id}/pay`

**Request:**
```json
{
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx"
}
```

**Flow:**
1. Verifies payment intent with Stripe
2. Processes wallet payment (if applicable)
3. Creates transaction record
4. Creates commission ledger entries
5. Updates authorization status to "paid"

---

### 4. Certificate Code Purchase

#### A. Create Payment Intent

**Endpoint:** `POST /api/training-center/codes/payment-intent`

**Request:**
```json
{
  "acc_id": 3,
  "course_id": 5,
  "quantity": 10,
  "discount_code": "SAVE20"
}
```

**Response:**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 3600.00,
  "currency": "USD",
  "total_amount": "4000.00",
  "discount_amount": "400.00",
  "final_amount": "3600.00",
  "unit_price": "400.00",
  "quantity": 10
}
```

**Metadata:**
- `payer_id`: Training Center ID
- `payee_id`: ACC ID
- `course_id`: Course ID
- `quantity`: Purchase quantity
- `type`: "code_purchase"
- `discount_code`: Discount code (if applicable)

#### B. Complete Purchase

**Endpoint:** `POST /api/training-center/codes/purchase`

**Request:**
```json
{
  "acc_id": 3,
  "course_id": 5,
  "quantity": 10,
  "discount_code": "SAVE20",
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx"
}
```

**Flow:**
1. Verifies payment intent with Stripe
2. Processes wallet payment (if applicable)
3. Creates transaction record
4. Creates code batch
5. Generates certificate codes
6. Creates commission ledger entries
7. Updates discount code usage

---

## Payment Intent Verification

The `verifyPaymentIntent()` method performs three critical checks:

### 1. Status Verification
- Ensures payment intent status is `succeeded`
- Rejects payments with other statuses (pending, failed, etc.)

### 2. Amount Verification
- Compares payment intent amount with expected amount
- Stripe uses cents, so amounts are converted accordingly
- Throws exception if amounts don't match

### 3. Metadata Verification
- Verifies key metadata fields match expected values
- Ensures payment intent was created for the correct transaction
- Prevents payment intent reuse or manipulation

**Example:**
```php
$this->stripeService->verifyPaymentIntent(
    $paymentIntentId,
    $expectedAmount,
    [
        'acc_id' => (string)$acc->id,
        'type' => 'subscription',
    ]
);
```

---

## Error Handling

### Common Error Responses

**Stripe Not Configured:**
```json
{
  "message": "Stripe payment is not configured"
}
```

**Payment Verification Failed:**
```json
{
  "message": "Payment verification failed",
  "error": "Payment not completed. Status: requires_payment_method"
}
```

**Amount Mismatch:**
```json
{
  "message": "Payment verification failed",
  "error": "Payment amount mismatch. Expected: 10000, Received: 9500"
}
```

**Metadata Mismatch:**
```json
{
  "message": "Payment verification failed",
  "error": "Metadata mismatch for key: acc_id. Expected: 5, Received: 3"
}
```

---

## Security Best Practices

### 1. Always Verify Payment Intents

Never trust frontend confirmation alone. Always verify payment intents on the backend before processing payments:

```php
// ✅ Good - Verify on backend
$this->stripeService->verifyPaymentIntent($paymentIntentId, $amount, $metadata);

// ❌ Bad - Trust frontend only
if ($frontendConfirmed) {
    // Process payment
}
```

### 2. Use Metadata for Verification

Include relevant transaction data in payment intent metadata to prevent payment intent reuse:

```php
$metadata = [
    'acc_id' => (string)$acc->id,
    'type' => 'subscription',
    'amount' => (string)$amount,
];
```

### 3. Convert Amounts Correctly

Stripe uses smallest currency unit (cents for USD). Always convert:

```php
// Amount to Stripe (multiply by 100)
$stripeAmount = (int)($amount * 100);

// Stripe amount back (divide by 100)
$amount = $stripeAmount / 100;
```

### 4. Use Database Transactions

Wrap all payment processing in database transactions:

```php
DB::beginTransaction();
try {
    // Create transaction
    // Update records
    // Process business logic
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### 5. Never Expose Secret Keys

- Secret keys should only be in `.env` or database
- Only publishable keys should be sent to frontend
- Use `GET /api/stripe/config` to provide publishable key to frontend

---

## Frontend Integration Example

```javascript
// Step 1: Create payment intent
const createPaymentIntentResponse = await fetch('/api/acc/subscription/payment-intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 10000.00
  })
});

const { client_secret, payment_intent_id } = await createPaymentIntentResponse.json();

// Step 2: Initialize Stripe
const stripe = Stripe('YOUR_PUBLISHABLE_KEY'); // Get from /api/stripe/config

// Step 3: Confirm payment with Stripe
const { error, paymentIntent } = await stripe.confirmCardPayment(client_secret, {
  payment_method: {
    card: cardElement,
  }
});

if (error) {
  console.error('Payment failed:', error);
} else if (paymentIntent.status === 'succeeded') {
  // Step 4: Complete payment on backend
  const completePaymentResponse = await fetch('/api/acc/subscription/payment', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      amount: 10000.00,
      payment_method: 'credit_card',
      payment_intent_id: payment_intent_id
    })
  });

  const result = await completePaymentResponse.json();
  console.log('Payment completed:', result);
}
```

---

## Testing

### Test Payment Intents

Use Stripe test cards for testing:
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- Requires authentication: `4000 0025 0000 3155`

### Test Payment Flow

1. Create payment intent using test API keys
2. Use test card to confirm payment
3. Verify payment intent on backend
4. Check transaction and related records are created

---

## Summary of Endpoints

### Payment Intent Creation
- `POST /api/acc/subscription/payment-intent` - ACC subscription
- `POST /api/acc/subscription/renew-payment-intent` - Subscription renewal
- `POST /api/training-center/instructors/authorizations/{id}/payment-intent` - Instructor authorization
- `POST /api/training-center/codes/payment-intent` - Code purchase

### Payment Completion
- `POST /api/acc/subscription/payment` - ACC subscription
- `PUT /api/acc/subscription/renew` - Subscription renewal
- `POST /api/training-center/instructors/authorizations/{id}/pay` - Instructor authorization
- `POST /api/training-center/codes/purchase` - Code purchase

### Configuration
- `GET /api/stripe/config` - Get publishable key

---

## Migration Notes

**Breaking Changes:**
- Payment endpoints now require `payment_intent_id` when using `credit_card` payment method
- Payment intent verification is mandatory for credit card payments
- Old payment methods without verification will fail

**Backward Compatibility:**
- Wallet payments remain unchanged
- `payment_method` field still supports both `wallet` and `credit_card`
- Existing wallet payment flows continue to work

