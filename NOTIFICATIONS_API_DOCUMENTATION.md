# Notifications API Documentation

## Overview

The BOMEQP system includes a comprehensive notification system that sends real-time notifications to users for various actions and events throughout the platform.

---

## Table of Contents

1. [Notification Types](#notification-types)
2. [API Endpoints](#api-endpoints)
3. [Notification Structure](#notification-structure)
4. [Usage Examples](#usage-examples)
5. [Frontend Integration](#frontend-integration)

---

## Notification Types

### ACC-Related Notifications

| Type | Recipient | Trigger |
|------|-----------|---------|
| `acc_application` | Group Admin | New ACC registration |
| `acc_approved` | ACC Admin | ACC application approved |
| `acc_rejected` | ACC Admin | ACC application rejected |

### Subscription Notifications

| Type | Recipient | Trigger |
|------|-----------|---------|
| `subscription_paid` | ACC Admin | Subscription payment successful |
| `subscription_expiring` | ACC Admin | Subscription expiring soon |
| `subscription_payment` | Group Admin | ACC subscription payment received |
| `subscription_renewal` | Group Admin | ACC subscription renewal payment received |

### Instructor Authorization Notifications

| Type | Recipient | Trigger |
|------|-----------|---------|
| `instructor_authorization_requested` | ACC Admin | Training Center requests instructor authorization |
| `instructor_authorized` | Training Center | Instructor authorization approved (ready for payment) |
| `instructor_authorization_rejected` | Training Center | Instructor authorization rejected |
| `instructor_authorization_payment_success` | Training Center | Instructor authorization payment successful |
| `instructor_authorization_paid` | Group Admin | Instructor authorization payment received |
| `instructor_needs_commission` | Group Admin | Instructor approved by ACC, needs commission percentage set |

### Code Purchase Notifications

| Type | Recipient | Trigger |
|------|-----------|---------|
| `code_purchased` | Training Center | Certificate codes purchased successfully |
| `code_purchase_admin` | Group Admin | Certificate codes purchased (system-wide) |
| `code_purchase_acc` | ACC Admin | Certificate codes purchased (commission notification) |

### Training Center Authorization Notifications

| Type | Recipient | Trigger |
|------|-----------|---------|
| `training_center_authorization_requested` | ACC Admin | Training Center requests authorization |
| `training_center_authorized` | Training Center | Authorization approved |
| `training_center_authorization_rejected` | Training Center | Authorization rejected |
| `training_center_authorization_returned` | Training Center | Authorization request returned for revision |

---

## API Endpoints

### Base URL
All notification endpoints are prefixed with `/api/notifications`

### Authentication
All endpoints require authentication via Bearer token:
```
Authorization: Bearer {token}
```

---

### 1. Get All Notifications

**GET** `/api/notifications`

Get all notifications for the authenticated user.

**Query Parameters:**
- `is_read` (boolean, optional) - Filter by read/unread status
- `type` (string, optional) - Filter by notification type
- `per_page` (integer, optional) - Items per page (default: 15)

**Response (200):**
```json
{
  "success": true,
  "notifications": [
    {
      "id": 1,
      "user_id": 5,
      "type": "acc_approved",
      "title": "ACC Application Approved",
      "message": "Your ACC application for 'ABC Accreditation Body' has been approved. You can now access your workspace.",
      "data": {
        "acc_id": 3
      },
      "is_read": false,
      "read_at": null,
      "created_at": "2024-12-25T10:30:00.000000Z",
      "updated_at": "2024-12-25T10:30:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 67
  },
  "unread_count": 12
}
```

**Example Request:**
```javascript
// Get all notifications
GET /api/notifications

// Get unread notifications only
GET /api/notifications?is_read=false

// Get specific type
GET /api/notifications?type=acc_approved

// Pagination
GET /api/notifications?per_page=20&page=2
```

---

### 2. Get Unread Count

**GET** `/api/notifications/unread-count`

Get the count of unread notifications for the authenticated user.

**Response (200):**
```json
{
  "success": true,
  "unread_count": 12
}
```

**Example Request:**
```javascript
GET /api/notifications/unread-count
```

---

### 3. Get Single Notification

**GET** `/api/notifications/{id}`

Get details of a specific notification.

**Response (200):**
```json
{
  "success": true,
  "notification": {
    "id": 1,
    "user_id": 5,
    "type": "acc_approved",
    "title": "ACC Application Approved",
    "message": "Your ACC application for 'ABC Accreditation Body' has been approved.",
    "data": {
      "acc_id": 3
    },
    "is_read": false,
    "read_at": null,
    "created_at": "2024-12-25T10:30:00.000000Z",
    "updated_at": "2024-12-25T10:30:00.000000Z"
  }
}
```

---

### 4. Mark Notification as Read

**PUT** `/api/notifications/{id}/read`

Mark a specific notification as read.

**Response (200):**
```json
{
  "success": true,
  "message": "Notification marked as read",
  "notification": {
    "id": 1,
    "is_read": true,
    "read_at": "2024-12-25T11:00:00.000000Z"
  }
}
```

---

### 5. Mark Notification as Unread

**PUT** `/api/notifications/{id}/unread`

Mark a specific notification as unread.

**Response (200):**
```json
{
  "success": true,
  "message": "Notification marked as unread",
  "notification": {
    "id": 1,
    "is_read": false,
    "read_at": null
  }
}
```

---

### 6. Mark All Notifications as Read

**POST** `/api/notifications/mark-all-read`

Mark all unread notifications as read.

**Response (200):**
```json
{
  "success": true,
  "message": "15 notification(s) marked as read",
  "updated_count": 15
}
```

---

### 7. Delete Notification

**DELETE** `/api/notifications/{id}`

Delete a specific notification.

**Response (200):**
```json
{
  "success": true,
  "message": "Notification deleted successfully"
}
```

---

### 8. Delete All Read Notifications

**DELETE** `/api/notifications/read`

Delete all read notifications.

**Response (200):**
```json
{
  "success": true,
  "message": "42 notification(s) deleted",
  "deleted_count": 42
}
```

---

## Notification Structure

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Notification ID |
| `user_id` | integer | User who receives the notification |
| `type` | string | Notification type (see types above) |
| `title` | string | Notification title |
| `message` | string | Notification message |
| `data` | object | Additional data (varies by type) |
| `is_read` | boolean | Read status |
| `read_at` | datetime/null | When notification was read |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

### Data Field Examples

**ACC Approval:**
```json
{
  "acc_id": 3
}
```

**Subscription Payment:**
```json
{
  "subscription_id": 5,
  "amount": 10000.00
}
```

**Code Purchase:**
```json
{
  "batch_id": 10,
  "quantity": 50,
  "amount": 20000.00
}
```

**Instructor Authorization:**
```json
{
  "authorization_id": 7,
  "instructor_name": "John Doe",
  "acc_name": "ABC Accreditation Body"
}
```

---

## Usage Examples

### React Hook Example

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function useNotifications() {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);

  const fetchNotifications = async (filters = {}) => {
    setLoading(true);
    try {
      const params = new URLSearchParams(filters);
      const response = await axios.get(`/api/notifications?${params}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      setNotifications(response.data.notifications);
      setUnreadCount(response.data.unread_count);
    } catch (error) {
      console.error('Failed to fetch notifications:', error);
    } finally {
      setLoading(false);
    }
  };

  const markAsRead = async (id) => {
    try {
      await axios.put(`/api/notifications/${id}/read`, {}, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      setNotifications(notifications.map(n => 
        n.id === id ? { ...n, is_read: true, read_at: new Date() } : n
      ));
      setUnreadCount(Math.max(0, unreadCount - 1));
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  };

  const markAllAsRead = async () => {
    try {
      const response = await axios.post('/api/notifications/mark-all-read', {}, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      setNotifications(notifications.map(n => ({ ...n, is_read: true })));
      setUnreadCount(0);
    } catch (error) {
      console.error('Failed to mark all as read:', error);
    }
  };

  useEffect(() => {
    fetchNotifications();
    // Poll for new notifications every 30 seconds
    const interval = setInterval(() => {
      fetchNotifications();
    }, 30000);
    return () => clearInterval(interval);
  }, []);

  return {
    notifications,
    unreadCount,
    loading,
    fetchNotifications,
    markAsRead,
    markAllAsRead
  };
}
```

### Notification Bell Component

```jsx
import React, { useState } from 'react';
import { useNotifications } from './useNotifications';

function NotificationBell() {
  const { notifications, unreadCount, markAsRead, markAllAsRead } = useNotifications();
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="notification-bell">
      <button onClick={() => setIsOpen(!isOpen)}>
        🔔
        {unreadCount > 0 && (
          <span className="badge">{unreadCount}</span>
        )}
      </button>

      {isOpen && (
        <div className="notification-dropdown">
          <div className="header">
            <h3>Notifications</h3>
            {unreadCount > 0 && (
              <button onClick={markAllAsRead}>
                Mark all as read
              </button>
            )}
          </div>

          <div className="notifications-list">
            {notifications.length === 0 ? (
              <p>No notifications</p>
            ) : (
              notifications.map(notification => (
                <div
                  key={notification.id}
                  className={`notification-item ${!notification.is_read ? 'unread' : ''}`}
                  onClick={() => !notification.is_read && markAsRead(notification.id)}
                >
                  <div className="title">{notification.title}</div>
                  <div className="message">{notification.message}</div>
                  <div className="time">
                    {new Date(notification.created_at).toLocaleString()}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}
```

---

## Frontend Integration

### Real-time Updates

For real-time notification updates, you can:

1. **Polling:** Fetch notifications every 30-60 seconds
2. **WebSockets:** Use Laravel Echo or Pusher for real-time updates
3. **Server-Sent Events (SSE):** Stream notifications to the client

### Example: Polling Implementation

```javascript
// Poll every 30 seconds
setInterval(async () => {
  const response = await fetch('/api/notifications/unread-count', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  const { unread_count } = await response.json();
  
  // Update badge or trigger notification sound
  updateNotificationBadge(unread_count);
}, 30000);
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found
```json
{
  "message": "Notification not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "type": ["The type field is required."]
  }
}
```

---

## Best Practices

1. **Polling Frequency:** Don't poll too frequently (recommended: 30-60 seconds)
2. **Mark as Read:** Automatically mark notifications as read when user views them
3. **Cleanup:** Periodically delete old read notifications
4. **Badge Updates:** Update notification badge count in real-time
5. **Sound Alerts:** Play sound for important notifications (optional)
6. **Visual Indicators:** Show unread notifications with different styling

---

## Notification Flow Examples

### ACC Registration Flow

```
1. User registers as ACC Admin
   ↓
2. System creates ACC with status 'pending'
   ↓
3. Notification sent to all Group Admins: "New ACC Application"
   ↓
4. Group Admin reviews and approves/rejects
   ↓
5. Notification sent to ACC Admin: "ACC Approved" or "ACC Rejected"
```

### Subscription Payment Flow

```
1. ACC Admin pays subscription
   ↓
2. Payment processed successfully
   ↓
3. Notification sent to ACC Admin: "Subscription Payment Successful"
   ↓
4. Subscription dates updated
```

### Instructor Authorization Flow

```
1. Training Center requests instructor authorization
   ↓
2. Notification sent to ACC Admin: "New Instructor Authorization Request"
   ↓
3. ACC Admin approves and sets price
   ↓
4. Group Admin sets commission percentage
   ↓
5. Notification sent to Training Center: "Instructor Authorization Approved"
   ↓
6. Training Center pays authorization fee
   ↓
7. Notification sent to Training Center: "Payment Successful"
   ↓
8. Notification sent to Group Admin: "Authorization Payment Received"
```

---

## Summary

The notification system provides:
- ✅ Real-time notifications for all major actions
- ✅ Role-based notification targeting
- ✅ Read/unread status tracking
- ✅ Rich data payloads for context
- ✅ RESTful API for easy integration
- ✅ Pagination support
- ✅ Filtering capabilities

All notifications are automatically created when relevant actions occur in the system.

