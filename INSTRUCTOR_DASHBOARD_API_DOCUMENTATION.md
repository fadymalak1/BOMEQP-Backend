# Instructor Dashboard API Documentation

Complete documentation for the instructor dashboard API endpoint that provides all data needed to display the dashboard screen.

## Base URL
```
https://aeroenix.com/v1/api
```

## Authentication
Requires authentication using Laravel Sanctum with `instructor` role:
```
Authorization: Bearer {token}
```

---

## Endpoint

### Get Instructor Dashboard Data

**GET** `/api/instructor/dashboard`

Returns all data needed for the instructor dashboard including profile summary, class statistics, recent classes, earnings, training centers, ACCs, and notifications.

**Response (200):**
```json
{
  "profile": {
    "name": "Fady Malak",
    "email": "fady@example.com"
  },
  "statistics": {
    "total_classes": 10,
    "upcoming_classes": 2,
    "in_progress": 1,
    "completed": 7
  },
  "recent_classes": [
    {
      "id": 1,
      "course": {
        "id": 5,
        "name": "Advanced Fire Safety",
        "code": "AFS-001"
      },
      "training_center": {
        "id": 3,
        "name": "XYZ Training Center"
      },
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "status": "scheduled",
      "enrolled_count": 15,
      "max_capacity": 20,
      "location": "physical",
      "location_details": "Training Room A"
    }
  ],
  "earnings": {
    "total": 5000.00,
    "this_month": 1500.00,
    "pending": 500.00,
    "paid": 4500.00
  },
  "training_centers": [
    {
      "id": 3,
      "name": "XYZ Training Center",
      "email": "info@xyz.com",
      "phone": "+1234567890",
      "country": "USA",
      "city": "New York",
      "status": "active",
      "classes_count": 5
    }
  ],
  "accs": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "country": "USA",
      "status": "active",
      "is_authorized": true,
      "authorization_date": "2024-01-15T10:30:00.000000Z",
      "classes_count": 8
    }
  ],
  "unread_notifications_count": 3
}
```

**Response (200) - Empty Dashboard:**
```json
{
  "profile": {
    "name": null,
    "email": "fady@example.com"
  },
  "statistics": {
    "total_classes": 0,
    "upcoming_classes": 0,
    "in_progress": 0,
    "completed": 0
  },
  "recent_classes": [],
  "earnings": {
    "total": 0.00,
    "this_month": 0.00,
    "pending": 0.00,
    "paid": 0.00
  },
  "training_centers": [],
  "accs": [],
  "unread_notifications_count": 0
}
```

**Error Response (404):**
```json
{
  "message": "Instructor not found"
}
```

**Error Response (401):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Response Fields

### Profile Object
- `name` (string|null) - Full name of the instructor (first_name + last_name). Returns `null` if name is not set.
- `email` (string|null) - Instructor's email address.

### Statistics Object
Dashboard summary cards data:
- `total_classes` (integer) - Total number of classes assigned to the instructor.
- `upcoming_classes` (integer) - Number of scheduled classes with start_date >= today.
- `in_progress` (integer) - Number of classes currently in progress.
- `completed` (integer) - Number of completed classes.

### Recent Classes Array
Array of recent classes (latest 10, ordered by start_date and created_at):
- `id` (integer) - Class ID.
- `course` (object) - Course information:
  - `id` (integer|null) - Course ID.
  - `name` (string) - Course name.
  - `code` (string) - Course code.
- `training_center` (object) - Training center information:
  - `id` (integer|null) - Training center ID.
  - `name` (string) - Training center name.
- `start_date` (string|null) - Class start date (YYYY-MM-DD format).
- `end_date` (string|null) - Class end date (YYYY-MM-DD format).
- `status` (string) - Class status: `scheduled`, `in_progress`, `completed`, or `cancelled`.
- `enrolled_count` (integer) - Number of enrolled trainees.
- `max_capacity` (integer) - Maximum capacity of the class.
- `location` (string) - Location type: `physical` or `online`.
- `location_details` (string|null) - Additional location details.

### Earnings Object
- `total` (number) - Total earnings from all completed transactions.
- `this_month` (number) - Earnings from completed transactions this month.
- `pending` (number) - Pending earnings (transactions with status 'pending').
- `paid` (number) - Paid earnings (total - pending).

### Training Centers Array
Array of training centers the instructor has worked with:
- `id` (integer) - Training center ID.
- `name` (string) - Training center name.
- `email` (string) - Training center email.
- `phone` (string) - Training center phone.
- `country` (string) - Training center country.
- `city` (string) - Training center city.
- `status` (string) - Training center status.
- `classes_count` (integer) - Number of classes with this training center.

