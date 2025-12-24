# Frontend Payment Flow Guide

## Overview

This guide provides complete instructions for implementing payment flows in the BOMEQP frontend application. The system uses Stripe for credit card payments and supports wallet payments as an alternative.

**Important:** All payment flows follow a two-step process:
1. **Create Payment Intent** - Get client secret from backend
2. **Complete Payment** - Confirm payment with Stripe, then finalize on backend

---

## Table of Contents

1. [Payment Flow Architecture](#payment-flow-architecture)
2. [Prerequisites](#prerequisites)
3. [Payment Types](#payment-types)
   - [ACC Subscription Payment](#1-acc-subscription-payment)
   - [Subscription Renewal](#2-subscription-renewal)
   - [Instructor Authorization Payment](#3-instructor-authorization-payment)
   - [Certificate Code Purchase](#4-certificate-code-purchase)
4. [Common Implementation](#common-implementation)
5. [Error Handling](#error-handling)
6. [Best Practices](#best-practices)

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
   |                            (Stripe API)                         |
   |<---(Payment Success)-----------|                                |
   |                                |                                |
   |---(3) Complete Payment-------->|                                |
   |    (with payment_intent_id)    |                                |
   |                                |---Verify Payment Intent------->|
   |                                |---Update Records               |
   |<---(Success Response)----------|                                |
```

---

## Prerequisites

### 1. Install Required Packages

```bash
# Core Stripe library
npm install @stripe/stripe-js

# React components (if using React)
npm install @stripe/react-stripe-js

# Vue.js (if using Vue)
npm install @stripe/stripe-js vue-stripe
```

### 2. Get Stripe Publishable Key

**Important:** Always get the publishable key from the backend to ensure you're using the correct key (test/live).

**Endpoint:** `GET /api/stripe/config`

```javascript
// Get Stripe configuration
async function getStripeConfig() {
  const response = await fetch('/api/stripe/config', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });

  const data = await response.json();
  
  if (data.success && data.publishable_key) {
    return data.publishable_key;
  }
  
  throw new Error('Stripe is not configured');
}

// Initialize Stripe
import { loadStripe } from '@stripe/stripe-js';

const publishableKey = await getStripeConfig();
const stripe = await loadStripe(publishableKey);
```

**Response:**
```json
{
  "success": true,
  "publishable_key": "pk_test_...",
  "is_configured": true
}
```

### 3. Required Dependencies

- `@stripe/stripe-js` - Stripe.js core library
- `@stripe/react-stripe-js` - React components (if using React)

### 4. Setup Stripe Elements (React)

```jsx
import { loadStripe } from '@stripe/stripe-js';
import { Elements } from '@stripe/react-stripe-js';

const stripePromise = loadStripe(publishableKey);

function App() {
  return (
    <Elements stripe={stripePromise}>
      {/* Your payment forms */}
    </Elements>
  );
}
```

### 5. Setup Stripe Elements (Vue.js)

```javascript
import { loadStripe } from '@stripe/stripe-js';
import Vue from 'vue';
import VueStripe from 'vue-stripe';

Vue.use(VueStripe, publishableKey);
```

### 6. Setup Stripe Elements (Vanilla JS)

```html
<script src="https://js.stripe.com/v3/"></script>
<script>
  const stripe = Stripe('pk_test_...');
  const elements = stripe.elements();
  const cardElement = elements.create('card');
  cardElement.mount('#card-element');
</script>
```

---

## Payment Types

### 1. ACC Subscription Payment

#### Step 1: Create Payment Intent

**Endpoint:** `POST /api/acc/subscription/payment-intent`

**Request:**
```javascript
const response = await fetch('/api/acc/subscription/payment-intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 10000.00
  })
});

const { client_secret, payment_intent_id, amount, currency } = await response.json();
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

#### Step 2: Confirm Payment with Stripe

```javascript
import { loadStripe } from '@stripe/stripe-js';

const stripe = await loadStripe(publishableKey);

// Confirm payment
const { error, paymentIntent } = await stripe.confirmCardPayment(client_secret, {
  payment_method: {
    card: cardElement, // Stripe Elements card element
    billing_details: {
      name: 'ACC Name',
      email: 'acc@example.com'
    }
  }
});

if (error) {
  console.error('Payment failed:', error);
  // Handle error
} else if (paymentIntent.status === 'succeeded') {
  // Proceed to step 3
}
```

#### Step 3: Complete Payment

**Endpoint:** `POST /api/acc/subscription/payment`

**Request:**
```javascript
const response = await fetch('/api/acc/subscription/payment', {
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

const result = await response.json();
```

**Response:**
```json
{
  "message": "Payment successful",
  "subscription": {
    "id": 1,
    "subscription_start_date": "2024-01-15",
    "subscription_end_date": "2025-01-15",
    "payment_status": "paid"
  }
}
```

#### Complete Example (React)

```jsx
import React, { useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';

function SubscriptionPayment({ amount, onSuccess }) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const stripe = useStripe();
  const elements = useElements();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // Step 1: Create payment intent
      const intentResponse = await fetch('/api/acc/subscription/payment-intent', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ amount })
      });

      if (!intentResponse.ok) {
        throw new Error('Failed to create payment intent');
      }

      const { client_secret, payment_intent_id } = await intentResponse.json();

      // Step 2: Confirm payment
      const cardElement = elements.getElement(CardElement);
      const { error: confirmError, paymentIntent } = await stripe.confirmCardPayment(
        client_secret,
        {
          payment_method: {
            card: cardElement
          }
        }
      );

      if (confirmError) {
        throw confirmError;
      }

      if (paymentIntent.status === 'succeeded') {
        // Step 3: Complete payment
        const completeResponse = await fetch('/api/acc/subscription/payment', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            amount,
            payment_method: 'credit_card',
            payment_intent_id
          })
        });

        if (!completeResponse.ok) {
          throw new Error('Failed to complete payment');
        }

        const result = await completeResponse.json();
        onSuccess(result);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <CardElement />
      {error && <div className="error">{error}</div>}
      <button type="submit" disabled={loading || !stripe}>
        {loading ? 'Processing...' : 'Pay'}
      </button>
    </form>
  );
}

// Usage
function SubscriptionPage() {
  const stripePromise = loadStripe(publishableKey);

  return (
    <Elements stripe={stripePromise}>
      <SubscriptionPayment 
        amount={10000.00} 
        onSuccess={(result) => console.log('Payment successful:', result)}
      />
    </Elements>
  );
}
```

---

### 2. Subscription Renewal

The flow is identical to subscription payment, but uses different endpoints:

#### Endpoints

1. **Create Payment Intent:** `POST /api/acc/subscription/renew-payment-intent`
2. **Complete Renewal:** `PUT /api/acc/subscription/renew`

#### Example

```javascript
// Step 1: Create renewal payment intent
const intentResponse = await fetch('/api/acc/subscription/renew-payment-intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ amount: 10000.00 })
});

const { client_secret, payment_intent_id } = await intentResponse.json();

// Step 2: Confirm with Stripe (same as above)

// Step 3: Complete renewal
const renewResponse = await fetch('/api/acc/subscription/renew', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 10000.00,
    payment_method: 'credit_card',
    payment_intent_id,
    auto_renew: false
  })
});
```

---

### 3. Instructor Authorization Payment

#### Step 1: Create Payment Intent

**Endpoint:** `POST /api/training-center/instructors/authorizations/{authorization_id}/payment-intent`

**Request:**
```javascript
const authorizationId = 1; // ID of the authorization request

const response = await fetch(
  `/api/training-center/instructors/authorizations/${authorizationId}/payment-intent`,
  {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }
);

const { client_secret, payment_intent_id, amount, currency, authorization } = await response.json();
```

**Response:**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 500.00,
  "currency": "USD",
  "authorization": {
    "id": 1,
    "instructor_id": 5,
    "authorization_price": 500.00
  }
}
```

#### Step 2: Confirm Payment with Stripe (Same as above)

#### Step 3: Complete Payment

**Endpoint:** `POST /api/training-center/instructors/authorizations/{authorization_id}/pay`

**Request:**
```javascript
const response = await fetch(
  `/api/training-center/instructors/authorizations/${authorizationId}/pay`,
  {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      payment_method: 'credit_card',
      payment_intent_id
    })
  }
);

const result = await response.json();
```

**Response:**
```json
{
  "message": "Payment successful. Instructor is now officially authorized.",
  "authorization": {
    "id": 1,
    "payment_status": "paid",
    "group_admin_status": "completed"
  },
  "transaction": { ... }
}
```

---

### 4. Certificate Code Purchase

This is the most complex payment flow as it includes discount codes and quantity selection.

#### Step 1: Create Payment Intent

**Endpoint:** `POST /api/training-center/codes/payment-intent`

**Request:**
```javascript
const response = await fetch('/api/training-center/codes/payment-intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    acc_id: 3,
    course_id: 5,
    quantity: 10,
    discount_code: 'SAVE20' // optional
  })
});

