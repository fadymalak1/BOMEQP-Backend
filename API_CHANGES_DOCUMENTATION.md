# API Changes Documentation

## Overview

This document describes the recent API changes and improvements made to the BOMEQP system, including new endpoints, error handling improvements, and bug fixes.

---

## Table of Contents

1. [New Endpoints](#new-endpoints)
2. [Purchase Endpoint Improvements](#purchase-endpoint-improvements)
3. [Error Handling Enhancements](#error-handling-enhancements)
4. [Breaking Changes](#breaking-changes)
5. [Migration Guide](#migration-guide)

---

## New Endpoints

### 1. Get Discount Codes by ACC ID

**Endpoint:** `GET /v1/api/acc/{id}/discount-codes`

**Description:** Retrieve all active discount codes for a specific ACC. This endpoint is useful for training centers and admins who need to view available discount codes for an ACC when purchasing certificate codes.

**Authentication:** Required (Sanctum)

**Authorization:** 
- `acc_admin` role - Can access via `/acc/{id}/discount-codes`
- `training_center_admin` role - Can access via `/training-center/accs/{id}/discount-codes`

**URL Parameters:**
- `id` (integer, required) - The ACC ID

**Response (200):**
```json
{
  "discount_codes": [
    {
      "id": 1,
      "acc_id": 6,
      "code": "SAVE20",
      "discount_type": "time_limited",
      "discount_percentage": 20.0,
      "applicable_course_ids": [1, 2, 3],
      "start_date": "2024-01-01",
      "end_date": "2024-12-31",
      "total_quantity": null,
      "used_quantity": 0,
      "status": "active",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

**Response (404):**
```json
{
  "message": "ACC not found"
}
```

**Notes:**
- Only returns discount codes with `status: "active"`
- Returns empty array if no active discount codes exist for the ACC
- Available at two endpoints:
  - `/acc/{id}/discount-codes` - For ACC admins
  - `/training-center/accs/{id}/discount-codes` - For training centers

---

## Purchase Endpoint Improvements

### Enhanced Error Handling

The `/training-center/codes/purchase` endpoint has been significantly improved with better error handling and validation.

#### 1. File Upload Validation

**Improvement:** Added comprehensive validation for manual payment receipt uploads.

**Changes:**
- Validates file existence and validity before processing
- Checks if request is sent as `multipart/form-data`
- Provides clear error messages for invalid file uploads
- Handles storage directory creation with proper permissions

**Error Response (422):**
```json
{
  "message": "Payment receipt file is invalid or missing. Please ensure the request is sent as multipart/form-data with the receipt file.",
  "error": "Invalid file upload"
}
```

#### 2. Null Value Handling

**Improvement:** Added null coalescing operators to prevent null value errors in database operations.

**Changes:**
- Commission amounts default to `0` if null
- Payment gateway transaction ID handles null for manual payments
- All commission ledger fields have default values

#### 3. Code Generation Improvements

**Improvement:** Enhanced certificate code generation with uniqueness validation and retry logic.

**Changes:**
- Checks for code uniqueness before creation
- Implements retry logic (up to 10 attempts) to generate unique codes
- Prevents duplicate code errors
- Provides clear error messages if code generation fails

**Error Response (500):**
```json
{
  "message": "Failed to generate unique certificate code after 10 attempts: [error details]",
  "error": "Code generation failed"
}
```

#### 4. Notification Error Handling

**Improvement:** Wrapped notification calls in try-catch blocks to prevent notification failures from affecting purchases.

**Changes:**
- Notification failures no longer cause purchase to fail
- Errors are logged for debugging
- Purchase completes successfully even if notifications fail

**Log Entry Example:**
```
[ERROR] Notification sending failed after code purchase
{
  "error": "Notification service error message",
  "batch_id": 123,
  "trace": "..."
}
```

#### 5. Payment Intent Handling

**Improvement:** Added null checks and error handling for Stripe payment intent retrieval.

**Changes:**
- Validates `payment_intent_id` exists before retrieving from Stripe
- Falls back to calculated amounts if retrieval fails
- Logs warnings for debugging

---

## Error Handling Enhancements

### Improved Error Messages

All endpoints now provide more descriptive error messages:

**Before:**
```json
{
  "message": "Purchase failed"
}
```

**After:**
```json
{
  "message": "Purchase failed. Please try again.",
  "error": "Internal server error"
}
```

### Enhanced Error Logging

All errors are now logged with comprehensive context:

```php
\Log::error('Code purchase failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'user_id' => $user->id ?? null,
    'training_center_id' => $trainingCenter->id ?? null,
    'request_data' => [
        'acc_id' => $request->acc_id ?? null,
        'course_id' => $request->course_id ?? null,
        'quantity' => $request->quantity ?? null,
        'payment_method' => $request->payment_method ?? null,
    ]
]);
```

### Debug Mode Support

Error responses include detailed error information when `APP_DEBUG=true`:

**Production (APP_DEBUG=false):**
```json
{
  "message": "Purchase failed. Please try again.",
  "error": "Internal server error"
}
```

**Development (APP_DEBUG=true):**
```json
{
  "message": "Purchase failed. Please try again. Error: [specific error message]",
  "error": "[specific error message]"
}
```

---

## API Endpoint Reference

### Purchase Certificate Codes

**Endpoint:** `POST /v1/api/training-center/codes/purchase`

**Request Body (Credit Card):**
```json
{
  "acc_id": 6,
  "course_id": 1,
  "quantity": 10,
  "discount_code": "SAVE20",
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx"
}
```

**Request Body (Manual Payment - multipart/form-data):**
```
acc_id: 6
course_id: 1
quantity: 10
discount_code: SAVE20
payment_method: manual_payment
payment_amount: 900.00
payment_receipt: [file]
```

**Response (200 - Credit Card):**
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "training_center_id": 1,
    "acc_id": 6,
    "course_id": 1,
    "quantity": 10,
    "total_amount": "1000.00",
    "discount_amount": "100.00",
    "final_amount": "900.00",
    "payment_method": "credit_card",
    "payment_status": "completed",
    "created_at": "2024-01-20T10:30:00.000000Z"
  },
  "codes": [
    {
      "id": 1,
      "code": "ABC123XYZ456",
      "status": "available"
    }
  ]
}
```

**Response (200 - Manual Payment):**
```json
{
  "message": "Payment request submitted successfully. Waiting for approval.",
  "batch": {
    "id": 1,
    "training_center_id": 1,
    "acc_id": 6,
    "course_id": 1,
    "quantity": 10,
    "total_amount": "1000.00",
    "discount_amount": "100.00",
    "final_amount": "900.00",
    "payment_method": "manual_payment",
    "payment_status": "pending",
    "created_at": "2024-01-20T10:30:00.000000Z"
  }
}
```

**Error Responses:**

**422 - Validation Error:**
```json
{
  "message": "Payment receipt is required for manual payment. Please ensure you are sending the file as multipart/form-data.",
  "error": "Validation error"
}
```

**422 - Amount Mismatch:**
```json
{
  "message": "Payment amount does not match the calculated total amount",
  "expected_amount": 900.00,
  "provided_amount": 850.00
}
```

**400 - Payment Not Confirmed:**
```json
{
  "message": "Payment not confirmed. Please complete the payment on the frontend before submitting the purchase.",
  "error": "Payment not completed. Status: requires_payment_method",
  "error_code": "payment_not_confirmed",
  "instructions": "The payment intent has been created but not yet confirmed. Please use Stripe.js to confirm the payment before calling this endpoint."
}
```

**400 - Payment Processing:**
```json
{
  "message": "Payment is still processing. Please wait a moment and try again.",
  "error": "Payment not completed. Status: processing",
  "error_code": "payment_processing"
}
```

**400 - Payment Canceled:**
```json
{
  "message": "Payment was canceled or requires additional action. Please try again with a new payment.",
  "error": "Payment not completed. Status: canceled",
  "error_code": "payment_canceled"
}
```

**500 - Server Error:**
```json
{
  "message": "Purchase failed. Please try again.",
  "error": "Internal server error"
}
```

### Payment Flow

**Important:** The payment intent must be confirmed on the frontend before calling the purchase endpoint.

1. **Create Payment Intent:**
   ```javascript
   const response = await axios.post('/training-center/codes/create-payment-intent', {
     acc_id: 6,
     course_id: 1,
     quantity: 10
   });
   const { client_secret, payment_intent_id } = response.data;
   ```

2. **Confirm Payment with Stripe:**
   ```javascript
   const stripe = Stripe('pk_test_...');
   const { error, paymentIntent } = await stripe.confirmCardPayment(client_secret, {
     payment_method: {
       card: cardElement,
       billing_details: { /* ... */ }
     }
   });
   
   if (error) {
     // Handle error
   } else if (paymentIntent.status === 'succeeded') {
     // Payment confirmed, proceed to purchase
   }
   ```

3. **Call Purchase Endpoint:**
   ```javascript
   // Only call this AFTER payment status is 'succeeded'
   const purchaseResponse = await axios.post('/training-center/codes/purchase', {
     acc_id: 6,
     course_id: 1,
     quantity: 10,
     payment_method: 'credit_card',
     payment_intent_id: payment_intent_id
   });
   ```

---

## Breaking Changes

### None

No breaking changes were introduced. All existing endpoints continue to work as before, with improved error handling and new optional endpoints added.

---

## Migration Guide

### For Frontend Developers

#### 1. Using the New Discount Codes Endpoint

**Before:**
```javascript
// No direct way to get discount codes for a specific ACC
```

**After:**
```javascript
// For ACC admins
const response = await axios.get(`/v1/api/acc/${accId}/discount-codes`);

// For training centers
const response = await axios.get(`/v1/api/training-center/accs/${accId}/discount-codes`);
```

#### 2. Handling Purchase Errors

**Before:**
```javascript
try {
  const response = await axios.post('/training-center/codes/purchase', data);
} catch (error) {
  // Generic error handling
  console.error('Purchase failed');
}
```

**After:**
```javascript
try {
  const response = await axios.post('/training-center/codes/purchase', data);
} catch (error) {
  if (error.response) {
    const { message, error: errorType, expected_amount, provided_amount } = error.response.data;
    
    // Handle specific error types
    if (errorType === 'Invalid file upload') {
      // Show message about multipart/form-data requirement
    } else if (errorType === 'Validation error') {
      // Show validation message
    } else if (expected_amount && provided_amount) {
      // Show amount mismatch message
    }
  }
}
```

#### 3. Manual Payment File Upload

**Important:** When using manual payment, ensure the request is sent as `multipart/form-data`:

```javascript
const formData = new FormData();
formData.append('acc_id', accId);
formData.append('course_id', courseId);
formData.append('quantity', quantity);
formData.append('payment_method', 'manual_payment');
formData.append('payment_amount', finalAmount);
formData.append('payment_receipt', receiptFile);

const response = await axios.post('/training-center/codes/purchase', formData, {
  headers: {
    'Content-Type': 'multipart/form-data'
  }
});
```

---

## Testing Recommendations

### 1. Test Discount Codes Endpoint

```bash
# Test as ACC admin
curl -X GET "https://aeroenix.com/v1/api/acc/6/discount-codes" \
  -H "Authorization: Bearer {token}"

# Test as training center
curl -X GET "https://aeroenix.com/v1/api/training-center/accs/6/discount-codes" \
  -H "Authorization: Bearer {token}"
```

### 2. Test Purchase with Credit Card

```bash
curl -X POST "https://aeroenix.com/v1/api/training-center/codes/purchase" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "acc_id": 6,
    "course_id": 1,
    "quantity": 10,
    "payment_method": "credit_card",
    "payment_intent_id": "pi_xxx"
  }'
```

### 3. Test Purchase with Manual Payment

```bash
curl -X POST "https://aeroenix.com/v1/api/training-center/codes/purchase" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "acc_id=6" \
  -F "course_id=1" \
  -F "quantity=10" \
  -F "payment_method=manual_payment" \
  -F "payment_amount=900.00" \
  -F "payment_receipt=@/path/to/receipt.pdf"
```

---

## Troubleshooting

### Issue: 404 Error on Discount Codes Endpoint

**Solution:** Clear Laravel route cache:
```bash
php artisan route:clear
php artisan route:cache
```

### Issue: 500 Error on Purchase Endpoint

**Check:**
1. Laravel logs: `storage/logs/laravel.log`
2. Ensure all required fields are provided
3. For manual payment, ensure request is `multipart/form-data`
4. Verify file upload permissions in `storage/app/public`

### Issue: Code Generation Fails

**Possible Causes:**
- Database connection issues
- Unique constraint violations (should be handled automatically)
- Insufficient permissions

**Solution:** Check logs for specific error message and stack trace.

---

## Changelog

### Version 1.1.0 (Current)

**Added:**
- New endpoint: `GET /acc/{id}/discount-codes`
- New endpoint: `GET /training-center/accs/{id}/discount-codes`
- Enhanced error handling in purchase endpoint
- Improved code generation with uniqueness validation
- Notification error handling to prevent purchase failures

**Improved:**
- File upload validation for manual payments
- Null value handling in database operations
- Error messages and logging
- Payment intent retrieval error handling

**Fixed:**
- 500 errors caused by null values
- 500 errors caused by notification failures
- Potential duplicate code generation issues
- 404 error on `/training-center/codes/create-payment-intent` route (added missing route)
- Improved error messages for payment verification failures with specific error codes and instructions

---

## Route Fixes

### Payment Intent Endpoint

**Issue:** The route `/training-center/codes/create-payment-intent` was returning 404.

**Solution:** Added the route to match the OpenAPI documentation and frontend expectations.

**Available Routes:**
- `POST /v1/api/training-center/codes/create-payment-intent` (Primary - matches OpenAPI docs)
- `POST /v1/api/training-center/codes/payment-intent` (Alias for backward compatibility)

Both routes point to the same controller method: `CodeController@createPaymentIntent`.

**Note:** After deploying this fix, clear the route cache:
```bash
php artisan route:clear
php artisan route:cache
```

