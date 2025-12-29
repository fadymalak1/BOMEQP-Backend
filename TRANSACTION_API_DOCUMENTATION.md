# Payment Transactions API Documentation

Complete documentation for payment transaction APIs for Group Admin, ACC, and Training Center. All endpoints return comprehensive transaction details including payer, payee, commission ledger, and reference information.

## Base URL
```
https://aeroenix.com/v1/api
```

## Authentication
All endpoints require authentication using Laravel Sanctum with the appropriate role:
- Group Admin: `group_admin`
- ACC: `acc_admin`
- Training Center: `training_center_admin`

```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. Group Admin - Get Transactions

**GET** `/api/admin/financial/transactions`

Get all transactions where Group is either payer or payee. Returns comprehensive transaction details.

**Query Parameters:**
- `type` (string, optional) - Filter by transaction type: `subscription`, `code_purchase`, `material_purchase`, `course_purchase`, `commission`, `settlement`
- `status` (string, optional) - Filter by status: `pending`, `completed`, `failed`, `refunded`
- `payer_type` (string, optional) - Filter by payer type: `acc`, `training_center`, `group`
- `payee_type` (string, optional) - Filter by payee type: `group`, `acc`, `instructor`
- `date_from` (date, optional) - Filter transactions from date (YYYY-MM-DD)
- `date_to` (date, optional) - Filter transactions to date (YYYY-MM-DD)
- `per_page` (integer, optional) - Items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "transaction_type": "subscription",
      "payer": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "email": "info@abc.com",
        "type": "acc"
      },
      "payee": {
        "id": 1,
        "name": "BOMEQP Group",
        "type": "group"
      },
      "amount": 5000.00,
      "currency": "USD",
      "payment_method": "credit_card",
      "payment_gateway_transaction_id": "pi_1234567890",
      "status": "completed",
      "description": "ACC Subscription Payment - Annual Plan",
      "reference": {
        "id": 1,
        "type": "ACCSubscription",
        "details": {
          "acc_id": 1,
          "plan": "annual",
          "start_date": "2024-01-01",
          "end_date": "2024-12-31",
          "status": "active"
        }
      },
      "commission_ledgers": [],
      "completed_at": "2024-01-15T10:30:00.000000Z",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    },
    {
      "id": 2,
      "transaction_type": "code_purchase",
      "payer": {
        "id": 3,
        "name": "XYZ Training Center",
        "email": "info@xyz.com",
        "type": "training_center"
      },
      "payee": {
        "id": 1,
        "name": "BOMEQP Group",
        "type": "group"
      },
      "amount": 1000.00,
      "currency": "USD",
      "payment_method": "credit_card",
      "payment_gateway_transaction_id": null,
      "status": "completed",
      "description": "Certificate Code Purchase - Batch #123",
      "reference": {
        "id": 5,
        "type": "CodeBatch",
        "details": {
          "training_center_id": 3,
          "acc_id": 1,
          "quantity": 100,
          "total_amount": 1000.00
        }
      },
      "commission_ledgers": [
        {
          "id": 1,
          "acc": {
            "id": 1,
            "name": "ABC Accreditation Body"
          },
          "training_center": {
            "id": 3,
            "name": "XYZ Training Center"
          },
          "instructor": null,
          "group_commission_amount": 100.00,
          "group_commission_percentage": 10.00,
          "acc_commission_amount": 50.00,
          "acc_commission_percentage": 5.00,
          "settlement_status": "pending",
          "settlement_date": null
        }
      ],
      "completed_at": "2024-01-16T14:20:00.000000Z",
      "created_at": "2024-01-16T14:20:00.000000Z",
      "updated_at": "2024-01-16T14:20:00.000000Z"
    }
  ],
  "summary": {
    "total_transactions": 100,
    "total_amount": 50000.00,
    "completed_amount": 45000.00,
    "pending_amount": 5000.00
  },
  "current_page": 1,
  "per_page": 15,
  "total": 100,
  "last_page": 7
}
```

---

### 2. ACC - Get Transactions

**GET** `/api/acc/financial/transactions`

Get all transactions where ACC is either payer or payee. Returns comprehensive transaction details.