const result = await response.json();
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

#### Step 2: Confirm Payment with Stripe

```javascript
const { error, paymentIntent } = await stripe.confirmCardPayment(client_secret, {
  payment_method: {
    card: cardElement
  }
});
```

#### Step 3: Complete Purchase

**Endpoint:** `POST /api/training-center/codes/purchase`

**Request:**
```javascript
const response = await fetch('/api/training-center/codes/purchase', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    acc_id: 3,
    course_id: 5,
    quantity: 10,
    discount_code: 'SAVE20', // optional, same as step 1
    payment_method: 'credit_card',
    payment_intent_id: payment_intent_id
  })
});

const result = await response.json();
```

**Response:**
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "training_center_id": 2,
    "acc_id": 3,
    "course_id": 5,
    "quantity": 10,
    "total_amount": "4000.00",
    "discount_amount": "400.00",
    "final_amount": "3600.00",
    "payment_method": "credit_card",
    "payment_status": "completed",
    "created_at": "2024-01-15T10:30:00.000000Z"
  },
  "codes": [
    {
      "id": 1,
      "code": "ABC123XYZ456",
      "status": "available"
    },
    {
      "id": 2,
      "code": "DEF789GHI012",
      "status": "available"
    }
  ]
}
```

#### Complete Example (React Hook)

```jsx
import { useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { useStripe, useElements } from '@stripe/react-stripe-js';

function useCodePurchase() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const stripe = useStripe();
  const elements = useElements();

  const purchaseCodes = async ({ accId, courseId, quantity, discountCode = null }) => {
    setLoading(true);
    setError(null);

    try {
      // Step 1: Create payment intent
      const intentResponse = await fetch('/api/training-center/codes/payment-intent', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          acc_id: accId,
          course_id: courseId,
          quantity: quantity,
          discount_code: discountCode
        })
      });

      if (!intentResponse.ok) {
        const errorData = await intentResponse.json();
        throw new Error(errorData.message || 'Failed to create payment intent');
      }

      const { client_secret, payment_intent_id, final_amount } = await intentResponse.json();

      // Step 2: Confirm payment with Stripe
      const cardElement = elements.getElement(CardElement);
      const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(
        client_secret,
        {
          payment_method: {
            card: cardElement
          }
        }
      );

      if (stripeError) {
        throw stripeError;
      }

      if (paymentIntent.status === 'succeeded') {
        // Step 3: Complete purchase
        const purchaseResponse = await fetch('/api/training-center/codes/purchase', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            acc_id: accId,
            course_id: courseId,
            quantity: quantity,
            discount_code: discountCode,
            payment_method: 'credit_card',
            payment_intent_id
          })
        });

        if (!purchaseResponse.ok) {
          const errorData = await purchaseResponse.json();
          throw new Error(errorData.message || 'Failed to complete purchase');
        }

        const result = await purchaseResponse.json();
        return result;
      }
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return { purchaseCodes, loading, error };
}
```

---

## Common Implementation

### Reusable Payment Hook/Function

```javascript
// utils/stripePayment.js

