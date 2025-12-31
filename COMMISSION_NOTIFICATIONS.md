# Commission Notifications for Admin - Implementation Guide

## Overview

Admin users now receive notifications when commission is received from transactions. The commission amount is also prominently displayed in transaction data in the admin dashboard.

## Notification Types

### 1. Commission Received Notification

**Type**: `commission_received`

**When Sent**: 
- When a transaction with commission is completed
- Only sent if commission amount > 0

**Notification Details**:
```json
{
    "type": "commission_received",
    "title": "Commission Received",
    "message": "Commission of $100.00 received from Code Purchase (Paid by: ABC Training Center) (Provider: XYZ ACC). Total transaction amount: $1000.00.",
    "data": {
        "transaction_id": 123,
        "transaction_type": "code_purchase",
        "commission_amount": 100.00,
        "total_amount": 1000.00,
        "payer_name": "ABC Training Center",
        "payee_name": "XYZ ACC"
    }
}
```

### 2. Updated Code Purchase Notification

**Type**: `code_purchase_admin`

**When Sent**: 
- When certificate codes are purchased
- Now includes commission amount in message

**Notification Details**:
```json
{
    "type": "code_purchase_admin",
    "title": "Certificate Codes Purchased",
    "message": "ABC Training Center purchased 10 certificate code(s) for $1000.00. Commission received: $100.00.",
    "data": {
        "batch_id": 5,
        "training_center_name": "ABC Training Center",
        "quantity": 10,
        "amount": 1000.00,
        "commission_amount": 100.00
    }
}
```

### 3. Updated Instructor Authorization Notification

**Type**: `instructor_authorization_paid`

**When Sent**: 
- When instructor authorization payment is completed
- Now includes commission amount in message

**Notification Details**:
```json
{
    "type": "instructor_authorization_paid",
    "title": "Instructor Authorization Payment Received",
    "message": "Payment of $1000.00 received for instructor authorization: John Doe. Commission received: $100.00.",
    "data": {
        "authorization_id": 30,
        "instructor_name": "John Doe",
        "amount": 1000.00,
        "commission_amount": 100.00
    }
}
```

## Transaction Data in Admin Dashboard

### Admin Financial Transactions Endpoint

**Endpoint**: `GET /api/admin/financial/transactions`

**Response**:
```json
{
    "data": [
        {
            "id": 123,
            "transaction_type": "code_purchase",
            "amount": 1000.00,              // Total transaction amount
            "commission_amount": 100.00,     // ⭐ Commission received by admin
            "provider_amount": 900.00,      // Amount ACC received
            "payment_type": "destination_charge",
            "currency": "USD",
            "status": "completed",
            "payer": {
                "name": "ABC Training Center",
                "type": "training_center"
            },
            "payee": {
                "name": "XYZ ACC",
                "type": "acc"
            },
            "commission_ledgers": [...]
        }
    ],
    "summary": {
        "total_commission": 10000.00,       // ⭐ Total commission received
        "completed_commission": 10000.00   // ⭐ Completed transactions commission
    }
}
```

### Admin Dashboard Endpoint

**Endpoint**: `GET /api/admin/dashboard`

**Response**:
```json
{
    "revenue": {
        "monthly": 10000.00,    // ⭐ Commission received this month
        "total": 50000.00       // ⭐ Total commission received
    }
}
```

## Frontend Implementation

### Display Commission in Transaction List

```javascript
// Admin Transactions List
transactions.forEach(transaction => {
    console.log(`Transaction ID: ${transaction.id}`);
    console.log(`Total Amount: $${transaction.amount}`);
    console.log(`Commission: $${transaction.commission_amount}`); // ⭐ Highlight this
    console.log(`Provider Received: $${transaction.provider_amount}`);
    console.log(`Payment Type: ${transaction.payment_type}`);
});
```

### React Component Example

```jsx
function AdminTransactionRow({ transaction }) {
    return (
        <tr>
            <td>{transaction.id}</td>
            <td>{transaction.transaction_type}</td>
            <td>${transaction.amount}</td>
            <td className="commission-amount highlight">
                ${transaction.commission_amount || '0.00'}
            </td>
            <td>${transaction.provider_amount || '0.00'}</td>
            <td>{transaction.status}</td>
        </tr>
    );
}
```

### Vue Component Example

```vue
<template>
    <tr>
        <td>{{ transaction.id }}</td>
        <td>{{ transaction.transaction_type }}</td>
        <td>${{ transaction.amount }}</td>
        <td class="commission-amount highlight">
            ${{ transaction.commission_amount || '0.00' }}
        </td>
        <td>${{ transaction.provider_amount || '0.00' }}</td>
        <td>{{ transaction.status }}</td>
    </tr>
</template>
```

