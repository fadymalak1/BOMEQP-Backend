# Stripe Payment Gateway Integration

## Overview
This document describes the Stripe payment gateway integration that has been added to the BOMEQP project.

## Features

### 1. Stripe Settings Management
- Admin can configure Stripe API keys (publishable key, secret key, webhook secret)
- Support for both sandbox/test and live/production environments
- Settings are stored securely in the database
- Only one environment can be active at a time

### 2. Payment Processing
- Create payment intents for various transaction types
- Support for subscriptions, code purchases, material purchases, etc.
- Automatic transaction record creation
- Webhook support for payment status updates

### 3. API Endpoints

#### Public Endpoints
- `POST /api/stripe/webhook` - Stripe webhook handler (signature verified)

#### Protected Endpoints (All Authenticated Users)
- `GET /api/stripe/config` - Get Stripe publishable key
- `POST /api/stripe/payment-intent` - Create a payment intent
- `POST /api/stripe/confirm` - Confirm a payment
- `POST /api/stripe/refund` - Refund a payment

#### Admin Endpoints (Group Admin Only)
- `GET /api/admin/stripe-settings` - List all Stripe settings
- `GET /api/admin/stripe-settings/active` - Get active Stripe settings
- `POST /api/admin/stripe-settings` - Create new Stripe settings
- `PUT /api/admin/stripe-settings/{id}` - Update Stripe settings
- `DELETE /api/admin/stripe-settings/{id}` - Delete Stripe settings

## Installation

1. Install Stripe PHP SDK (already added to composer.json):
```bash
composer install
```

2. Run migrations:
```bash
php artisan migrate
```

3. Seed Stripe settings (optional):
```bash
php artisan db:seed --class=StripeSettingSeeder
```

## Configuration

### Admin Configuration Steps

1. Log in as a group admin
2. Navigate to Stripe settings endpoint: `GET /api/admin/stripe-settings`
3. Create or update Stripe settings:
   - For sandbox/testing: Use test keys from Stripe Dashboard
   - For production: Use live keys from Stripe Dashboard
4. Set `is_active` to `true` for the environment you want to use

### Stripe Dashboard Setup

1. Create a Stripe account at https://stripe.com
2. Get your API keys from the Dashboard:
   - Test mode: Use test keys (pk_test_... and sk_test_...)
   - Live mode: Use live keys (pk_live_... and sk_live_...)
3. Set up webhooks:
   - Webhook URL: `https://yourdomain.com/api/stripe/webhook`
   - Events to listen for:
     - `payment_intent.succeeded`
     - `payment_intent.payment_failed`
     - `charge.refunded`

## Usage Examples

### Creating a Payment Intent

```javascript
// Frontend (JavaScript)
const response = await fetch('/api/stripe/payment-intent', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: JSON.stringify({
    amount: 100.00,
    currency: 'USD',
    transaction_type: 'code_purchase',
    payer_type: 'training_center',
    payer_id: 1,
    payee_type: 'acc',
    payee_id: 1,
    description: 'Certificate code purchase'
  })
});

const { client_secret, payment_intent_id } = await response.json();

// Use Stripe.js to confirm payment
const stripe = Stripe('YOUR_PUBLISHABLE_KEY');
const { error } = await stripe.confirmCardPayment(client_secret, {
  payment_method: {
    card: cardElement,
  }
});
```

### Admin Updating Stripe Settings

```javascript
// Update Stripe settings
const response = await fetch('/api/admin/stripe-settings/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ADMIN_TOKEN'
  },
  body: JSON.stringify({
    publishable_key: 'pk_test_your_key',
    secret_key: 'sk_test_your_key',
    webhook_secret: 'whsec_your_secret',
    is_active: true,
    description: 'Production Stripe settings'
  })
});
```

## Database Schema

### stripe_settings table
- `id` - Primary key
- `environment` - 'sandbox' or 'live'
- `publishable_key` - Stripe publishable key
- `secret_key` - Stripe secret key (encrypted/hidden in responses)
- `webhook_secret` - Webhook signing secret
- `is_active` - Boolean, only one can be active per environment
- `description` - Description of the settings
- `created_at`, `updated_at` - Timestamps

## Security Notes

1. Secret keys are never exposed in API responses (only last 4 characters shown)
2. Webhook signatures are verified before processing
3. All payment operations require authentication
4. Admin endpoints require group_admin role

## Transaction Integration

All Stripe payments automatically create Transaction records with:
- `payment_method` = 'credit_card'
- `payment_gateway_transaction_id` = Stripe payment intent ID
- `status` = 'pending' (updated to 'completed' or 'failed' via webhook)

## Testing

Use Stripe test cards:
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- 3D Secure: `4000 0025 0000 3155`

## Support

For Stripe API documentation, visit: https://stripe.com/docs/api