import { loadStripe } from '@stripe/stripe-js';

export async function processStripePayment({
  stripe,
  cardElement,
  createIntentEndpoint,
  createIntentData,
  completePaymentEndpoint,
  completePaymentData,
  onSuccess,
  onError
}) {
  try {
    // Step 1: Create payment intent
    const intentResponse = await fetch(createIntentEndpoint, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(createIntentData)
    });

    if (!intentResponse.ok) {
      const error = await intentResponse.json();
      throw new Error(error.message || 'Failed to create payment intent');
    }

    const { client_secret, payment_intent_id } = await intentResponse.json();

    // Step 2: Confirm payment with Stripe
    const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(
      client_secret,
      {
        payment_method: {
          card: cardElement
        }
      }
    );

    if (stripeError) {
      throw stripeError;
    }

    if (paymentIntent.status === 'succeeded') {
      // Step 3: Complete payment
      const completeResponse = await fetch(completePaymentEndpoint, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          ...completePaymentData,
          payment_intent_id
        })
      });

      if (!completeResponse.ok) {
        const error = await completeResponse.json();
        throw new Error(error.message || 'Failed to complete payment');
      }

      const result = await completeResponse.json();
      onSuccess(result);
      return result;
    }
  } catch (error) {
    onError(error);
    throw error;
  }
}

