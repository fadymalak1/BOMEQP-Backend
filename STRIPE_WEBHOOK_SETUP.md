# Stripe Webhook Setup Guide

## Overview
This guide explains how to set up and handle Stripe webhooks in the BOMEQP system. Webhooks allow Stripe to notify your application about payment events in real-time.

---

## Configuration

### Option 1: Using .env File (Quick Setup)

Add to your `.env` file:
```env
STRIPE_KEY=sk_test_51Shy6rC7FGiektWulVzleEMln1wKRnnMhGGQAMCNw37e8ahfwrrNSCGs346Jn1VpwosGHpjnF9TZsqLSRpg9ZnMJ00XCkr4o3a
STRIPE_PUBLISHABLE_KEY=pk_test_51Shy6rC7FGiektWuqBF4ghuxewx0U5vDobkFO0gxz4UhTZJ9cZ2LsZuwYxeMoKlZNRicIHRBmaw4yYEZPHniwx4J00nG2h5nfQ
STRIPE_WEBHOOK_SECRET=whsec_u8Q40GmEzXwMGwQwvdkXxtI2Y2j8Vbev
STRIPE_CURRENCY=USD
```

The system will automatically use these values if no StripeSetting record exists in the database.

### Option 2: Using Database (Recommended for Production)

1. Run migration:
```bash
php artisan migrate
```

2. Create StripeSetting record via API or directly in database:
```php
\App\Models\StripeSetting::create([
    'environment' => 'sandbox', // or 'live'
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'secret_key' => env('STRIPE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'is_active' => true,
    'description' => 'Stripe test environment settings',
]);
```

Or use the Admin API endpoint:
```
POST /api/admin/stripe-settings
```

**Priority:** Database settings take precedence over .env values.

---

## Webhook Endpoint

**URL:** `POST /api/stripe/webhook`

This endpoint is public (no authentication required) but protected by Stripe signature verification.

---

## Setting Up Webhook in Stripe Dashboard

### 1. Access Stripe Dashboard

1. Log in to your Stripe Dashboard: https://dashboard.stripe.com
2. Navigate to **Developers** → **Webhooks**

### 2. Add Webhook Endpoint

1. Click **"Add endpoint"** or **"Add webhook endpoint"**
2. Enter your webhook URL:
   ```
   https://yourdomain.com/api/stripe/webhook
   ```
   
   **For local development/testing**, use Stripe CLI:
   ```bash
   stripe listen --forward-to http://localhost:8000/api/stripe/webhook
   ```

### 3. Select Events to Listen To

Select the following events:

**Payment Events:**
- `payment_intent.succeeded` - Payment completed successfully
- `payment_intent.payment_failed` - Payment failed
- `payment_intent.canceled` - Payment was canceled

**Refund Events:**
- `charge.refunded` - Payment was refunded

**Dispute Events (Optional):**
- `charge.dispute.created` - Dispute created

### 4. Copy Webhook Secret

