# Instructor API Documentation

Complete API documentation for Instructor endpoints in the BOMEQP system.

## Base URL
```
https://aeroenix.com/v1/api
```

## Authentication
All instructor endpoints require authentication using Laravel Sanctum. Include the bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

The instructor must have the `instructor` role to access these endpoints.

---

## Table of Contents

1. [Dashboard](#dashboard)
2. [Profile Management](#profile-management)
3. [Classes Management](#classes-management)
4. [Training Centers](#training-centers)
5. [ACCs](#accs)
6. [Materials](#materials)
7. [Earnings](#earnings)

---

## Dashboard

### Get Instructor Dashboard

**GET** `/api/instructor/dashboard`

Get comprehensive dashboard data including profile, statistics, classes, earnings, training centers, and ACCs.

**Response (200):**
```json
{
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "cv_url": "/api/storage/instructors/cv/cv.pdf",
    "certificates": [
      {
        "name": "Fire Safety Instructor",
        "issuer": "ABC Body",
        "expiry": "2025-12-31"
      }
    ],
    "specializations": ["Fire Safety", "First Aid"],
    "status": "active",
    "training_center": {
      "id": 1,
      "name": "ABC Training Center",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "country": "USA",
      "city": "New York"
    }
  },
  "statistics": {
    "total_classes": 15,
    "upcoming_classes": 3,
    "in_progress_classes": 2,
    "completed_classes": 10
  },
  "recent_classes": [
    {
      "id": 1,
      "course": {
        "id": 5,
        "name": "Fire Safety Training",
        "code": "FS-101"
      },
      "training_center": {
        "id": 1,
        "name": "ABC Training Center"
      },
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "status": "scheduled",
      "enrolled_count": 15,
      "max_capacity": 20,
      "location": "physical"
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
      "id": 1,
      "name": "ABC Training Center",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "country": "USA",
      "city": "New York",
      "status": "active",
      "classes_count": 8
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
      "classes_count": 12
    }
  ],
  "unread_notifications_count": 5
}
```

---

## Profile Management

### Get Instructor Profile

**GET** `/api/instructor/profile`

Get the authenticated instructor's complete profile information.

**Response (200):**
```json
{
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "cv_url": "/api/storage/instructors/cv/cv.pdf",
    "certificates": [
      {
        "name": "Fire Safety Instructor",
        "issuer": "ABC Body",
        "expiry": "2025-12-31"
      }
    ],
    "specializations": ["Fire Safety", "First Aid"],
    "status": "active",
    "training_center": {
      "id": 1,
      "name": "ABC Training Center",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "country": "USA",
      "city": "New York"
    },
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "instructor",
      "status": "active"
    }
  }
}
```

---

### Update Instructor Profile

**PUT** `/api/instructor/profile`

Update the instructor's profile information.

**Request Body (multipart/form-data or application/json):**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+1234567890",
  "cv": "(file upload - PDF, max 10MB)",
  "certificates_json": [
    {
      "name": "Fire Safety Instructor",
      "issuer": "ABC Body",
      "expiry": "2025-12-31"
    }
  ],
  "specializations": ["Fire Safety", "First Aid"]
}
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "cv_url": "/api/storage/instructors/cv/new_cv.pdf",
    "certificates": [...],
    "specializations": ["Fire Safety", "First Aid"],
    "training_center": {...}
  }
}
```

---

## Classes Management

### Get All Classes

**GET** `/api/instructor/classes`

Get all classes assigned to the instructor.

**Query Parameters:**
- `status` (string, optional) - Filter by status: `scheduled`, `in_progress`, `completed`

**Response (200):**
```json
{
  "classes": [
    {
      "id": 1,
      "course": {
        "id": 5,
        "name": "Fire Safety Training",
        "code": "FS-101",
        "description": "Comprehensive fire safety training",
        "duration_hours": 40
      },
      "training_center": {
        "id": 1,
        "name": "ABC Training Center",
        "email": "info@abc.com"
      },
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "schedule_json": {
        "monday": "09:00-17:00",
        "tuesday": "09:00-17:00"
      },
      "status": "scheduled",
      "enrolled_count": 15,
      "max_capacity": 20,
      "location": "physical",
      "location_details": "Training Room A"
    }
  ]
}
```

**Example Requests:**
```javascript
// Get all classes
GET /api/instructor/classes

// Get only scheduled classes
GET /api/instructor/classes?status=scheduled

// Get completed classes
GET /api/instructor/classes?status=completed
```

---

### Get Single Class

**GET** `/api/instructor/classes/{id}`

Get detailed information about a specific class.

**URL Parameters:**
- `id` (integer, required) - Class ID

**Response (200):**
```json
{
  "class": {
    "id": 1,
    "course": {
      "id": 5,
      "name": "Fire Safety Training",
      "code": "FS-101",
      "description": "Comprehensive fire safety training",
      "duration_hours": 40,
      "acc": {
        "id": 1,
        "name": "ABC Accreditation Body"
      }
    },
    "training_center": {
      "id": 1,
      "name": "ABC Training Center",
      "email": "info@abc.com",
      "phone": "+1234567890"
    },
    "classModel": {
      "id": 3,
      "name": "Fire Safety Level 1"
    },
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "schedule_json": {
      "monday": "09:00-17:00",
      "tuesday": "09:00-17:00"
    },
    "status": "scheduled",
    "enrolled_count": 15,
    "max_capacity": 20,
    "location": "physical",
    "location_details": "Training Room A",
    "completion": null
  }
}
```

**Response (404):**
```json
{
  "message": "No query results for model [App\\Models\\TrainingClass] {id}"
}
```

---

### Mark Class as Complete

**PUT** `/api/instructor/classes/{id}/mark-complete`

Mark a class as completed. Can only be done after the class end date has passed.

**URL Parameters:**
- `id` (integer, required) - Class ID

**Request Body:**
```json
{
  "completion_rate_percentage": 95.5,
  "notes": "All students completed successfully. Excellent participation."
}
```

**Response (200):**
```json
{
  "message": "Class marked as completed",
  "completion": {
    "id": 1,
    "training_class_id": 1,
    "completed_date": "2024-02-05T17:00:00.000000Z",
    "completion_rate_percentage": 95.5,
    "notes": "All students completed successfully. Excellent participation.",
    "marked_by": 5
  }
}
```

**Response (400):**
```json
{
  "message": "Class end date has not been reached"
}
```

---

## Training Centers

### Get Training Centers

**GET** `/api/instructor/training-centers`

Get a list of all training centers that have assigned classes to this instructor.

**Response (200):**
```json
{
  "training_centers": [
    {
      "id": 1,
      "name": "ABC Training Center",
      "legal_name": "ABC Training Center LLC",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "country": "USA",
      "city": "New York",
      "address": "123 Main St",
      "status": "active",
      "classes_count": 8,
      "completed_classes": 5,
      "upcoming_classes": 2,
      "in_progress_classes": 1
    }
  ]
}
```

**Note:** This includes:
- The instructor's own training center (where they were registered)
- All training centers that have assigned classes to this instructor

---

## ACCs

### Get ACCs

**GET** `/api/instructor/accs`

Get a list of all ACCs that have authorized this instructor or have courses assigned to this instructor.

**Response (200):**
```json
{
  "accs": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "legal_name": "ABC Accreditation Body Inc.",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "country": "USA",
      "address": "456 Business Ave",
      "website": "https://abc.com",
      "status": "active",
      "authorization": {
        "status": "approved",
        "authorization_date": "2024-01-15T10:30:00.000000Z",
        "payment_status": "paid",
        "authorization_price": 500.00
      },
      "classes_count": 12,
      "completed_classes": 8,
      "upcoming_classes": 3,
      "in_progress_classes": 1
    }
  ]
}
```

**Note:** This includes:
- ACCs that have approved and paid for instructor authorization
- ACCs whose courses have been assigned to this instructor in classes

---

## Materials

### Get Materials

**GET** `/api/instructor/materials`

Get available course materials for classes assigned to the instructor.

**Response (200):**
```json
{
  "materials": [
    {
      "id": 1,
      "name": "Fire Safety Manual",
      "material_type": "pdf",
      "file_url": "/api/storage/materials/fire_safety.pdf",
      "course": {
        "id": 5,
        "name": "Fire Safety Training"
      },
      "acc": {
        "id": 1,
        "name": "ABC Accreditation Body"
      }
    }
  ]
}
```

---

## Earnings

### Get Earnings

**GET** `/api/instructor/earnings`

Get instructor earnings and payment history.

**Query Parameters:**
- `month` (string, optional) - Filter by month (YYYY-MM format). Example: `2024-02`
- `year` (integer, optional) - Filter by year. Example: `2024`

**Response (200):**
```json
{
  "earnings": {
    "total": 5000.00,
    "this_month": 1500.00,
    "pending": 500.00,
    "paid": 4500.00
  },
  "transactions": [
    {
      "id": 1,
      "amount": 500.00,
      "currency": "USD",
      "status": "completed",
      "completed_at": "2024-01-15T10:30:00.000000Z",
      "reference_type": "class_completion",
      "reference_id": 5,
      "class": {
        "id": 5,
        "course": {
          "name": "Fire Safety Training"
        },
        "training_center": {
          "name": "ABC Training Center"
        }
      }
    }
  ],
  "summary_by_month": [
    {
      "month": "2024-01",
      "total": 2000.00,
      "paid": 1500.00,
      "pending": 500.00
    },
    {
      "month": "2024-02",
      "total": 1500.00,
      "paid": 1500.00,
      "pending": 0.00
    }
  ]
}
```

**Example Requests:**
```javascript
// Get all earnings
GET /api/instructor/earnings

// Get earnings for specific month
GET /api/instructor/earnings?month=2024-02

// Get earnings for specific year
GET /api/instructor/earnings?year=2024
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "Unauthorized. Required role: instructor"
}
```

### 404 Not Found
```json
{
  "message": "Instructor not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "first_name": ["The first name field is required."],
    "completion_rate_percentage": ["The completion rate percentage must be between 0 and 100."]
  }
}
```

---

## Data Models

### Instructor Profile
```json
{
  "id": 1,
  "first_name": "John",
  "last_name": "Doe",
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "id_number": "ID123456",
  "cv_url": "/api/storage/instructors/cv/cv.pdf",
  "certificates": [
    {
      "name": "Fire Safety Instructor",
      "issuer": "ABC Body",
      "expiry": "2025-12-31"
    }
  ],
  "specializations": ["Fire Safety", "First Aid"],
  "status": "active|pending|suspended|inactive",
  "training_center": {
    "id": 1,
    "name": "ABC Training Center"
  }
}
```

### Class Object
```json
{
  "id": 1,
  "course": {
    "id": 5,
    "name": "Fire Safety Training",
    "code": "FS-101"
  },
  "training_center": {
    "id": 1,
    "name": "ABC Training Center"
  },
  "start_date": "2024-02-01",
  "end_date": "2024-02-05",
  "schedule_json": {
    "monday": "09:00-17:00",
    "tuesday": "09:00-17:00"
  },
  "status": "scheduled|in_progress|completed|cancelled",
  "enrolled_count": 15,
  "max_capacity": 20,
  "location": "physical|online",
  "location_details": "Training Room A"
}
```

### Training Center Object
```json
{
  "id": 1,
  "name": "ABC Training Center",
  "legal_name": "ABC Training Center LLC",
  "email": "info@abc.com",
  "phone": "+1234567890",
  "country": "USA",
  "city": "New York",
  "address": "123 Main St",
  "status": "active|pending|suspended|inactive",
  "classes_count": 8,
  "completed_classes": 5,
  "upcoming_classes": 2,
  "in_progress_classes": 1
}
```

### ACC Object
```json
{
  "id": 1,
  "name": "ABC Accreditation Body",
  "legal_name": "ABC Accreditation Body Inc.",
  "email": "info@abc.com",
  "phone": "+1234567890",
  "country": "USA",
  "address": "456 Business Ave",
  "website": "https://abc.com",
  "status": "active|pending|suspended|expired",
  "is_authorized": true,
  "authorization_date": "2024-01-15T10:30:00.000000Z",
  "authorization": {
    "status": "approved",
    "authorization_date": "2024-01-15T10:30:00.000000Z",
    "payment_status": "paid",
    "authorization_price": 500.00
  },
  "classes_count": 12,
  "completed_classes": 8,
  "upcoming_classes": 3,
  "in_progress_classes": 1
}
```

---

## Usage Examples

### React Hook Example

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function useInstructorDashboard() {
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(false);

  const fetchDashboard = async () => {
    setLoading(true);
    try {
      const response = await axios.get('/api/instructor/dashboard', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      setDashboard(response.data);
    } catch (error) {
      console.error('Failed to fetch dashboard:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  return { dashboard, loading, refetch: fetchDashboard };
}
```

### Update Profile Example

```jsx
const updateProfile = async (profileData) => {
  const formData = new FormData();
  formData.append('first_name', profileData.firstName);
  formData.append('last_name', profileData.lastName);
  formData.append('phone', profileData.phone);
  
  if (profileData.cvFile) {
    formData.append('cv', profileData.cvFile);
  }
  
  formData.append('specializations', JSON.stringify(profileData.specializations));

  const response = await axios.put('/api/instructor/profile', formData, {
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('token')}`,
      'Content-Type': 'multipart/form-data'
    }
  });
  
  return response.data;
};
```

---

## Summary

The Instructor API provides:

- ✅ **Complete Dashboard** - All data in one endpoint
- ✅ **Profile Management** - View and update instructor profile
- ✅ **Classes Management** - View and manage assigned classes
- ✅ **Training Centers** - View all training centers worked with
- ✅ **ACCs** - View all ACCs authorized with
- ✅ **Materials** - Access course materials
- ✅ **Earnings** - Track earnings and payments

All endpoints are secured with authentication and role-based access control.

---

**Last Updated:** December 28, 2024  
**API Version:** 1.0

