# Payment Mechanisms API Documentation

This document describes the payment mechanisms implemented in the BOMEQP system.

---

## Table of Contents

1. [ACC Subscription Payment](#acc-subscription-payment)
2. [Instructor Authorization Payment](#instructor-authorization-payment)
3. [Certificate Code Purchase Payment](#certificate-code-purchase-payment)
4. [Scheduled Tasks](#scheduled-tasks)

---

## ACC Subscription Payment

### Overview
ACC accounts must maintain an active subscription to remain operational. When a subscription expires, the ACC account is automatically suspended and cannot be reactivated until the subscription is renewed.

### Flow

1. **ACC pays subscription fee** → Creates subscription record
2. **Subscription expires** → Automatic suspension (via scheduled command)
3. **ACC renews subscription** → Account reactivated

---

### 1. Pay Subscription

**Endpoint:** `POST /api/acc/subscription/payment`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC pays subscription fee to Admin.

**Request Body:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_1234567890"
}
```

**Response:** `200 OK`
```json
{
  "message": "Payment successful",
  "subscription": {
    "id": 1,
    "acc_id": 5,
    "subscription_start_date": "2025-12-19",
    "subscription_end_date": "2026-12-19",
    "renewal_date": "2026-12-19",
    "amount": "10000.00",
    "payment_status": "paid",
    "payment_date": "2025-12-19T10:00:00.000000Z",
    "payment_method": "credit_card",
    "transaction_id": 123,
    "auto_renew": false
  }
}
```

**Validation Rules:**
- `amount`: required, numeric, min:0
- `payment_method`: required, enum: `credit_card`, `wallet`
- `payment_intent_id`: nullable, string (for Stripe)

**Notes:**
- If ACC is suspended due to expired subscription, it will be automatically reactivated upon payment
- Default subscription duration is 1 year
- Creates a transaction record for payment tracking

---

### 2. Renew Subscription

**Endpoint:** `PUT /api/acc/subscription/renew`  
**Authentication:** Required (ACC Admin)  
**Description:** Renew an expired or expiring subscription.

**Request Body:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_1234567890",
  "auto_renew": false
}
```

**Response:** `200 OK`
```json
{
  "message": "Subscription renewed successfully",
  "subscription": {
    "id": 2,
    "subscription_start_date": "2026-12-19",
    "subscription_end_date": "2027-12-19",
    "amount": "10000.00",
    "payment_status": "paid"
  }
}
```

**Error Response:** `400 Bad Request` (if subscription expired and account suspended)
```json
{
  "message": "Subscription expired. Account is suspended. Please renew to reactivate.",
  "requires_payment": true
}
```

**Validation Rules:**
- `amount`: required, numeric, min:0
- `payment_method`: required, enum: `credit_card`, `wallet`
- `payment_intent_id`: nullable, string
- `auto_renew`: nullable, boolean

**Notes:**
- If current subscription hasn't expired, new subscription starts from the end date of current subscription
- If subscription expired, new subscription starts from now
- Automatically reactivates ACC account if suspended

---

### 3. Get Subscription

**Endpoint:** `GET /api/acc/subscription`  
**Authentication:** Required (ACC Admin)  
**Description:** Get current subscription details.

**Response:** `200 OK`
```json
{
  "subscription": {
    "id": 1,
    "acc_id": 5,
    "subscription_start_date": "2025-12-19",
    "subscription_end_date": "2026-12-19",
    "renewal_date": "2026-12-19",
    "amount": "10000.00",
    "payment_status": "paid",
    "payment_date": "2025-12-19T10:00:00.000000Z",
    "payment_method": "credit_card",
    "transaction_id": 123,
    "auto_renew": false,
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z"
  }
}
```

---

### 4. Automatic Suspension

**Scheduled Command:** `php artisan subscriptions:check-expired`  
**Schedule:** Daily (automatically scheduled)

**Description:**  
This command checks for ACCs with expired subscriptions and automatically suspends them. The command:
- Finds subscriptions where `subscription_end_date < now()` and `payment_status = 'paid'`
- Checks if ACC has an active subscription
- If no active subscription exists, suspends ACC account and associated user account
- Runs daily via Laravel scheduler

**Manual Execution:**
```bash
php artisan subscriptions:check-expired
```

---

## Instructor Authorization Payment

### Overview
The instructor authorization process involves multiple steps with payment required before final authorization.

### Flow

1. **Training Center** creates instructor and requests authorization
2. **ACC Admin** reviews and approves (sets authorization price)
3. **Group Admin** sets commission percentage
4. **Training Center** pays authorization fee
5. **Instructor** is officially authorized

---

### 1. ACC Admin Approve Instructor (Set Price)

**Endpoint:** `PUT /api/acc/instructors/requests/{id}/approve`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC Admin approves instructor authorization request and sets the authorization price.

**Request Body:**
```json
{
  "authorization_price": 500.00
}
```

**Response:** `200 OK`
```json
{
  "message": "Instructor approved successfully. Waiting for Group Admin to set commission percentage.",
  "authorization": {
    "id": 1,
    "instructor_id": 5,
    "acc_id": 3,
    "training_center_id": 2,
    "status": "approved",
    "group_admin_status": "pending",
    "authorization_price": "500.00",
    "payment_status": "pending",
    "reviewed_by": 10,
    "reviewed_at": "2025-12-19T10:00:00.000000Z"
  }
}
```

**Validation Rules:**
- `authorization_price`: required, numeric, min:0

**Notes:**
- After approval, status changes to `approved` and `group_admin_status` becomes `pending`
- Training Center cannot pay until Group Admin sets commission percentage

---

### 2. Group Admin Set Commission Percentage

**Endpoint:** `PUT /api/admin/instructor-authorizations/{id}/set-commission`  
**Authentication:** Required (Group Admin)  
**Description:** Group Admin sets commission percentage for instructor authorization.

**Request Body:**
```json
{
  "commission_percentage": 15.5
}
```

**Response:** `200 OK`
```json
{
  "message": "Commission percentage set successfully. Training Center can now complete payment.",
  "authorization": {
    "id": 1,
    "instructor_id": 5,
    "acc_id": 3,
    "training_center_id": 2,
    "status": "approved",
    "group_admin_status": "commission_set",
    "authorization_price": "500.00",
    "commission_percentage": "15.50",
    "payment_status": "pending",
    "group_commission_set_by": 1,
    "group_commission_set_at": "2025-12-19T11:00:00.000000Z"
  }
}
```

**Error Response:** `400 Bad Request`
```json
{
  "message": "Authorization must be approved by ACC Admin first and waiting for commission setting"
}
```

**Validation Rules:**
- `commission_percentage`: required, numeric, min:0, max:100

**Notes:**
- Commission percentage determines how payment is split between Group and ACC
- After commission is set, `group_admin_status` becomes `commission_set`
- Training Center receives notification to complete payment

---

### 3. Group Admin View Pending Commission Requests

**Endpoint:** `GET /api/admin/instructor-authorizations/pending-commission`  
**Authentication:** Required (Group Admin)  
**Description:** Get all instructor authorization requests waiting for commission percentage setting.

**Response:** `200 OK`
```json
{
  "authorizations": [
    {
      "id": 1,
      "instructor_id": 5,
      "acc_id": 3,
      "training_center_id": 2,
      "status": "approved",
      "group_admin_status": "pending",
      "authorization_price": "500.00",
      "payment_status": "pending",
      "instructor": {
        "id": 5,
        "first_name": "John",
        "last_name": "Doe"
      },
      "acc": {
        "id": 3,
        "name": "Aviation ACC"
      },
      "training_center": {
        "id": 2,
        "name": "Training Center Name"
      }
    }
  ],
  "total": 1
}
```

---

### 4. Training Center Pay Authorization

**Endpoint:** `POST /api/training-center/instructors/authorizations/{id}/pay`  
**Authentication:** Required (Training Center Admin)  
**Description:** Training Center pays for instructor authorization after commission is set.

**Request Body:**
```json
{
  "payment_method": "wallet",
  "payment_intent_id": "pi_1234567890"
}
```

**Response:** `200 OK`
```json
{
  "message": "Payment successful. Instructor is now officially authorized.",
  "authorization": {
    "id": 1,
    "instructor_id": 5,
    "acc_id": 3,
    "training_center_id": 2,
    "status": "approved",
    "group_admin_status": "completed",
    "authorization_price": "500.00",
    "commission_percentage": "15.50",
    "payment_status": "paid",
    "payment_date": "2025-12-19T12:00:00.000000Z",
    "payment_transaction_id": 456
  },
  "transaction": {
    "id": 456,
    "transaction_type": "commission",
    "amount": "500.00",
    "status": "completed"
  }
}
```

**Error Responses:**

**400 Bad Request** - Not approved yet
```json
{
  "message": "Authorization must be approved by ACC Admin first"
}
```

**400 Bad Request** - Commission not set
```json
{
  "message": "Group Admin must set commission percentage first"
}
```

**400 Bad Request** - Already paid
```json
{
  "message": "Authorization already paid"
}
```

**400 Bad Request** - Insufficient wallet balance
```json
{
  "message": "Insufficient wallet balance"
}
```

**Validation Rules:**
- `payment_method`: required, enum: `wallet`, `credit_card`
- `payment_intent_id`: nullable, string (for Stripe)

**Notes:**
- Payment creates a transaction record
- Commission is automatically distributed and recorded in CommissionLedger
- After payment, `group_admin_status` becomes `completed` and instructor is officially authorized
- Commission distribution:
  - Group receives: `authorization_price * commission_percentage / 100`
  - ACC receives: `authorization_price * (100 - commission_percentage) / 100`

---

### 5. Training Center View Authorizations

**Endpoint:** `GET /api/training-center/instructors/authorizations`  
**Authentication:** Required (Training Center Admin)  
**Description:** Get all instructor authorization requests for the training center.

**Query Parameters:**
- `status`: Filter by status (optional, enum: `pending`, `approved`, `rejected`, `returned`)
- `payment_status`: Filter by payment status (optional, enum: `pending`, `paid`, `failed`)

**Response:** `200 OK`
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

---

## Certificate Code Purchase Payment

### Overview
Training Centers purchase certificate codes from ACCs. Payments are automatically distributed based on commission percentages set by Group Admin.

### Flow

1. **Group Admin** sets commission percentage for ACC
2. **Training Center** purchases codes from ACC
3. **Payment** is automatically distributed:
   - Group receives commission percentage
   - ACC receives remaining amount

---

### Purchase Certificate Codes

**Endpoint:** `POST /api/training-center/codes/purchase`  
**Authentication:** Required (Training Center Admin)  
**Description:** Purchase certificate codes with automatic commission distribution.

**Request Body:**
```json
{
  "acc_id": 3,
  "course_id": 5,
  "quantity": 10,
  "discount_code": "SAVE20",
  "payment_method": "wallet"
}
```

**Response:** `201 Created`
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "training_center_id": 2,
    "acc_id": 3,
    "quantity": 10,
    "total_amount": "4000.00",
    "payment_method": "wallet",
    "transaction_id": 789,
    "certificate_codes": [
      {
        "id": 1,
        "code": "ABC123XYZ456",
        "course_id": 5,
        "purchased_price": "400.00",
        "status": "available"
      }
    ]
  }
}
```

**Commission Distribution:**
- If ACC commission percentage is 15%:
  - Group receives: `4000.00 * 15% = 600.00`
  - ACC receives: `4000.00 * 85% = 3400.00`
- Commission amounts are recorded in `CommissionLedger` table

**Validation Rules:**
- `acc_id`: required, exists:accs,id
- `course_id`: required, exists:courses,id
- `quantity`: required, integer, min:1
- `discount_code`: nullable, string
- `payment_method`: required, enum: `wallet`, `credit_card`

**Notes:**
- Commission percentage is retrieved from ACC's `commission_percentage` field (set by Group Admin)
- Commission distribution is automatic and recorded in CommissionLedger
- Transaction is created for payment tracking
- Commission ledger entry is created for settlement tracking

---

## Summary

### ACC Subscription
- ✅ Payment creates subscription
- ✅ Automatic suspension on expiration (scheduled command)
- ✅ Reactivation only after renewal payment
- ✅ Default duration: 1 year

### Instructor Authorization
- ✅ ACC Admin sets authorization price
- ✅ Group Admin sets commission percentage
- ✅ Training Center pays authorization fee
- ✅ Automatic commission distribution
- ✅ Official authorization after payment

### Certificate Code Purchase
- ✅ Automatic commission distribution
- ✅ Commission based on ACC's commission percentage
- ✅ Commission ledger entries created
- ✅ Ready for settlement processing

---

## Database Schema Updates

### instructor_acc_authorization table
Added fields:
- `authorization_price` (decimal 10,2) - Price set by ACC Admin
- `payment_status` (enum: pending, paid, failed) - Payment status
- `payment_date` (timestamp) - When payment was made
- `payment_transaction_id` (string) - Reference to transaction
- `group_admin_status` (enum: pending, commission_set, completed) - Group Admin workflow status
- `group_commission_set_by` (foreign key to users) - Who set the commission
- `group_commission_set_at` (timestamp) - When commission was set

---

## Scheduled Commands

### Check Expired Subscriptions
**Command:** `php artisan subscriptions:check-expired`  
**Schedule:** Daily  
**Purpose:** Automatically suspend ACC accounts with expired subscriptions

**Setup:**  
Add to server cron:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

**Last Updated:** December 19, 2025

