# Frontend API Updates - Payment Mechanisms

## Overview
This document outlines the recent updates and fixes made to the Payment Mechanisms APIs. All endpoints are now fully functional and ready for frontend integration.

---

## ✅ Fixed Endpoints

### 1. GET `/api/training-center/instructors/authorizations`

**Status:** ✅ Fixed and Working

**Issue:** Endpoint was returning 404 due to route ordering conflict.

**Fix:** 
- Route moved before `apiResource('instructors')` to prevent conflicts
- Response structure updated to match documentation exactly

**Response Structure:**
```json
{
  "authorizations": [
    {
      "id": 1,
      "instructor_id": 5,
      "acc_id": 3,
      "status": "approved",
      "group_admin_status": "commission_set",
      "authorization_price": "500.00",
      "commission_percentage": "15.50",
      "payment_status": "pending",
      "instructor": {
        "id": 5,
        "first_name": "John",
        "last_name": "Doe"
      },
      "acc": {
        "id": 3,
        "name": "Aviation ACC"
      }
    }
  ]
}
```

**Query Parameters:**
- `status` (optional): Filter by status (`pending`, `approved`, `rejected`, `returned`)
- `payment_status` (optional): Filter by payment status (`pending`, `paid`, `failed`)

**Usage Example:**
```javascript
// Get all authorizations
GET /api/training-center/instructors/authorizations

// Get only approved authorizations
GET /api/training-center/instructors/authorizations?status=approved

// Get pending payments
GET /api/training-center/instructors/authorizations?payment_status=pending
```

---

### 2. POST `/api/training-center/codes/purchase`

**Status:** ✅ Enhanced and Fixed

**Changes Made:**
1. Added authorization validation (Training Center must be authorized by ACC)
2. Added course ownership validation (Course must belong to ACC)
3. Enhanced discount code validation
4. Improved error responses with proper HTTP status codes
5. Updated response structure to match requirements

**Request Body:**
```json
{
  "acc_id": 1,
  "course_id": 5,
  "quantity": 10,
  "payment_method": "wallet",
  "discount_code": "DISCOUNT10",
  "payment_intent_id": "pi_1234567890"  // Required for credit_card
}
```

**Response Structure (200 OK):**
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "training_center_id": 2,
    "acc_id": 1,
    "course_id": 5,
    "quantity": 10,
    "total_amount": "500.00",
    "discount_amount": "50.00",
    "final_amount": "450.00",
    "payment_method": "wallet",
    "payment_status": "completed",
    "created_at": "2025-12-19T10:00:00.000000Z"
  },
  "codes": [
    {
      "id": 1,
      "code": "ABC123XYZ",
      "status": "available"
    }
  ]
}
```

**Error Responses:**

**400 Bad Request:**
```json
{
  "message": "Invalid data provided"
}
```

**402 Payment Required:**
```json
{
  "message": "Insufficient wallet balance"
}
```

**403 Forbidden:**
```json
{
  "message": "Training Center does not have authorization from this ACC"
}
```
or
```json
{
  "message": "ACC is not active"
}
```

**404 Not Found:**
```json
{
  "message": "ACC not found"
}
```
or
```json
{
  "message": "Course not found or does not belong to this ACC"
}
```

**422 Unprocessable Entity:**
```json
{
  "message": "Invalid discount code"
}
```
or
```json
{
  "message": "Discount code has expired"
}
```

**Validation Rules:**
- `acc_id`: Required, must exist in database
- `course_id`: Required, must exist and belong to ACC
- `quantity`: Required, integer, minimum 1
- `payment_method`: Required, must be `wallet` or `credit_card`
- `discount_code`: Optional, must be valid and active
- `payment_intent_id`: Required if `payment_method` is `credit_card`

**Usage Example:**
```javascript
// Purchase codes with wallet
const purchaseWithWallet = async () => {
  const response = await fetch('/api/training-center/codes/purchase', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      acc_id: 1,
      course_id: 5,
      quantity: 10,
      payment_method: 'wallet'
    })
  });
  
  if (response.status === 402) {
    // Handle insufficient balance
    const data = await response.json();
    alert(data.message);
  }
  
  return response.json();
};

// Purchase codes with credit card
const purchaseWithCreditCard = async (paymentIntentId) => {
  const response = await fetch('/api/training-center/codes/purchase', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      acc_id: 1,
      course_id: 5,
      quantity: 10,
      payment_method: 'credit_card',
      payment_intent_id: paymentIntentId
    })
  });
  
  return response.json();
};
```

---

## 🔧 Bug Fixes

### 1. Instructor Authorization Payment Commission Calculation

**Issue:** Commission was calculated using ACC's commission percentage instead of authorization's commission percentage.

**Fix:** Updated to use `authorization.commission_percentage` (set by Group Admin) instead of `acc.commission_percentage`.

**Impact:** Commission distribution now correctly uses the percentage set by Group Admin for each authorization.

---

### 2. Wallet Balance Validation

**Issue:** Wallet balance check was happening inside transaction, which could leave transactions open.

**Fix:** Moved wallet balance validation before starting the transaction in both:
- Code purchase endpoint
- Instructor authorization payment endpoint

**Impact:** Better error handling and transaction management.

---

## 📋 Important Notes for Frontend Developers

### 1. Error Handling

Always check HTTP status codes:
- **200**: Success
- **201**: Created successfully
- **400**: Bad Request (validation errors)
- **401**: Unauthorized (not logged in)
- **402**: Payment Required (insufficient balance)
- **403**: Forbidden (no authorization)
- **404**: Not Found
- **422**: Unprocessable Entity (invalid discount code, etc.)
- **500**: Server Error

### 2. Authorization Requirements

Before purchasing codes, ensure:
- Training Center is authorized by the ACC (`status: 'approved'`)
- ACC is active (`status: 'active'`)
- Course belongs to the ACC

You can check authorization status using:
```
GET /api/training-center/authorizations
```

### 3. Payment Methods

**Wallet Payment:**
- No `payment_intent_id` required
- Balance is checked before transaction
- Returns 402 if insufficient balance

**Credit Card Payment:**
- `payment_intent_id` is required
- Must be obtained from Stripe first
- Should verify payment intent before calling endpoint

### 4. Discount Codes

When applying discount codes:
- Code must belong to the ACC
- Code must be active
- Code must be within valid date range
- Code must apply to the course (if course-specific)
- Code must have available quantity (if quantity-based)

### 5. Response Fields

**Batch Object:**
- `total_amount`: Original amount before discount
- `discount_amount`: Discount applied
- `final_amount`: Amount after discount (this is what was charged)
- `payment_status`: Always "completed" on success

**Codes Array:**
- Each code has `id`, `code`, and `status`
- Status is initially "available"
- Codes can be used immediately after purchase

---

## 🧪 Testing Checklist

Before deploying to production, test:

- [ ] Get instructor authorizations list
- [ ] Filter authorizations by status
- [ ] Filter authorizations by payment_status
- [ ] Purchase codes with wallet (sufficient balance)
- [ ] Purchase codes with wallet (insufficient balance)
- [ ] Purchase codes with credit card
- [ ] Purchase codes with valid discount code
- [ ] Purchase codes with invalid discount code
- [ ] Purchase codes without authorization (should fail)
- [ ] Purchase codes for course not belonging to ACC (should fail)
- [ ] Verify commission distribution in response

---

## 📞 Support

If you encounter any issues or have questions:
1. Check error messages in response
2. Verify authentication token is valid
3. Check user role (must be `training_center_admin`)
4. Verify Training Center has authorization from ACC
5. Check API documentation for detailed endpoint specifications

---

**Last Updated:** December 19, 2025