### ACCs Array
Array of ACCs the instructor has worked with:
- `id` (integer) - ACC ID.
- `name` (string) - ACC name.
- `email` (string) - ACC email.
- `phone` (string) - ACC phone.
- `country` (string) - ACC country.
- `status` (string) - ACC status.
- `is_authorized` (boolean) - Whether the instructor is authorized with this ACC.
- `authorization_date` (string|null) - Date when authorization was approved.
- `classes_count` (integer) - Number of classes with courses from this ACC.

### Unread Notifications Count
- `unread_notifications_count` (integer) - Number of unread notifications for the instructor.

---

## Dashboard UI Mapping

### Summary Cards
The dashboard displays four summary cards:

1. **Total Classes Card**
   - Value: `statistics.total_classes`
   - Description: "Click to view all"

2. **Upcoming Classes Card**
   - Value: `statistics.upcoming_classes`
   - Description: "Scheduled classes"

3. **In Progress Card**
   - Value: `statistics.in_progress`
   - Description: "Active now"

4. **Completed Card**
   - Value: `statistics.completed`
   - Description: "Finished classes"

### Recent Classes Section
- Header: "Recent Classes"
- Data: `recent_classes` array
- Empty state: Show when `recent_classes.length === 0`
- Display: Course name, training center, dates, status

### Profile Summary Section
- Header: "Profile Summary"
- Fields:
  - Name: `profile.name` (or "N/A" if null)
  - Email: `profile.email` (or "N/A" if null)
- Action: "View Full Profile" button (links to profile page)

---

## Usage Examples

### Example 1: Fetch Dashboard Data

```javascript
const response = await fetch('/api/instructor/dashboard', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();

// Display profile
console.log('Name:', data.profile.name || 'N/A');
console.log('Email:', data.profile.email || 'N/A');

// Display statistics
console.log('Total Classes:', data.statistics.total_classes);
console.log('Upcoming:', data.statistics.upcoming_classes);
console.log('In Progress:', data.statistics.in_progress);
console.log('Completed:', data.statistics.completed);

// Display recent classes
data.recent_classes.forEach(classItem => {
  console.log(`${classItem.course.name} - ${classItem.training_center.name}`);
});
```

### Example 2: Handle Empty Dashboard

```javascript
const response = await fetch('/api/instructor/dashboard', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const data = await response.json();

// Check if dashboard is empty
const isEmpty = data.statistics.total_classes === 0 && 
               data.recent_classes.length === 0;

if (isEmpty) {
  // Show empty state UI
  console.log('No classes found');
} else {
  // Display dashboard data
  displayDashboard(data);
}
```

### Example 3: Display Profile Summary

```javascript
const profile = data.profile;

// Display name (with fallback)
const nameDisplay = profile.name || 'N/A';
document.getElementById('instructor-name').textContent = nameDisplay;

// Display email (with fallback)
const emailDisplay = profile.email || 'N/A';
document.getElementById('instructor-email').textContent = emailDisplay;
```

---

## Testing the Endpoint

### Using cURL

