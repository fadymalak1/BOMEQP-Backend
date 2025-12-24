# Stripe Environment Setup - Quick Guide

## ✅ Configuration Complete

You've added Stripe keys to your `.env` file. The system is now configured to use these values.

---

## Environment Variables Added

```env
STRIPE_KEY=sk_test_51Shy6rC7FGiektWulVzleEMln1wKRnnMhGGQAMCNw37e8ahfwrrNSCGs346Jn1VpwosGHpjnF9TZsqLSRpg9ZnMJ00XCkr4o3a
STRIPE_PUBLISHABLE_KEY=pk_test_51Shy6rC7FGiektWuqBF4ghuxewx0U5vDobkFO0gxz4UhTZJ9cZ2LsZuwYxeMoKlZNRicIHRBmaw4yYEZPHniwx4J00nG2h5nfQ
STRIPE_WEBHOOK_SECRET=whsec_u8Q40GmEzXwMGwQwvdkXxtI2Y2j8Vbev
STRIPE_CURRENCY=USD
```

---

## How It Works

The system will:
1. **First** try to use `StripeSetting` from database (if exists and active)
2. **Fallback** to `.env` values if no database settings found

This means your `.env` values will be used automatically!

---

## Webhook Endpoint

**URL:** `POST /api/stripe/webhook`

**Status:** ✅ Configured and ready

**Security:** Protected by Stripe signature verification

---

## Next Steps

### 1. Configure Webhook in Stripe Dashboard

1. Go to: https://dashboard.stripe.com/test/webhooks
2. Click **"Add endpoint"**
3. Enter URL: `https://yourdomain.com/api/stripe/webhook`
4. Select events:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `payment_intent.canceled`
   - `charge.refunded`
5. Copy the **Signing secret** (starts with `whsec_`)
6. Update `.env` with the new webhook secret if different

### 2. Test the Configuration

**Check if Stripe is configured:**
```bash
GET /api/stripe/config
```

Expected response:
```json
{
  "success": true,
  "publishable_key": "pk_test_...",
  "is_configured": true
}
```

### 3. Test Webhook Locally (Optional)

If testing locally, use Stripe CLI:
```bash
# Install Stripe CLI
# Windows: scoop install stripe
# macOS: brew install stripe/stripe-cli/stripe

# Forward webhooks to local server
stripe listen --forward-to http://localhost:8000/api/stripe/webhook

# Trigger test event
stripe trigger payment_intent.succeeded
```

---

## Webhook Events Handled

| Event | Action |
|-------|--------|
| `payment_intent.succeeded` | Updates transaction status to `completed` |
| `payment_intent.payment_failed` | Updates transaction status to `failed` |
| `payment_intent.canceled` | Updates transaction status to `failed` |
| `charge.refunded` | Updates transaction status to `refunded` |
| `charge.dispute.created` | Logs dispute information |

---

## Verification

The webhook handler:
- ✅ Verifies Stripe signature
- ✅ Updates transaction status
- ✅ Logs all events
- ✅ Handles errors gracefully
- ✅ Returns proper HTTP status codes

---

## Important Notes

1. **Webhook Secret:** Make sure `STRIPE_WEBHOOK_SECRET` matches the one from Stripe Dashboard
2. **SSL Required:** For production, webhook endpoint must use HTTPS
3. **Idempotency:** Webhook handler is idempotent - duplicate events won't cause issues
4. **Logs:** Check `storage/logs/laravel.log` for webhook activity

---

## Troubleshooting

**If webhook signature verification fails:**
- Check webhook secret in `.env` matches Stripe Dashboard
- Ensure raw request body is used (Laravel handles this automatically)

**If webhook not received:**
- Verify webhook URL is publicly accessible (for production)
- Check server logs for incoming requests
- Use Stripe CLI for local testing

**If transaction not updated:**
- Check logs for errors
- Verify payment_intent_id matches transaction record
- Ensure transaction exists in database

---

## Status: ✅ Ready to Use

Your Stripe integration is now fully configured and ready to handle payments and webhooks!

