# Transaction Commission Update - Frontend Developer Guide

## Overview

Transactions now include separate amounts for commission and provider payments. Admin users receive notifications when commission is received, and commission amounts are prominently displayed in transaction data.

## What Changed?

### Before
- Transactions only showed total `amount`
- Commission was calculated separately
- No notifications for commission received

### After
- Transactions include `commission_amount` and `provider_amount`
- Admin receives notifications when commission is received
- Dashboard shows commission totals
- Transaction lists show commission prominently

## API Response Changes

### Admin Financial Transactions

**Endpoint**: `GET /api/admin/financial/transactions`

**New Response Fields**:
```json
{
    "data": [
        {
            "id": 123,
            "transaction_type": "code_purchase",
            "amount": 1000.00,              // Total amount (unchanged)
            "commission_amount": 100.00,     // ⭐ NEW - Commission received by admin
            "provider_amount": 900.00,       // ⭐ NEW - Amount ACC received
            "payment_type": "destination_charge", // ⭐ NEW - Payment method
            "currency": "USD",
            "status": "completed",
            "payer": {...},
            "payee": {...},
            "commission_ledgers": [...]
        }
    ],
    "summary": {
        "total_transactions": 50,
        "total_amount": 50000.00,
        "total_commission": 5000.00,        // ⭐ NEW - Total commission received
        "completed_amount": 45000.00,
        "completed_commission": 4500.00,   // ⭐ NEW - Completed commission
        "pending_amount": 5000.00
    }
}
```

### Admin Dashboard

**Endpoint**: `GET /api/admin/dashboard`

**Response** (unchanged structure, but now shows commission):
```json
{
    "revenue": {
        "monthly": 10000.00,  // ⭐ Now shows commission received this month
        "total": 50000.00     // ⭐ Now shows total commission received
    }
}
```

### ACC Dashboard

**Endpoint**: `GET /api/acc/dashboard`

**Response** (unchanged structure, but now shows provider amount):
```json
{
    "revenue": {
        "monthly": 90000.00,  // ⭐ Now shows amount ACC received this month
        "total": 450000.00    // ⭐ Now shows total amount ACC received
    }
}
```

### ACC Financial Transactions

**Endpoint**: `GET /api/acc/financial/transactions`

**New Response Fields**:
```json
{
    "data": [
        {
            "id": 123,
            "transaction_type": "code_purchase",
            "amount": 1000.00,              // Total amount
            "commission_amount": 100.00,     // ⭐ NEW - Platform commission
            "provider_amount": 900.00,       // ⭐ NEW - Amount ACC received
            "received_amount": 900.00,       // ⭐ NEW - Amount ACC received (same as provider_amount)
            "payment_type": "destination_charge", // ⭐ NEW
            "currency": "USD",
            "status": "completed"
        }
    ],
    "summary": {
        "total_received": 90000.00,         // ⭐ Now shows provider_amount sum
        "completed_received": 90000.00      // ⭐ NEW - Completed received amount
    }
}
```

## Notification Changes

### New Notification Type: Commission Received

**Type**: `commission_received`

**When**: Admin receives commission from a transaction

**Notification Object**:
```json
{
    "id": 1,
    "type": "commission_received",
    "title": "Commission Received",
    "message": "Commission of $100.00 received from Code Purchase (Paid by: ABC Training Center) (Provider: XYZ ACC). Total transaction amount: $1,000.00.",
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

### Updated Notification: Code Purchase

**Type**: `code_purchase_admin`

**Updated Message**: Now includes commission amount
```
"ABC Training Center purchased 10 certificate code(s) for $1,000.00. Commission received: $100.00."
```

**Updated Data**:
```json
{
    "batch_id": 5,
    "training_center_name": "ABC Training Center",
    "quantity": 10,
    "amount": 1000.00,
    "commission_amount": 100.00  // ⭐ NEW
}
```

### Updated Notification: Instructor Authorization

**Type**: `instructor_authorization_paid`

**Updated Message**: Now includes commission amount
```
"Payment of $1,000.00 received for instructor authorization: John Doe. Commission received: $100.00."
```

**Updated Data**:
```json
{
    "authorization_id": 30,
    "instructor_name": "John Doe",
    "amount": 1000.00,
    "commission_amount": 100.00  // ⭐ NEW
}
```

## Frontend Implementation

### 1. Update Transaction List Component

#### React Example

```jsx
import { useState, useEffect } from 'react';