```bash
curl -X GET "https://aeroenix.com/v1/api/instructor/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Using JavaScript Fetch

```javascript
fetch('https://aeroenix.com/v1/api/instructor/dashboard', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${yourToken}`,
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => {
  console.log('Dashboard Data:', data);
})
.catch(error => {
  console.error('Error:', error);
});
```

---

## Data Logic

### Class Statistics Calculation

1. **Total Classes**: Count of all `TrainingClass` records where `instructor_id` matches the instructor.

2. **Upcoming Classes**: Count of classes where:
   - `status = 'scheduled'`
   - `start_date >= today` (start of day)

3. **In Progress**: Count of classes where:
   - `status = 'in_progress'`

4. **Completed**: Count of classes where:
   - `status = 'completed'`

### Recent Classes Selection

- Orders by `start_date` (descending), then by `created_at` (descending)
- Limits to 10 most recent classes
- Includes course and training center relationships

### Profile Name Construction

- Combines `first_name` and `last_name` with a space
- Trims whitespace
- Returns `null` if the result is empty
- Frontend should display "N/A" when `name` is `null`

### Earnings Calculation

- **Total**: Sum of all transactions where `payee_type = 'instructor'`, `payee_id = instructor.id`, and `status = 'completed'`
- **This Month**: Same as total but filtered by current month and year
- **Pending**: Sum of transactions with `status = 'pending'`
- **Paid**: Calculated as `total - pending`

### Training Centers

- Includes training centers from classes assigned to the instructor
- Also includes the instructor's primary training center (if set)
- Shows count of classes per training center

### ACCs

- Includes ACCs from:
  1. Approved and paid authorizations
  2. Courses from classes assigned to the instructor
- Shows authorization status and class count per ACC

---

## Common Issues and Solutions

### Issue: Profile name shows "N/A"

**Solution:** The instructor's `first_name` and `last_name` are not set. Update the instructor profile using the profile update endpoint.

### Issue: All statistics show 0

**Solution:** The instructor hasn't been assigned any classes yet. Classes are assigned by training centers or ACCs.

### Issue: Recent classes array is empty

**Solution:** This is normal if the instructor has no classes. The array will populate once classes are assigned.

### Issue: Earnings show 0.00

**Solution:** Earnings are calculated from completed transactions. If no transactions exist or are completed, earnings will be 0.

---

## Frontend Integration Guide

### React Example

```jsx
import { useState, useEffect } from 'react';

function InstructorDashboard() {
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      const response = await fetch('/api/instructor/dashboard', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      setDashboardData(data);
    } catch (error) {
      console.error('Error fetching dashboard:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading...</div>;
  if (!dashboardData) return <div>Error loading dashboard</div>;

  return (
    <div>
      {/* Profile Summary */}
      <div>
        <h3>Profile Summary</h3>
        <p>Name: {dashboardData.profile.name || 'N/A'}</p>
        <p>Email: {dashboardData.profile.email || 'N/A'}</p>
      </div>

      {/* Statistics Cards */}
      <div className="stats-grid">
        <div className="stat-card">
          <h4>Total Classes</h4>
          <p>{dashboardData.statistics.total_classes}</p>
        </div>
        <div className="stat-card">
          <h4>Upcoming Classes</h4>
          <p>{dashboardData.statistics.upcoming_classes}</p>
        </div>
        <div className="stat-card">
          <h4>In Progress</h4>
          <p>{dashboardData.statistics.in_progress}</p>
        </div>
        <div className="stat-card">
          <h4>Completed</h4>
          <p>{dashboardData.statistics.completed}</p>
        </div>
      </div>

      {/* Recent Classes */}
      <div>
        <h3>Recent Classes</h3>
        {dashboardData.recent_classes.length === 0 ? (
          <p>No classes found</p>
        ) : (
          <ul>
            {dashboardData.recent_classes.map(classItem => (
              <li key={classItem.id}>
                {classItem.course.name} - {classItem.training_center.name}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
```

### Vue.js Example

```vue
<template>
  <div>
    <!-- Profile Summary -->
    <div>
      <h3>Profile Summary</h3>
      <p>Name: {{ profile.name || 'N/A' }}</p>
      <p>Email: {{ profile.email || 'N/A' }}</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <h4>Total Classes</h4>
        <p>{{ statistics.total_classes }}</p>
      </div>
      <div class="stat-card">
        <h4>Upcoming Classes</h4>
        <p>{{ statistics.upcoming_classes }}</p>
      </div>
      <div class="stat-card">
        <h4>In Progress</h4>
        <p>{{ statistics.in_progress }}</p>
      </div>
      <div class="stat-card">
        <h4>Completed</h4>
        <p>{{ statistics.completed }}</p>
      </div>
    </div>

    <!-- Recent Classes -->
    <div>
      <h3>Recent Classes</h3>
      <div v-if="recentClasses.length === 0">
        <p>No classes found</p>
      </div>
      <ul v-else>
        <li v-for="classItem in recentClasses" :key="classItem.id">
          {{ classItem.course.name }} - {{ classItem.training_center.name }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      profile: {},
      statistics: {},
      recentClasses: []
    };
  },
  async mounted() {
    await this.fetchDashboard();
  },
  methods: {
    async fetchDashboard() {
      try {
        const response = await fetch('/api/instructor/dashboard', {
          headers: {
            'Authorization': `Bearer ${this.token}`
          }
        });
        const data = await response.json();
        this.profile = data.profile;
        this.statistics = data.statistics;
        this.recentClasses = data.recent_classes;
      } catch (error) {
        console.error('Error fetching dashboard:', error);
      }
    }
  }
};
</script>
```

---

## Best Practices

1. **Handle Null Values**: Always check for null values in profile name and email, displaying "N/A" when appropriate.

2. **Empty States**: Show appropriate empty states when arrays are empty (e.g., "No classes found").

3. **Loading States**: Display loading indicators while fetching dashboard data.

4. **Error Handling**: Handle 401 (unauthorized) and 404 (instructor not found) errors gracefully.

5. **Refresh Data**: Consider implementing periodic refresh or refresh on focus to keep data up-to-date.

6. **Caching**: Consider caching dashboard data on the frontend to reduce API calls, but refresh when needed.

---

## Summary

✅ **Single Endpoint**: One API call returns all dashboard data  
✅ **Profile Summary**: Name and email for dashboard display  
✅ **Statistics**: Total, upcoming, in progress, and completed classes  
✅ **Recent Classes**: Latest 10 classes with full details  
✅ **Additional Data**: Earnings, training centers, ACCs, and notifications  
✅ **Null Handling**: Proper null handling for empty data  
✅ **Optimized**: Efficient queries with relationships loaded

---

**Last Updated:** December 29, 2024  
**API Version:** 1.0  
**Endpoint:** `GET /api/instructor/dashboard`

