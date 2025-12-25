# Notifications Server-Sent Events (SSE) Guide

## Overview

The notification system uses Server-Sent Events (SSE) to stream notifications in real-time to clients. This eliminates the need for frequent API polling and provides instant notification delivery.

---

## Why SSE?

- ✅ **Reduces API Calls:** No need to poll every 30-60 seconds
- ✅ **Real-time Updates:** Instant notification delivery
- ✅ **Lower Bandwidth:** Only sends new data when available
- ✅ **Automatic Reconnection:** Built-in reconnection handling
- ✅ **Simple Implementation:** Native browser support (EventSource API)

---

## Endpoints

### 1. Stream All Notifications

**GET** `/api/notifications/stream`

**Query Parameters:**
- `last_id` (integer, optional) - Last notification ID received. Only notifications with ID > last_id will be sent.

**Example:**
```
GET /api/notifications/stream?last_id=0
```

### 2. Stream Unread Count Only

**GET** `/api/notifications/stream/unread-count`

Streams only the unread count, updates when count changes.

**Example:**
```
GET /api/notifications/stream/unread-count
```

---

## Implementation

### JavaScript (Browser)

#### Basic Implementation

```javascript
// Connect to notification stream
const eventSource = new EventSource('/api/notifications/stream?last_id=0');

eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);
  
  if (data.type === 'notification') {
    console.log('New notification:', data.notification);
    // Handle notification
    displayNotification(data.notification);
  } else if (data.type === 'connected') {
    console.log('Connected to stream');
  }
};

eventSource.onerror = (error) => {
  console.error('Stream error:', error);
  // EventSource will automatically attempt to reconnect
};
```

**Problem:** EventSource doesn't support custom headers (like Authorization header).

#### Solution 1: Token in Query Parameter (Simple but less secure)

**Backend:** Modify the route to accept token from query parameter and authenticate:

```php
// In NotificationController
public function stream(Request $request)
{
    // Support token from query parameter for SSE
    if ($request->has('token')) {
        $user = \Laravel\Sanctum\PersonalAccessToken::findToken($request->token)?->tokenable;
        if (!$user) {
            abort(401);
        }
    } else {
        $user = $request->user();
    }
    
    // ... rest of the code
}
```

**Frontend:**
```javascript
const token = localStorage.getItem('token');
const eventSource = new EventSource(`/api/notifications/stream?last_id=0&token=${token}`);
```

#### Solution 2: Use eventsource Library (Recommended)

Install the library:
```bash
npm install eventsource
```

```javascript
import EventSource from 'eventsource';

const token = localStorage.getItem('token');
const eventSource = new EventSource('/api/notifications/stream?last_id=0', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);
  handleNotification(data);
};
```

#### Solution 3: Use Fetch API with ReadableStream

```javascript
async function connectNotificationStream(token, lastId = 0) {
  const response = await fetch(`/api/notifications/stream?last_id=${lastId}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'text/event-stream'
    }
  });

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  while (true) {
    const { done, value } = await reader.read();
    
    if (done) {
      console.log('Stream ended');
      // Reconnect after delay
      setTimeout(() => connectNotificationStream(token, lastId), 5000);
      break;
    }

    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split('\n');
    buffer = lines.pop() || '';

    for (const line of lines) {
      if (line.startsWith('data: ')) {
        try {
          const data = JSON.parse(line.substring(6));
          handleNotificationData(data, (id) => {
            if (id > lastId) lastId = id;
          });
        } catch (e) {
          console.error('Parse error:', e);
        }
      } else if (line.startsWith(':')) {
        // Heartbeat or comment, ignore
      }
    }
  }
}

function handleNotificationData(data, updateLastId) {
  if (data.type === 'notification') {
    const notification = data.notification;
    displayNotification(notification);
    updateNotificationBadge();
    updateLastId(notification.id);
  } else if (data.type === 'connected') {
    console.log('Connected to notification stream');
  } else if (data.type === 'timeout') {
    console.log('Connection timeout');
  }
}