### Display Notifications

```javascript
// Fetch notifications
const notifications = await fetch('/api/notifications', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});

const data = await notifications.json();

// Filter commission notifications
const commissionNotifications = data.notifications.filter(
    n => n.type === 'commission_received'
);

// Display commission notifications
commissionNotifications.forEach(notification => {
    console.log(`Commission: $${notification.data.commission_amount}`);
    console.log(`From: ${notification.data.transaction_type}`);
    console.log(`Transaction ID: ${notification.data.transaction_id}`);
});
```

## Notification Display Examples

### Commission Received Notification Card

```
┌─────────────────────────────────────────┐
│  💰 Commission Received                  │
├─────────────────────────────────────────┤
│  Commission of $100.00 received from    │
│  Code Purchase                           │
│                                         │
│  Paid by: ABC Training Center           │
│  Provider: XYZ ACC                      │
│  Total: $1,000.00                       │
│                                         │
│  Transaction ID: #123                  │
└─────────────────────────────────────────┘
```

### Transaction List with Commission Highlight

```
┌────┬──────────────┬──────────┬─────────────┬──────────────┐
│ ID │ Type         │ Total    │ Commission  │ Provider     │
├────┼──────────────┼──────────┼─────────────┼──────────────┤
│ 1  │ code_purchase│ $1,000   │ 💰 $100.00  │ $900.00      │
│ 2  │ instructor   │ $500     │ 💰 $50.00   │ $450.00      │
└────┴──────────────┴──────────┴─────────────┴──────────────┘
```

## Summary Statistics

### Admin Financial Summary

The summary now includes commission-specific totals:

```json
{
    "summary": {
        "total_transactions": 50,
        "total_amount": 50000.00,          // Total transaction amounts
        "total_commission": 5000.00,       // ⭐ Total commission received
        "completed_amount": 45000.00,       // Completed transaction amounts
        "completed_commission": 4500.00,   // ⭐ Completed commission
        "pending_amount": 5000.00
    }
}
```

## When Notifications Are Sent

### Code Purchase

1. **Transaction Created** → Commission amount stored
2. **Commission > 0** → Notification sent to admin
3. **Notification Type**: `commission_received`
4. **Also Sent**: Updated `code_purchase_admin` notification with commission

### Instructor Authorization

1. **Transaction Created** → Commission amount stored
2. **Commission > 0** → Notification sent to admin
3. **Notification Type**: `commission_received`
4. **Also Sent**: Updated `instructor_authorization_paid` notification with commission

## Notification Data Structure

```json
{
    "id": 1,
    "user_id": 1,
    "type": "commission_received",
    "title": "Commission Received",
    "message": "Commission of $100.00 received from Code Purchase...",
    "data": {
        "transaction_id": 123,
        "transaction_type": "code_purchase",
        "commission_amount": 100.00,
        "total_amount": 1000.00,
        "payer_name": "ABC Training Center",
        "payee_name": "XYZ ACC"
    },
    "is_read": false,
    "created_at": "2024-01-15T10:30:00.000000Z"
}
```

## UI/UX Recommendations

### 1. Highlight Commission Amount

- Use a different color (e.g., green) for commission amounts
- Add an icon (💰) next to commission
- Make commission column prominent

### 2. Notification Badge

- Show unread commission notifications count
- Highlight commission notifications differently
- Group commission notifications together

### 3. Dashboard Widget

```
┌─────────────────────────────┐
│  Commission This Month      │
├─────────────────────────────┤
│  💰 $10,000.00              │
│                             │
│  Total Commission           │
│  💰 $50,000.00              │
└─────────────────────────────┘
```

## Testing

### Test Commission Notification

1. Create a code purchase transaction
2. Check admin notifications
3. Verify `commission_received` notification exists
4. Verify commission amount in notification data
5. Verify transaction shows commission_amount

### Test Transaction Data

1. Get admin transactions: `GET /api/admin/financial/transactions`
2. Verify `commission_amount` field exists
3. Verify `commission_amount` is correct
4. Verify summary shows `total_commission`

## Summary

✅ **Commission Amount** in transaction data (already implemented)  
✅ **Commission Notifications** sent to admin when commission received  
✅ **Updated Notifications** include commission in existing notifications  
✅ **Summary Statistics** show commission totals  
✅ **Dashboard** shows commission revenue  

Admin users will now:
- See commission amount in every transaction
- Receive notifications when commission is received
- See commission totals in dashboard and summary
- Have clear visibility into platform revenue