// Usage
await processStripePayment({
  stripe,
  cardElement: elements.getElement(CardElement),
  createIntentEndpoint: '/api/acc/subscription/payment-intent',
  createIntentData: { amount: 10000 },
  completePaymentEndpoint: '/api/acc/subscription/payment',
  completePaymentData: {
    amount: 10000,
    payment_method: 'credit_card'
  },
  onSuccess: (result) => console.log('Success:', result),
  onError: (error) => console.error('Error:', error)
});
```

---

## Wallet Payments

For wallet payments, you can skip Stripe integration and call the payment endpoint directly:

### Example: Wallet Payment for Code Purchase

```javascript
const response = await fetch('/api/training-center/codes/purchase', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    acc_id: 3,
    course_id: 5,
    quantity: 10,
    discount_code: 'SAVE20',
    payment_method: 'wallet'
    // No payment_intent_id needed for wallet payments
  })
});
```

---

## Error Handling

### Common Error Responses

#### 1. Validation Errors (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "acc_id": ["The acc id field is required."],
    "quantity": ["The quantity must be at least 1."]
  }
}
```

**Handling:**
```javascript
catch (error) {
  if (error.response?.status === 422) {
    const errors = error.response.data.errors;
    // Display validation errors to user
    Object.keys(errors).forEach(field => {
      console.error(`${field}: ${errors[field][0]}`);
    });
  }
}
```

#### 2. Payment Intent Creation Failed (500)

```json
{
  "success": false,
  "message": "Failed to create payment intent",
  "error": "Stripe API error message"
}
```

#### 3. Payment Verification Failed (400)

```json
{
  "message": "Payment verification failed",
  "error": "Payment not completed. Status: requires_payment_method"
}
```

#### 4. Stripe Not Configured (400)

```json
{
  "message": "Stripe payment is not configured"
}
```

### Complete Error Handling Example