function AdminTransactionList() {
    const [transactions, setTransactions] = useState([]);
    const [summary, setSummary] = useState(null);

    useEffect(() => {
        fetchTransactions();
    }, []);

    const fetchTransactions = async () => {
        const response = await fetch('/api/admin/financial/transactions', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        setTransactions(data.data);
        setSummary(data.summary);
    };

    return (
        <div className="transactions-container">
            {/* Summary Section */}
            <div className="summary-cards">
                <div className="card">
                    <h3>Total Commission</h3>
                    <p className="commission-amount">
                        ${summary?.total_commission?.toFixed(2) || '0.00'}
                    </p>
                </div>
                <div className="card">
                    <h3>Completed Commission</h3>
                    <p className="commission-amount">
                        ${summary?.completed_commission?.toFixed(2) || '0.00'}
                    </p>
                </div>
            </div>

            {/* Transaction Table */}
            <table className="transactions-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Total Amount</th>
                        <th className="commission-column">Commission</th>
                        <th>Provider Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    {transactions.map(transaction => (
                        <TransactionRow 
                            key={transaction.id} 
                            transaction={transaction} 
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function TransactionRow({ transaction }) {
    return (
        <tr>
            <td>{transaction.id}</td>
            <td>{transaction.transaction_type}</td>
            <td>${transaction.amount.toFixed(2)}</td>
            <td className="commission-cell highlight">
                {transaction.commission_amount ? (
                    <>
                        <span className="commission-icon">💰</span>
                        ${transaction.commission_amount.toFixed(2)}
                    </>
                ) : (
                    '$0.00'
                )}
            </td>
            <td>
                {transaction.provider_amount 
                    ? `$${transaction.provider_amount.toFixed(2)}` 
                    : 'N/A'
                }
            </td>
            <td>
                <span className={`status-badge ${transaction.status}`}>
                    {transaction.status}
                </span>
            </td>
            <td>{new Date(transaction.completed_at).toLocaleDateString()}</td>
        </tr>
    );
}
```

#### Vue Example

```vue
<template>
    <div class="transactions-container">
        <!-- Summary Section -->
        <div class="summary-cards">
            <div class="card">
                <h3>Total Commission</h3>
                <p class="commission-amount">
                    ${{ summary?.total_commission?.toFixed(2) || '0.00' }}
                </p>
            </div>
            <div class="card">
                <h3>Completed Commission</h3>
                <p class="commission-amount">
                    ${{ summary?.completed_commission?.toFixed(2) || '0.00' }}
                </p>
            </div>
        </div>

        <!-- Transaction Table -->
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Total Amount</th>
                    <th class="commission-column">Commission</th>
                    <th>Provider Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="transaction in transactions" :key="transaction.id">
                    <td>{{ transaction.id }}</td>
                    <td>{{ transaction.transaction_type }}</td>
                    <td>${{ transaction.amount.toFixed(2) }}</td>
                    <td class="commission-cell highlight">
                        <span v-if="transaction.commission_amount">
                            <span class="commission-icon">💰</span>
                            ${{ transaction.commission_amount.toFixed(2) }}
                        </span>
                        <span v-else>$0.00</span>
                    </td>
                    <td>
                        {{ transaction.provider_amount 
                            ? `$${transaction.provider_amount.toFixed(2)}` 
                            : 'N/A' 
                        }}
                    </td>
                    <td>
                        <span :class="`status-badge ${transaction.status}`">
                            {{ transaction.status }}
                        </span>
                    </td>
                    <td>{{ formatDate(transaction.completed_at) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    data() {
        return {
            transactions: [],
            summary: null
        };
    },
    mounted() {
        this.fetchTransactions();
    },
    methods: {
        async fetchTransactions() {
            const response = await fetch('/api/admin/financial/transactions', {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });
            const data = await response.json();
            this.transactions = data.data;
            this.summary = data.summary;
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString();
        }
    }
}
</script>
```

### 2. Update Dashboard Component

#### React Example

```jsx
function AdminDashboard() {
    const [dashboard, setDashboard] = useState(null);

    useEffect(() => {
        fetchDashboard();
    }, []);

    const fetchDashboard = async () => {
        const response = await fetch('/api/admin/dashboard', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        setDashboard(data);
    };

    return (
        <div className="dashboard">
            <div className="revenue-card highlight">
                <h2>Commission Revenue</h2>
                <div className="amount-large">
                    <span className="currency">$</span>
                    {dashboard?.revenue?.total?.toFixed(2) || '0.00'}
                </div>
                <div className="amount-small">
                    This Month: ${dashboard?.revenue?.monthly?.toFixed(2) || '0.00'}
                </div>
            </div>
        </div>
    );
}
```

### 3. Display Commission Notifications

#### React Example

```jsx
function NotificationList() {
    const [notifications, setNotifications] = useState([]);

    useEffect(() => {
        fetchNotifications();
    }, []);

    const fetchNotifications = async () => {
        const response = await fetch('/api/notifications', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        setNotifications(data.notifications || []);
    };

    // Filter commission notifications
    const commissionNotifications = notifications.filter(
        n => n.type === 'commission_received'
    );

    return (
        <div className="notifications">
            <h2>Commission Notifications</h2>
            {commissionNotifications.map(notification => (
                <CommissionNotificationCard 
                    key={notification.id} 
                    notification={notification} 
                />
            ))}
        </div>
    );
}

function CommissionNotificationCard({ notification }) {
    const { data } = notification;
    
    return (
        <div className="notification-card commission-notification">
            <div className="notification-header">
                <span className="icon">💰</span>
                <h3>{notification.title}</h3>
                {!notification.is_read && <span className="badge">New</span>}
            </div>
            <div className="notification-body">
                <p>{notification.message}</p>
                <div className="commission-details">
                    <div className="detail-item">
                        <span className="label">Commission:</span>
                        <span className="value highlight">
                            ${data.commission_amount?.toFixed(2)}
                        </span>
                    </div>
                    <div className="detail-item">
                        <span className="label">Total Amount:</span>
                        <span className="value">
                            ${data.total_amount?.toFixed(2)}
                        </span>
                    </div>
                    <div className="detail-item">
                        <span className="label">Transaction ID:</span>
                        <span className="value">#{data.transaction_id}</span>
                    </div>
                </div>
            </div>
            <div className="notification-footer">
                <span className="date">
                    {new Date(notification.created_at).toLocaleString()}
                </span>
            </div>
        </div>
    );
}
```

### 4. Update TypeScript Types

```typescript
interface Transaction {
    id: number;
    transaction_type: string;
    amount: number;
    commission_amount: number | null;      // ⭐ NEW
    provider_amount: number | null;        // ⭐ NEW
    received_amount?: number | null;       // ⭐ NEW (for ACC)
    payment_type: 'destination_charge' | 'standard' | null; // ⭐ NEW
    currency: string;
    payment_method: string;
    status: string;
    payer: PayerInfo | null;
    payee: PayeeInfo | null;
    commission_ledgers: CommissionLedger[];
    completed_at: string | null;
    created_at: string;
    updated_at: string;
}

interface TransactionSummary {
    total_transactions: number;
    total_amount: number;
    total_commission: number;             // ⭐ NEW
    completed_amount: number;
    completed_commission: number;          // ⭐ NEW
    pending_amount: number;
}

interface CommissionNotification {
    id: number;
    type: 'commission_received';
    title: string;
    message: string;
    data: {
        transaction_id: number;
        transaction_type: string;
        commission_amount: number;
        total_amount: number;
        payer_name: string | null;
        payee_name: string | null;
    };
    is_read: boolean;
    created_at: string;
}

interface DashboardRevenue {
    monthly: number;  // Commission received this month (admin) or provider amount (ACC)
    total: number;     // Total commission received (admin) or total provider amount (ACC)
}
```

## CSS Styling Recommendations

### Highlight Commission Amounts

```css
/* Commission column styling */
.commission-column {
    background-color: #f0f9ff;
    font-weight: 600;
}

.commission-cell {
    color: #059669; /* Green color for commission */
    font-weight: 600;
}

.commission-cell.highlight {
    background-color: #d1fae5;
    padding: 4px 8px;
    border-radius: 4px;
}

.commission-icon {
    margin-right: 4px;
    font-size: 1.1em;
}

.commission-amount {
    color: #059669;
    font-size: 1.5em;
    font-weight: 700;
}

/* Commission notification styling */
.commission-notification {
    border-left: 4px solid #059669;
    background-color: #f0fdf4;
}

.commission-notification .notification-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.commission-notification .icon {
    font-size: 1.5em;
}

.commission-details {
    margin-top: 12px;
    padding: 12px;
    background-color: white;
    border-radius: 4px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.detail-item .label {
    color: #6b7280;
}

.detail-item .value.highlight {
    color: #059669;
    font-weight: 600;
}
```

## UI/UX Recommendations

### 1. Transaction Table Layout

```
┌────┬──────────────┬──────────┬─────────────┬──────────────┬─────────┐
│ ID │ Type         │ Total    │ 💰 Commission│ Provider     │ Status  │
├────┼──────────────┼──────────┼─────────────┼──────────────┼─────────┤
│ 1  │ code_purchase│ $1,000   │ 💰 $100.00  │ $900.00      │ ✅ Done │
│ 2  │ instructor   │ $500     │ 💰 $50.00   │ $450.00      │ ✅ Done │
└────┴──────────────┴──────────┴─────────────┴──────────────┴─────────┘
```

### 2. Dashboard Widget

```
┌─────────────────────────────┐
│  💰 Commission Revenue       │
├─────────────────────────────┤
│  Total                      │
│  $50,000.00                 │
│                             │
│  This Month                 │
│  $10,000.00                 │
└─────────────────────────────┘
```

### 3. Notification Badge

Show unread commission notifications count:

```jsx
const unreadCommissionCount = notifications.filter(
    n => n.type === 'commission_received' && !n.is_read
).length;

<Badge count={unreadCommissionCount} className="commission-badge">
    💰
</Badge>
```

## Handling Null Values

### Commission Amount May Be Null

For old transactions or transactions without commission:

```javascript
// Safe display
const displayCommission = (transaction) => {
    if (transaction.commission_amount) {
        return `$${transaction.commission_amount.toFixed(2)}`;
    }
    return '$0.00'; // or 'N/A'
};

// In component
<td>{displayCommission(transaction)}</td>
```

### Provider Amount May Be Null

```javascript
const displayProviderAmount = (transaction) => {
    if (transaction.provider_amount) {
        return `$${transaction.provider_amount.toFixed(2)}`;
    }
    // Fallback: calculate from amount and commission
    if (transaction.commission_amount) {
        const providerAmount = transaction.amount - transaction.commission_amount;
        return `$${providerAmount.toFixed(2)}`;
    }
    return 'N/A';
};
```

## Filtering and Sorting

### Filter by Commission Amount

```javascript
// Show only transactions with commission
const transactionsWithCommission = transactions.filter(
    t => t.commission_amount && t.commission_amount > 0
);

// Sort by commission amount (highest first)
const sortedByCommission = transactions.sort((a, b) => {
    const aCommission = a.commission_amount || 0;
    const bCommission = b.commission_amount || 0;
    return bCommission - aCommission;
});
```

### Filter Commission Notifications

```javascript
// Get only commission notifications
const commissionNotifications = notifications.filter(
    n => n.type === 'commission_received'
);

// Get unread commission notifications
const unreadCommission = notifications.filter(
    n => n.type === 'commission_received' && !n.is_read
);
```

## Example: Complete Transaction List Component

```jsx
import { useState, useEffect } from 'react';

function AdminTransactionList() {
    const [transactions, setTransactions] = useState([]);
    const [summary, setSummary] = useState(null);
    const [filter, setFilter] = useState('all'); // all, with_commission, without_commission

    useEffect(() => {
        fetchTransactions();
    }, []);

    const fetchTransactions = async () => {
        const response = await fetch('/api/admin/financial/transactions', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        setTransactions(data.data);
        setSummary(data.summary);
    };

    const filteredTransactions = transactions.filter(t => {
        if (filter === 'with_commission') {
            return t.commission_amount && t.commission_amount > 0;
        }
        if (filter === 'without_commission') {
            return !t.commission_amount || t.commission_amount === 0;
        }
        return true;
    });

    return (
        <div className="admin-transactions">
            {/* Summary Cards */}
            <div className="summary-section">
                <div className="summary-card">
                    <h3>Total Commission</h3>
                    <p className="commission-highlight">
                        ${summary?.total_commission?.toFixed(2) || '0.00'}
                    </p>
                </div>
                <div className="summary-card">
                    <h3>Completed Commission</h3>
                    <p className="commission-highlight">
                        ${summary?.completed_commission?.toFixed(2) || '0.00'}
                    </p>
                </div>
            </div>

            {/* Filters */}
            <div className="filters">
                <button 
                    onClick={() => setFilter('all')}
                    className={filter === 'all' ? 'active' : ''}
                >
                    All Transactions
                </button>
                <button 
                    onClick={() => setFilter('with_commission')}
                    className={filter === 'with_commission' ? 'active' : ''}
                >
                    With Commission
                </button>
                <button 
                    onClick={() => setFilter('without_commission')}
                    className={filter === 'without_commission' ? 'active' : ''}
                >
                    Without Commission
                </button>
            </div>

            {/* Transaction Table */}
            <table className="transactions-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th className="commission-header">
                            💰 Commission
                        </th>
                        <th>Provider</th>
                        <th>Payment Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    {filteredTransactions.map(transaction => (
                        <TransactionRow 
                            key={transaction.id} 
                            transaction={transaction} 
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function TransactionRow({ transaction }) {
    const hasCommission = transaction.commission_amount && transaction.commission_amount > 0;
    
    return (
        <tr className={hasCommission ? 'has-commission' : ''}>
            <td>{transaction.id}</td>
            <td>
                <span className="transaction-type">
                    {transaction.transaction_type.replace('_', ' ')}
                </span>
            </td>
            <td>${transaction.amount.toFixed(2)}</td>
            <td className={`commission-cell ${hasCommission ? 'highlight' : ''}`}>
                {hasCommission ? (
                    <>
                        <span className="commission-icon">💰</span>
                        <strong>${transaction.commission_amount.toFixed(2)}</strong>
                    </>
                ) : (
                    <span className="no-commission">$0.00</span>
                )}
            </td>
            <td>
                {transaction.provider_amount 
                    ? `$${transaction.provider_amount.toFixed(2)}` 
                    : <span className="na">N/A</span>
                }
            </td>
            <td>
                <span className={`payment-type ${transaction.payment_type}`}>
                    {transaction.payment_type === 'destination_charge' 
                        ? 'Auto Split' 
                        : 'Standard'
                    }
                </span>
            </td>
            <td>
                <span className={`status-badge ${transaction.status}`}>
                    {transaction.status}
                </span>
            </td>
            <td>
                {transaction.completed_at 
                    ? new Date(transaction.completed_at).toLocaleDateString()
                    : '-'
                }
            </td>
        </tr>
    );
}
```

## Testing Checklist

- [ ] Transaction list displays `commission_amount` column
- [ ] Commission amounts are highlighted/styled differently
- [ ] Summary shows `total_commission` and `completed_commission`
- [ ] Dashboard shows commission revenue (not total revenue)
- [ ] Notifications include commission information
- [ ] Commission notifications are filtered and displayed
- [ ] Null commission amounts are handled gracefully
- [ ] Payment type is displayed correctly

## Migration Notes

- **No breaking changes** - All new fields are optional/nullable
- **Backward compatible** - Old transactions work fine
- **Gradual adoption** - Can update UI incrementally

## Summary

✅ **Commission Amount** in transaction data  
✅ **Provider Amount** in transaction data  
✅ **Commission Notifications** for admin  
✅ **Summary Statistics** show commission totals  
✅ **Dashboard** shows commission revenue  

Frontend developers should:
1. Display `commission_amount` prominently in transaction lists
2. Show commission totals in summary/dashboard
3. Display commission notifications with proper styling
4. Handle null values gracefully
5. Highlight commission amounts visually