After creating the webhook endpoint:
1. Click on the webhook endpoint
2. Under **"Signing secret"**, click **"Reveal"**
3. Copy the webhook secret (starts with `whsec_`)
4. Add it to your `.env` file:
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
   ```

---

## Webhook Event Handling

### Handled Events

#### 1. payment_intent.succeeded

**Triggered when:** Payment is successfully completed

**Action:**
- Updates transaction status to `completed`
- Sets `completed_at` timestamp

**Code:**
```php
protected function handlePaymentSucceeded(array $paymentIntent)
{
    $transaction = Transaction::where('payment_gateway_transaction_id', $paymentIntent['id'])->first();
    
    if ($transaction) {
        $transaction->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
```

#### 2. payment_intent.payment_failed

**Triggered when:** Payment attempt fails

**Action:**
- Updates transaction status to `failed`

#### 3. payment_intent.canceled

**Triggered when:** Payment intent is canceled

**Action:**
- Updates transaction status to `failed`

#### 4. charge.refunded

**Triggered when:** A charge is refunded

**Action:**
- Updates transaction status to `refunded`

#### 5. charge.dispute.created (Optional)

**Triggered when:** A dispute is created

**Action:**
- Logs dispute information
- You can extend this to create dispute records or send notifications

---

## Webhook Security

### Signature Verification

All webhook requests are verified using Stripe's signature verification:

1. Stripe signs each webhook payload with your webhook secret
2. The signature is included in the `Stripe-Signature` header
3. The system verifies the signature before processing the webhook

**Implementation:**
```php
public function verifyWebhookSignature(string $payload, string $signature): bool
{
    $webhookSecret = $this->settings->webhook_secret ?? env('STRIPE_WEBHOOK_SECRET');
    
    try {
        \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
        return true;
    } catch (\Exception $e) {
        Log::error('Webhook signature verification failed', ['error' => $e->getMessage()]);
        return false;
    }
}
```

**If signature verification fails:**
- Returns `400 Bad Request`
- Logs warning
- Webhook is not processed

---

## Testing Webhooks

### Using Stripe CLI (Recommended for Development)

1. **Install Stripe CLI:**
   ```bash
   # Windows (using Scoop)
   scoop install stripe
   
   # macOS (using Homebrew)
   brew install stripe/stripe-cli/stripe
   
   # Linux
   # Download from https://stripe.com/docs/stripe-cli
   ```

2. **Login to Stripe:**
   ```bash
   stripe login
   ```

3. **Forward webhooks to local server:**
   ```bash
   stripe listen --forward-to http://localhost:8000/api/stripe/webhook
   ```
   
   This will output a webhook signing secret like `whsec_xxx`. Use this for local testing.

4. **Trigger test events:**
   ```bash
   # Test successful payment
   stripe trigger payment_intent.succeeded
   
   # Test failed payment
   stripe trigger payment_intent.payment_failed
   
   # Test refund
   stripe trigger charge.refunded
   ```

### Using Stripe Dashboard

1. Go to **Developers** → **Webhooks**
2. Click on your webhook endpoint
3. Click **"Send test webhook"**
4. Select event type and click **"Send test webhook"**

### Manual Testing with cURL

```bash
# Note: You need to generate a proper signature for this to work
# This is just for reference - use Stripe CLI for actual testing

curl -X POST http://localhost:8000/api/stripe/webhook \
  -H "Stripe-Signature: t=timestamp,v1=signature" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment_intent.succeeded",
    "data": {
      "object": {
        "id": "pi_test_xxx",
        "status": "succeeded"
      }
    }
  }'
```

---

## Webhook Payload Structure

### Example: payment_intent.succeeded

```json
{
  "id": "evt_xxx",
  "object": "event",
  "api_version": "2020-08-27",
  "created": 1234567890,
  "data": {
    "object": {
      "id": "pi_xxx",
      "object": "payment_intent",
      "amount": 10000,
      "currency": "usd",
      "status": "succeeded",
      "metadata": {
        "acc_id": "1",
        "type": "subscription"
      }
    }
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": "req_xxx",
    "idempotency_key": null
  },
  "type": "payment_intent.succeeded"
}
```

---

## Logging

All webhook events are logged:

- **Info level:** Successful webhook receipt and processing
- **Warning level:** Signature verification failures, unhandled events
- **Error level:** Processing errors, exceptions

**Log locations:**
- Laravel logs: `storage/logs/laravel.log`
- Check logs for webhook activity:
  ```bash
  tail -f storage/logs/laravel.log | grep -i webhook
  ```

---

## Troubleshooting

### Issue: Webhook signature verification fails

**Possible causes:**
1. Webhook secret is incorrect
2. Webhook secret not set in .env or database
3. Payload was modified before verification

**Solution:**
1. Verify webhook secret in Stripe Dashboard
2. Check .env file has correct `STRIPE_WEBHOOK_SECRET`
3. Ensure raw request body is used (not parsed JSON)

### Issue: Webhook received but transaction not updated

**Possible causes:**
1. Transaction not found (payment_intent_id doesn't match)
2. Transaction already updated
3. Error during processing

**Solution:**
1. Check logs for errors
2. Verify payment_intent_id matches transaction record
3. Check if transaction exists in database

### Issue: Webhook endpoint returns 404

**Possible causes:**
1. Route not registered
2. URL path incorrect
3. Server configuration issue

**Solution:**
1. Verify route exists: `POST /api/stripe/webhook`
2. Check routes are cached: `php artisan route:clear`
3. Verify webhook URL in Stripe Dashboard matches your server

### Issue: Webhook not received

**Possible causes:**
1. Server not accessible from internet (for production)
2. Firewall blocking requests
3. SSL certificate issues

**Solution:**
1. Use Stripe CLI for local development
2. Check server logs for incoming requests
3. Verify SSL certificate is valid (for production)

---

## Production Checklist

Before going live:

- [ ] Webhook endpoint is publicly accessible
- [ ] SSL certificate is valid and properly configured
- [ ] Webhook secret is set in .env or database
- [ ] All required events are selected in Stripe Dashboard
- [ ] Webhook endpoint is tested with Stripe test mode
- [ ] Error logging is configured and monitored
- [ ] Transaction update logic is tested
- [ ] Webhook retry handling is understood (Stripe retries failed webhooks)

---

## Webhook Retry Logic

Stripe automatically retries failed webhooks:

- **Retry schedule:** 5 minutes, 1 hour, 6 hours, 12 hours, 24 hours
- **Maximum retries:** Up to 3 days
- **Success response:** Must return 200 status code

**Best practices:**
- Always return 200 immediately after receiving webhook
- Process webhook asynchronously if needed
- Implement idempotency to handle duplicate webhooks

---

## Example: Complete Webhook Flow

```
1. User completes payment on frontend
   ↓
2. Stripe processes payment
   ↓
3. Stripe sends webhook to: POST /api/stripe/webhook
   ↓
4. System verifies webhook signature
   ↓
5. System processes event (e.g., payment_intent.succeeded)
   ↓
6. System updates transaction status to 'completed'
   ↓
7. System returns 200 OK to Stripe
   ↓
8. Stripe marks webhook as delivered
```

---

## Additional Resources

- [Stripe Webhooks Documentation](https://stripe.com/docs/webhooks)
- [Stripe CLI Documentation](https://stripe.com/docs/stripe-cli)
- [Webhook Security Best Practices](https://stripe.com/docs/webhooks/best-practices)
- [Testing Webhooks](https://stripe.com/docs/webhooks/test)

---

## Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Stripe Dashboard → Developers → Webhooks for delivery status
3. Use Stripe CLI to test webhooks locally
4. Verify webhook secret matches between Stripe Dashboard and .env