**Query Parameters:**
- `type` (string, optional) - Filter by transaction type: `subscription`, `code_purchase`, `material_purchase`, `course_purchase`, `commission`, `settlement`
- `status` (string, optional) - Filter by status: `pending`, `completed`, `failed`, `refunded`
- `date_from` (date, optional) - Filter transactions from date (YYYY-MM-DD)
- `date_to` (date, optional) - Filter transactions to date (YYYY-MM-DD)
- `per_page` (integer, optional) - Items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "transaction_type": "subscription",
      "payer": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "email": "info@abc.com",
        "type": "acc"
      },
      "payee": {
        "id": 1,
        "name": "BOMEQP Group",
        "type": "group"
      },
      "amount": 5000.00,
      "currency": "USD",
      "payment_method": "credit_card",
      "payment_gateway_transaction_id": "pi_1234567890",
      "status": "completed",
      "description": "ACC Subscription Payment - Annual Plan",
      "reference": {
        "id": 1,
        "type": "ACCSubscription",
        "details": {
          "acc_id": 1,
          "plan": "annual",
          "start_date": "2024-01-01",
          "end_date": "2024-12-31",
          "status": "active"
        }
      },
      "commission_ledgers": [],
      "completed_at": "2024-01-15T10:30:00.000000Z",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    },
    {
      "id": 3,
      "transaction_type": "commission",
      "payer": {
        "id": 1,
        "name": "BOMEQP Group",
        "type": "group"
      },
      "payee": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "email": "info@abc.com",
        "type": "acc"
      },
      "amount": 250.00,
      "currency": "USD",
      "payment_method": "bank_transfer",
      "payment_gateway_transaction_id": null,
      "status": "completed",
      "description": "Monthly Commission Payment",
      "reference": {
        "id": 1,
        "type": "MonthlySettlement",
        "details": {
          "acc_id": 1,
          "settlement_month": "2024-01",
          "total_revenue": 5000.00,
          "group_commission_amount": 500.00,
          "status": "completed"
        }
      },
      "commission_ledgers": [],
      "completed_at": "2024-02-01T09:00:00.000000Z",
      "created_at": "2024-02-01T09:00:00.000000Z",
      "updated_at": "2024-02-01T09:00:00.000000Z"
    }
  ],
  "summary": {
    "total_transactions": 50,
    "total_received": 25000.00,
    "total_paid": 5000.00,
    "completed_amount": 20000.00,
    "pending_amount": 5000.00
  },
  "current_page": 1,
  "per_page": 15,
  "total": 50,
  "last_page": 4
}
```

---

### 3. Training Center - Get Transactions

**GET** `/api/training-center/financial/transactions`

Get all transactions where Training Center is either payer or payee. Returns comprehensive transaction details.

**Query Parameters:**
- `type` (string, optional) - Filter by transaction type: `subscription`, `code_purchase`, `material_purchase`, `course_purchase`, `commission`, `settlement`
- `status` (string, optional) - Filter by status: `pending`, `completed`, `failed`, `refunded`
- `date_from` (date, optional) - Filter transactions from date (YYYY-MM-DD)
- `date_to` (date, optional) - Filter transactions to date (YYYY-MM-DD)
- `per_page` (integer, optional) - Items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

**Response (200):**
```json
{
  "data": [
    {
      "id": 2,
      "transaction_type": "code_purchase",
      "payer": {
        "id": 3,
        "name": "XYZ Training Center",
        "email": "info@xyz.com",
        "type": "training_center"
      },
      "payee": {
        "id": 1,
        "name": "BOMEQP Group",
        "type": "group"
      },
      "amount": 1000.00,
      "currency": "USD",
      "payment_method": "credit_card",
      "payment_gateway_transaction_id": null,
      "status": "completed",
      "description": "Certificate Code Purchase - Batch #123",
      "reference": {
        "id": 5,
        "type": "CodeBatch",
        "details": {
          "training_center_id": 3,
          "acc_id": 1,
          "quantity": 100,
          "total_amount": 1000.00
        }
      },
      "commission_ledgers": [
        {
          "id": 1,
          "acc": {
            "id": 1,
            "name": "ABC Accreditation Body"
          },
          "training_center": {
            "id": 3,
            "name": "XYZ Training Center"
          },
          "instructor": null,
          "group_commission_amount": 100.00,
          "group_commission_percentage": 10.00,
          "acc_commission_amount": 50.00,
          "acc_commission_percentage": 5.00,
          "settlement_status": "pending",
          "settlement_date": null
        }
      ],
      "completed_at": "2024-01-16T14:20:00.000000Z",
      "created_at": "2024-01-16T14:20:00.000000Z",
      "updated_at": "2024-01-16T14:20:00.000000Z"
    },
    {
      "id": 4,
      "transaction_type": "material_purchase",
      "payer": {
        "id": 3,
        "name": "XYZ Training Center",
        "email": "info@xyz.com",
        "type": "training_center"
      },
      "payee": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "email": "info@abc.com",
        "type": "acc"
      },
      "amount": 50.00,
      "currency": "USD",
      "payment_method": "credit_card",
      "payment_gateway_transaction_id": null,
      "status": "completed",
      "description": "Material Purchase - Fire Safety Manual",
      "reference": {
        "id": 2,
        "type": "TrainingCenterPurchase",
        "details": {
          "training_center_id": 3,
          "acc_id": 1,
          "purchase_type": "material",
          "item_id": 10,
          "amount": 50.00
        }
      },
      "commission_ledgers": [],
      "completed_at": "2024-01-17T11:15:00.000000Z",
      "created_at": "2024-01-17T11:15:00.000000Z",
      "updated_at": "2024-01-17T11:15:00.000000Z"
    }
  ],
  "summary": {
    "total_transactions": 30,
    "total_spent": 15000.00,
    "total_received": 2000.00,
    "completed_amount": 12000.00,
    "pending_amount": 3000.00
  },
  "current_page": 1,
  "per_page": 15,
  "total": 30,
  "last_page": 2
}
```

---

## Transaction Fields

### Transaction Object

Each transaction includes the following fields:

- `id` (integer) - Transaction ID
- `transaction_type` (string) - Type of transaction: `subscription`, `code_purchase`, `material_purchase`, `course_purchase`, `commission`, `settlement`
- `payer` (object|null) - Payer information:
  - `id` (integer) - Payer ID
  - `name` (string) - Payer name
  - `email` (string|null) - Payer email
  - `type` (string) - Payer type: `acc`, `training_center`, `group`
- `payee` (object|null) - Payee information:
  - `id` (integer) - Payee ID
  - `name` (string) - Payee name
  - `email` (string|null) - Payee email
  - `type` (string) - Payee type: `group`, `acc`, `instructor`
- `amount` (number) - Transaction amount (rounded to 2 decimal places)
- `currency` (string) - Currency code (3 characters, e.g., "USD")
- `payment_method` (string) - Payment method: `credit_card`, `bank_transfer`
- `payment_gateway_transaction_id` (string|null) - Payment gateway transaction ID (e.g., Stripe payment intent ID)
- `status` (string) - Transaction status: `pending`, `completed`, `failed`, `refunded`
- `description` (string|null) - Transaction description
- `reference` (object|null) - Reference information (what the transaction is for):
  - `id` (integer) - Reference record ID
  - `type` (string) - Reference type: `ACCSubscription`, `CodeBatch`, `TrainingCenterPurchase`, `MonthlySettlement`
  - `details` (object) - Reference-specific details (varies by type)
- `commission_ledgers` (array) - Commission ledger entries related to this transaction:
  - `id` (integer) - Commission ledger ID
  - `acc` (object|null) - ACC information
  - `training_center` (object|null) - Training center information
  - `instructor` (object|null) - Instructor information
  - `group_commission_amount` (number) - Group commission amount
  - `group_commission_percentage` (number) - Group commission percentage
  - `acc_commission_amount` (number) - ACC commission amount
  - `acc_commission_percentage` (number) - ACC commission percentage
  - `settlement_status` (string|null) - Settlement status
  - `settlement_date` (date|null) - Settlement date
- `completed_at` (datetime|null) - When the transaction was completed
- `created_at` (datetime) - When the transaction was created
- `updated_at` (datetime) - When the transaction was last updated

---

## Reference Types and Details

### ACCSubscription
```json
{
  "acc_id": 1,
  "plan": "annual",
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "status": "active"
}
```

### CodeBatch
```json
{
  "training_center_id": 3,
  "acc_id": 1,
  "quantity": 100,
  "total_amount": 1000.00
}
```

### TrainingCenterPurchase
```json
{
  "training_center_id": 3,
  "acc_id": 1,
  "purchase_type": "material",
  "item_id": 10,
  "amount": 50.00
}
```

### MonthlySettlement
```json
{
  "acc_id": 1,
  "settlement_month": "2024-01",
  "total_revenue": 5000.00,
  "group_commission_amount": 500.00,
  "status": "completed"
}
```

---

## Summary Statistics

### Group Admin Summary
- `total_transactions` - Total number of transactions
- `total_amount` - Total amount of all transactions
- `completed_amount` - Total amount of completed transactions
- `pending_amount` - Total amount of pending transactions

### ACC Summary
- `total_transactions` - Total number of transactions
- `total_received` - Total amount received (where ACC is payee)
- `total_paid` - Total amount paid (where ACC is payer)
- `completed_amount` - Total amount of completed transactions
- `pending_amount` - Total amount of pending transactions

### Training Center Summary
- `total_transactions` - Total number of transactions
- `total_spent` - Total amount spent (where Training Center is payer)
- `total_received` - Total amount received (where Training Center is payee)
- `completed_amount` - Total amount of completed transactions
- `pending_amount` - Total amount of pending transactions

---

## Usage Examples

### Example 1: Get All Transactions (Group Admin)

```javascript
const response = await fetch('/api/admin/financial/transactions', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
console.log('Total Transactions:', data.summary.total_transactions);
console.log('Transactions:', data.data);
```

### Example 2: Filter by Type and Status (ACC)

```javascript
const response = await fetch('/api/acc/financial/transactions?type=subscription&status=completed', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const data = await response.json();
console.log('Completed Subscriptions:', data.data);
```

### Example 3: Filter by Date Range (Training Center)

```javascript
const response = await fetch('/api/training-center/financial/transactions?date_from=2024-01-01&date_to=2024-01-31', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const data = await response.json();
console.log('January Transactions:', data.data);
console.log('Total Spent:', data.summary.total_spent);
```

### Example 4: Paginated Results

```javascript
const response = await fetch('/api/admin/financial/transactions?per_page=20&page=2', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const data = await response.json();
console.log('Page 2:', data.data);
console.log('Total Pages:', data.last_page);
```

---

## Testing the Endpoints

### Test Group Admin Transactions

```bash
curl -X GET "https://aeroenix.com/v1/api/admin/financial/transactions" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Test ACC Transactions with Filters

```bash
curl -X GET "https://aeroenix.com/v1/api/acc/financial/transactions?type=subscription&status=completed&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Test Training Center Transactions

```bash
curl -X GET "https://aeroenix.com/v1/api/training-center/financial/transactions?date_from=2024-01-01&date_to=2024-12-31" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Transaction Types

1. **subscription** - ACC subscription payments
2. **code_purchase** - Certificate code batch purchases
3. **material_purchase** - Course material purchases
4. **course_purchase** - Course purchases
5. **commission** - Commission payments
6. **settlement** - Monthly settlement payments

---

## Transaction Statuses

1. **pending** - Transaction is pending completion
2. **completed** - Transaction has been completed successfully
3. **failed** - Transaction failed
4. **refunded** - Transaction has been refunded

---

## Payment Methods

1. **credit_card** - Credit card payment via payment gateway
2. **bank_transfer** - Bank transfer payment

---

## Common Use Cases

### View All Completed Transactions

```javascript
const response = await fetch('/api/acc/financial/transactions?status=completed', {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

### View Transactions for Specific Month

```javascript
const response = await fetch('/api/training-center/financial/transactions?date_from=2024-01-01&date_to=2024-01-31', {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

### View Subscription Transactions Only

```javascript
const response = await fetch('/api/admin/financial/transactions?type=subscription', {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

### View Transactions with Commission Details

All transactions automatically include commission ledger information when available. Filter by transaction type to see commission-related transactions:

```javascript
const response = await fetch('/api/admin/financial/transactions?type=code_purchase', {
  headers: { 'Authorization': `Bearer ${token}` }
});

// Commission details are in commission_ledgers array
data.data.forEach(transaction => {
  transaction.commission_ledgers.forEach(ledger => {
    console.log('Group Commission:', ledger.group_commission_amount);
    console.log('ACC Commission:', ledger.acc_commission_amount);
  });
});
```

---

## Best Practices

1. **Use Pagination**: Always use pagination for large datasets to improve performance
2. **Filter by Date Range**: Use date filters to limit results to relevant time periods
3. **Filter by Status**: Filter by status to focus on specific transaction states
4. **Check Summary**: Use summary statistics for quick overview before processing detailed data
5. **Handle Null Values**: Some fields (payer, payee, reference) may be null - always check before accessing
6. **Commission Ledgers**: Check commission_ledgers array length to see if commissions are associated

---

## Summary

✅ **Group Admin**: View all transactions where group is payer or payee  
✅ **ACC**: View all transactions where ACC is payer or payee  
✅ **Training Center**: View all transactions where training center is payer or payee  
✅ **Comprehensive Details**: All endpoints return complete transaction information  
✅ **Payer/Payee Info**: Includes full details about who paid and who received  
✅ **Reference Details**: Shows what each transaction is for (subscription, purchase, etc.)  
✅ **Commission Ledgers**: Includes commission breakdown when applicable  
✅ **Filtering**: Multiple filter options (type, status, date range, etc.)  
✅ **Pagination**: Built-in pagination support  
✅ **Summary Statistics**: Quick overview statistics included in response

---

**Last Updated:** December 29, 2024  
**API Version:** 1.0