```javascript
async function handlePayment(paymentData) {
  try {
    // Step 1: Create payment intent
    const intentResponse = await fetch('/api/training-center/codes/payment-intent', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(paymentData)
    });

    if (!intentResponse.ok) {
      const errorData = await intentResponse.json();
      
      if (intentResponse.status === 422) {
        // Validation errors
        const errors = errorData.errors || {};
        const errorMessages = Object.values(errors).flat();
        throw new Error(errorMessages.join(', '));
      } else if (intentResponse.status === 400) {
        // Bad request (e.g., Stripe not configured)
        throw new Error(errorData.message || 'Payment service unavailable');
      } else {
        throw new Error(errorData.message || 'Failed to create payment intent');
      }
    }

    const { client_secret, payment_intent_id } = await intentResponse.json();

    // Step 2: Confirm with Stripe
    const { error: stripeError } = await stripe.confirmCardPayment(client_secret, {
      payment_method: { card: cardElement }
    });

    if (stripeError) {
      // Handle Stripe errors
      if (stripeError.type === 'card_error') {
        throw new Error(stripeError.message);
      } else if (stripeError.type === 'validation_error') {
        throw new Error('Invalid card information');
      } else {
        throw new Error('Payment failed. Please try again.');
      }
    }

    // Step 3: Complete payment
    const completeResponse = await fetch('/api/training-center/codes/purchase', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ...paymentData,
        payment_method: 'credit_card',
        payment_intent_id
      })
    });

    if (!completeResponse.ok) {
      const errorData = await completeResponse.json();
      throw new Error(errorData.message || 'Failed to complete payment');
    }

    const result = await completeResponse.json();
    return result;

  } catch (error) {
    // Log error for debugging
    console.error('Payment error:', error);
    
    // Show user-friendly error message
    showError(error.message || 'An error occurred during payment');
    
    throw error;
  }
}
```

---

## Best Practices

### 1. Always Verify Amount Before Payment

Display the amount to the user before creating payment intent:

```javascript
// Show amount breakdown
const amountBreakdown = {
  unitPrice: 400.00,
  quantity: 10,
  subtotal: 4000.00,
  discount: discountCode ? 400.00 : 0,
  total: discountCode ? 3600.00 : 4000.00
};

// Confirm with user
if (!confirm(`Total amount: $${amountBreakdown.total}. Proceed?`)) {
  return;
}

// Then create payment intent
```

### 2. Store Payment Intent ID

Store the `payment_intent_id` locally in case of network issues:

```javascript
// Store payment intent ID
localStorage.setItem('pending_payment_intent_id', payment_intent_id);

// After successful payment, clear it
localStorage.removeItem('pending_payment_intent_id');
```

### 3. Handle Network Failures

Implement retry logic for network failures:

```javascript
async function fetchWithRetry(url, options, retries = 3) {
  for (let i = 0; i < retries; i++) {
    try {
      const response = await fetch(url, options);
      if (response.ok) {
        return response;
      }
      throw new Error(`HTTP ${response.status}`);
    } catch (error) {
      if (i === retries - 1) throw error;
      await new Promise(resolve => setTimeout(resolve, 1000 * (i + 1)));
    }
  }
}
```

### 4. Show Loading States

Always show loading indicators during payment processing:

```javascript
const [paymentStatus, setPaymentStatus] = useState('idle'); // idle, creating, confirming, completing, success, error

// In your payment function
setPaymentStatus('creating');
// ... create payment intent

setPaymentStatus('confirming');
// ... confirm with Stripe

setPaymentStatus('completing');
// ... complete payment

setPaymentStatus('success');
```

### 5. Validate Data Before Creating Payment Intent

Validate all required fields before making API calls:

```javascript
function validatePaymentData({ accId, courseId, quantity }) {
  const errors = {};
  
  if (!accId || accId <= 0) {
    errors.accId = 'Please select an ACC';
  }
  
  if (!courseId || courseId <= 0) {
    errors.courseId = 'Please select a course';
  }
  
  if (!quantity || quantity < 1) {
    errors.quantity = 'Quantity must be at least 1';
  }
  
  return {
    isValid: Object.keys(errors).length === 0,
    errors
  };
}

// Usage
const { isValid, errors } = validatePaymentData({ accId, courseId, quantity });
if (!isValid) {
  // Show errors to user
  return;
}
```

### 6. Handle Card Element Properly

Always check if Stripe and Elements are loaded:

```javascript
if (!stripe || !elements) {
  return <div>Loading payment form...</div>;
}

const cardElement = elements.getElement(CardElement);
if (!cardElement) {
  return <div>Card element not found</div>;
}
```