// Start connection
const token = localStorage.getItem('token');
connectNotificationStream(token);
```

---

## React Implementation

### Custom Hook

```jsx
import { useEffect, useState, useRef, useCallback } from 'react';

function useNotificationStream(token) {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isConnected, setIsConnected] = useState(false);
  const eventSourceRef = useRef(null);
  const lastIdRef = useRef(0);
  const reconnectTimeoutRef = useRef(null);

  const connect = useCallback(() => {
    if (!token || eventSourceRef.current?.readyState === EventSource.OPEN) {
      return;
    }

    // Close existing connection
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
    }

    // Using eventsource library that supports headers
    import('eventsource').then(({ default: EventSource }) => {
      const eventSource = new EventSource(
        `/api/notifications/stream?last_id=${lastIdRef.current}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        }
      );

      eventSourceRef.current = eventSource;

      eventSource.onopen = () => {
        setIsConnected(true);
        console.log('Connected to notification stream');
        if (reconnectTimeoutRef.current) {
          clearTimeout(reconnectTimeoutRef.current);
          reconnectTimeoutRef.current = null;
        }
      };

      eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);

          if (data.type === 'notification') {
            const notification = data.notification;
            setNotifications(prev => [notification, ...prev]);
            
            if (!notification.is_read) {
              setUnreadCount(prev => prev + 1);
            }

            if (notification.id > lastIdRef.current) {
              lastIdRef.current = notification.id;
            }
          } else if (data.type === 'connected') {
            setIsConnected(true);
          } else if (data.type === 'timeout') {
            console.log('Connection timeout, reconnecting...');
            eventSource.close();
            reconnectTimeoutRef.current = setTimeout(connect, 1000);
          }
        } catch (error) {
          console.error('Error parsing SSE data:', error);
        }
      };

      eventSource.onerror = (error) => {
        console.error('SSE error:', error);
        setIsConnected(false);
        
        // Only reconnect if connection was closed
        if (eventSource.readyState === EventSource.CLOSED) {
          reconnectTimeoutRef.current = setTimeout(connect, 5000);
        }
      };
    });
  }, [token]);

  useEffect(() => {
    connect();

    return () => {
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
        eventSourceRef.current = null;
      }
      if (reconnectTimeoutRef.current) {
        clearTimeout(reconnectTimeoutRef.current);
      }
    };
  }, [connect]);

  const markAsRead = useCallback(async (notificationId) => {
    try {
      const response = await fetch(`/api/notifications/${notificationId}/read`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        setNotifications(prev =>
          prev.map(n =>
            n.id === notificationId ? { ...n, is_read: true } : n
          )
        );
        setUnreadCount(prev => Math.max(0, prev - 1));
      }
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  }, [token]);

  return {
    notifications,
    unreadCount,
    isConnected,
    markAsRead,
    reconnect: connect
  };
}
```

### Usage in Component

```jsx
import React from 'react';
import { useNotificationStream } from './useNotificationStream';

function NotificationCenter() {
  const token = localStorage.getItem('token');
  const { notifications, unreadCount, isConnected, markAsRead } = useNotificationStream(token);

  return (
    <div>
      <div className="status">
        Status: {isConnected ? '🟢 Connected' : '🔴 Disconnected'}
      </div>
      <div className="badge">
        Unread: {unreadCount}
      </div>
      <div className="notifications">
        {notifications.map(notification => (
          <div
            key={notification.id}
            className={`notification ${notification.is_read ? 'read' : 'unread'}`}
            onClick={() => !notification.is_read && markAsRead(notification.id)}
          >
            <h4>{notification.title}</h4>
            <p>{notification.message}</p>
            <span>{new Date(notification.created_at).toLocaleString()}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
```

---

## Vue.js Implementation

```vue
<template>
  <div>
    <div>Status: {{ isConnected ? 'Connected' : 'Disconnected' }}</div>
    <div>Unread: {{ unreadCount }}</div>
    <div v-for="notification in notifications" :key="notification.id">
      <h4>{{ notification.title }}</h4>
      <p>{{ notification.message }}</p>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import EventSource from 'eventsource';

export default {
  setup() {
    const notifications = ref([]);
    const unreadCount = ref(0);
    const isConnected = ref(false);
    let eventSource = null;
    let lastId = 0;

    const connect = () => {
      const token = localStorage.getItem('token');
      
      eventSource = new EventSource(
        `/api/notifications/stream?last_id=${lastId}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        }
      );

      eventSource.onopen = () => {
        isConnected.value = true;
      };

      eventSource.onmessage = (event) => {
        const data = JSON.parse(event.data);
        
        if (data.type === 'notification') {
          notifications.value.unshift(data.notification);
          if (!data.notification.is_read) {
            unreadCount.value++;
          }
          if (data.notification.id > lastId) {
            lastId = data.notification.id;
          }
        }
      };

      eventSource.onerror = () => {
        isConnected.value = false;
        setTimeout(connect, 5000);
      };
    };

    onMounted(() => {
      connect();
    });

    onUnmounted(() => {
      if (eventSource) {
        eventSource.close();
      }
    });

    return {
      notifications,
      unreadCount,
      isConnected
    };
  }
};
</script>
```

---

## Connection Management

### Timeout Handling

The server closes connections after 5 minutes. Your client should handle reconnection:

```javascript
eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);
  
  if (data.type === 'timeout') {
    console.log('Connection timeout, reconnecting...');
    eventSource.close();
    setTimeout(() => {
      connectToStream();
    }, 1000);
  }
};
```

### Heartbeat

The server sends heartbeat messages (`: heartbeat`) every 30 seconds to keep the connection alive. These can be ignored.

### Last ID Tracking

Always track the last notification ID received and pass it when reconnecting:

```javascript
let lastNotificationId = 0;

eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);
  
  if (data.type === 'notification' && data.notification.id > lastNotificationId) {
    lastNotificationId = data.notification.id;
    // Store in localStorage for persistence
    localStorage.setItem('lastNotificationId', lastNotificationId);
  }
};

// On reconnect
const lastId = localStorage.getItem('lastNotificationId') || 0;
const eventSource = new EventSource(`/api/notifications/stream?last_id=${lastId}`);
```

---

## Best Practices

1. **Handle Reconnection:** Always implement reconnection logic
2. **Track Last ID:** Pass last_id to avoid receiving duplicate notifications
3. **Error Handling:** Handle connection errors gracefully
4. **Connection Status:** Show connection status to users
5. **Fallback:** Provide polling as fallback if SSE fails
6. **Token Security:** Use secure methods to pass authentication token
7. **Cleanup:** Always close connections when component unmounts

---

## Troubleshooting

### Connection Immediately Closes

- Check if authentication token is valid
- Verify CORS settings allow SSE connections
- Check server logs for errors

### Not Receiving Notifications

- Verify `last_id` parameter is correct
- Check if there are actually new notifications
- Verify connection is open (check `readyState`)

### High Server Load

- Reduce polling frequency (currently 2 seconds)
- Increase heartbeat interval
- Reduce connection timeout

---

## Comparison: SSE vs Polling

| Feature | SSE | Polling |
|---------|-----|---------|
| Real-time | ✅ Instant | ❌ Delayed (poll interval) |
| Server Load | ✅ Low (push-based) | ❌ High (constant requests) |
| Bandwidth | ✅ Efficient | ❌ Wastes bandwidth |
| Complexity | ⚠️ Medium | ✅ Simple |
| Browser Support | ✅ Modern browsers | ✅ All browsers |

**Recommendation:** Use SSE for real-time notifications. Use polling only as a fallback for older browsers.