### 7. Clear Card Element After Payment

Clear sensitive card data after successful payment:

```javascript
if (paymentIntent.status === 'succeeded') {
  // Clear card element
  cardElement.clear();
  
  // Complete payment on backend
  // ...
}
```

---

## Complete React Component Example

```jsx
import React, { useState, useEffect } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';

const stripePromise = loadStripe('YOUR_PUBLISHABLE_KEY'); // Get from /api/stripe/config

function PaymentForm({ amount, onSuccess, onError }) {
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState('idle');
  const [error, setError] = useState(null);
  const stripe = useStripe();
  const elements = useElements();

  useEffect(() => {
    // Get publishable key from backend
    fetch('/api/stripe/config', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
      .then(res => res.json())
      .then(data => {
        if (data.publishable_key) {
          // Initialize Stripe with key from backend
        }
      });
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!stripe || !elements) {
      return;
    }

    setLoading(true);
    setStatus('creating');
    setError(null);

    try {
      // Step 1: Create payment intent
      const intentRes = await fetch('/api/acc/subscription/payment-intent', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ amount })
      });

      if (!intentRes.ok) {
        const err = await intentRes.json();
        throw new Error(err.message || 'Failed to create payment intent');
      }

      const { client_secret, payment_intent_id } = await intentRes.json();

      // Step 2: Confirm payment
      setStatus('confirming');
      const cardElement = elements.getElement(CardElement);
      
      const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(
        client_secret,
        { payment_method: { card: cardElement } }
      );

      if (stripeError) {
        throw stripeError;
      }

      if (paymentIntent.status === 'succeeded') {
        // Step 3: Complete payment
        setStatus('completing');
        cardElement.clear(); // Clear card data

        const completeRes = await fetch('/api/acc/subscription/payment', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            amount,
            payment_method: 'credit_card',
            payment_intent_id
          })
        });

        if (!completeRes.ok) {
          const err = await completeRes.json();
          throw new Error(err.message || 'Failed to complete payment');
        }

        const result = await completeRes.json();
        setStatus('success');
        onSuccess(result);
      }
    } catch (err) {
      setError(err.message);
      setStatus('error');
      onError(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div className="payment-form">
        <CardElement
          options={{
            style: {
              base: {
                fontSize: '16px',
                color: '#424770',
                '::placeholder': {
                  color: '#aab7c4',
                },
              },
              invalid: {
                color: '#9e2146',
              },
            },
          }}
        />
      </div>
      
      {error && <div className="error">{error}</div>}
      
      <button 
        type="submit" 
        disabled={loading || !stripe || status === 'success'}
      >
        {status === 'creating' && 'Creating payment...'}
        {status === 'confirming' && 'Processing payment...'}
        {status === 'completing' && 'Finalizing payment...'}
        {status === 'success' && 'Payment Successful!'}
        {status === 'idle' && 'Pay'}
      </button>
    </form>
  );
}

// Usage
function PaymentPage() {
  return (
    <Elements stripe={stripePromise}>
      <PaymentForm
        amount={10000.00}
        onSuccess={(result) => {
          console.log('Payment successful:', result);
          // Redirect or show success message
        }}
        onError={(error) => {
          console.error('Payment failed:', error);
          // Show error message
        }}
      />
    </Elements>
  );
}
```

---

## API Endpoints Summary

### Payment Intent Creation

| Payment Type | Endpoint | Method |
|--------------|----------|--------|
| ACC Subscription | `/api/acc/subscription/payment-intent` | POST |
| Subscription Renewal | `/api/acc/subscription/renew-payment-intent` | POST |
| Instructor Authorization | `/api/training-center/instructors/authorizations/{id}/payment-intent` | POST |
| Code Purchase | `/api/training-center/codes/payment-intent` | POST |

### Payment Completion

| Payment Type | Endpoint | Method |
|--------------|----------|--------|
| ACC Subscription | `/api/acc/subscription/payment` | POST |
| Subscription Renewal | `/api/acc/subscription/renew` | PUT |
| Instructor Authorization | `/api/training-center/instructors/authorizations/{id}/pay` | POST |
| Code Purchase | `/api/training-center/codes/purchase` | POST |

### Configuration

| Endpoint | Purpose |
|----------|---------|
| `/api/stripe/config` | Get Stripe publishable key |

---

## Testing

### Test Cards

Use these test card numbers in Stripe test mode:

- **Success:** `4242 4242 4242 4242`
- **Decline:** `4000 0000 0000 0002`
- **Requires Authentication:** `4000 0025 0000 3155`

### Test Flow

1. Use test card number: `4242 4242 4242 4242`
2. Use any future expiry date: `12/34`
3. Use any 3-digit CVC: `123`
4. Use any ZIP code: `12345`

---

## Security Considerations

1. **Never store credit card data** - Stripe handles all sensitive data
2. **Always use HTTPS** - Required for Stripe in production
3. **Verify amounts on backend** - Never trust frontend amounts
4. **Use payment_intent_id** - Backend verifies payment before completing
5. **Handle errors gracefully** - Don't expose sensitive information

---

## Support

For issues or questions:
- Check browser console for errors
- Check network tab for API responses
- Verify Stripe publishable key is correct
- Ensure authentication token is valid
- Check backend logs for detailed errors

---

## Quick Reference Checklist

Before implementing payment:

- [ ] Install `@stripe/stripe-js`
- [ ] Get publishable key from `/api/stripe/config`
- [ ] Initialize Stripe with publishable key
- [ ] Set up Stripe Elements (CardElement)
- [ ] Create payment intent endpoint
- [ ] Confirm payment with Stripe
- [ ] Complete payment endpoint
- [ ] Handle errors appropriately
- [ ] Test with Stripe test cards
- [ ] Clear card data after payment

---

## Payment Flow Summary

### Standard Flow (All Payment Types)

```
User → Create Payment Intent → Get client_secret → Confirm with Stripe → Complete Payment → Success
```

### Quick Reference: Endpoints by Payment Type

| Payment Type | Create Intent | Complete Payment |
|--------------|---------------|------------------|
| **ACC Subscription** | `POST /api/acc/subscription/payment-intent` | `POST /api/acc/subscription/payment` |
| **Subscription Renewal** | `POST /api/acc/subscription/renew-payment-intent` | `PUT /api/acc/subscription/renew` |
| **Instructor Authorization** | `POST /api/training-center/instructors/authorizations/{id}/payment-intent` | `POST /api/training-center/instructors/authorizations/{id}/pay` |
| **Code Purchase** | `POST /api/training-center/codes/payment-intent` | `POST /api/training-center/codes/purchase` |

### Payment Flow Steps (Universal)

1. **Get Stripe Config** → `GET /api/stripe/config`
2. **Initialize Stripe** → `loadStripe(publishable_key)`
3. **Create Payment Intent** → Appropriate endpoint based on payment type
4. **Confirm Payment** → `stripe.confirmCardPayment(client_secret, { payment_method: { card } })`
5. **Complete Payment** → Appropriate endpoint with `payment_intent_id`
6. **Handle Result** → Show success/error message

---

## Additional Resources

- [Stripe.js Documentation](https://stripe.com/docs/stripe-js)
- [Stripe Elements](https://stripe.com/docs/stripe-js/react)
- [Payment Intents API](https://stripe.com/docs/payments/payment-intents)
- [Test Cards](https://stripe.com/docs/testing)

---

## Quick Start Checklist

Before implementing any payment flow:

- [ ] Install `@stripe/stripe-js` package
- [ ] Get publishable key from `/api/stripe/config`
- [ ] Initialize Stripe with publishable key
- [ ] Set up Stripe Elements (CardElement)
- [ ] Implement payment intent creation
- [ ] Implement Stripe payment confirmation
- [ ] Implement payment completion
- [ ] Add error handling
- [ ] Test with Stripe test cards
- [ ] Add loading states
- [ ] Clear card data after payment

