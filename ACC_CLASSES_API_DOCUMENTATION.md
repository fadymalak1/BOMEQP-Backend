# ACC Classes API Documentation

Complete documentation for ACC to view classes from authorized training centers.

## Base URL
```
https://aeroenix.com/v1/api
```

## Authentication
All endpoints require authentication using Laravel Sanctum with the `acc_admin` role.

```
Authorization: Bearer {token}
```

---

## Overview

ACC admins can view all classes created by training centers that:
1. Have **approved authorization** from the ACC
2. Are for **courses that belong to the ACC**

This allows ACCs to monitor and track all training classes being conducted by their authorized training centers.

---

## Endpoints

### 1. Get All Classes

**GET** `/api/acc/classes`

Get all classes from training centers that have approved authorization from this ACC. Only shows classes for courses that belong to the ACC.

**Query Parameters:**
- `status` (string, optional) - Filter by class status: `scheduled`, `in_progress`, `completed`, `cancelled`
- `training_center_id` (integer, optional) - Filter by training center ID
- `course_id` (integer, optional) - Filter by course ID
- `date_from` (date, optional) - Filter classes starting from date (YYYY-MM-DD)
- `date_to` (date, optional) - Filter classes starting until date (YYYY-MM-DD)
- `per_page` (integer, optional) - Items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "training_center_id": 1,
      "course_id": 1,
      "class_id": 1,
      "instructor_id": 1,
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "schedule_json": {
        "monday": "09:00-17:00",
        "tuesday": "09:00-17:00",
        "wednesday": "09:00-17:00"
      },
      "max_capacity": 20,
      "enrolled_count": 15,
      "status": "scheduled",
      "location": "physical",
      "location_details": "Training Room A, Building 1",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z",
      "course": {
        "id": 1,
        "name": "Fire Safety",
        "name_ar": "السلامة من الحرائق",
        "code": "FS-001",
        "description": "Comprehensive fire safety training course",
        "duration_hours": 40,
        "level": "intermediate",
        "status": "active"
      },
      "training_center": {
        "id": 1,
        "name": "ABC Training Center",
        "legal_name": "ABC Training Center LLC",
        "email": "info@abc.com",
        "phone": "+1234567890",
        "country": "USA",
        "city": "New York",
        "status": "active"
      },
      "instructor": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "phone": "+1234567890"
      }
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 50,
  "last_page": 4,
  "from": 1,
  "to": 15
}
```

**Error Response (404):**
```json
{
  "message": "ACC not found"
}
```

---

### 2. Get Class Details

**GET** `/api/acc/classes/{id}`

Get detailed information about a specific class.

**URL Parameters:**
- `id` (integer, required) - The ID of the class

**Response (200):**
```json
{
  "id": 1,
  "training_center_id": 1,
  "course_id": 1,
  "class_id": 1,
  "instructor_id": 1,
  "start_date": "2024-02-01",
  "end_date": "2024-02-05",
  "schedule_json": {
    "monday": "09:00-17:00",
    "tuesday": "09:00-17:00",
    "wednesday": "09:00-17:00",
    "thursday": "09:00-17:00",
    "friday": "09:00-17:00"
  },
  "max_capacity": 20,
  "enrolled_count": 15,
  "status": "scheduled",
  "location": "physical",
  "location_details": "Training Room A, Building 1",
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z",
  "course": {
    "id": 1,
    "name": "Fire Safety",
    "name_ar": "السلامة من الحرائق",
    "code": "FS-001",
    "description": "Comprehensive fire safety training course",
    "duration_hours": 40,
    "level": "intermediate",
    "status": "active",
    "sub_category_id": 1
  },
  "training_center": {
    "id": 1,
    "name": "ABC Training Center",
    "legal_name": "ABC Training Center LLC",
    "registration_number": "TC-001",
    "email": "info@abc.com",
    "phone": "+1234567890",
    "website": "https://abc-training.com",
    "country": "USA",
    "city": "New York",
    "address": "123 Main St",
    "status": "active"
  },
  "instructor": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "status": "active"
  }
}
```

**Error Response (404):**
```json
{
  "message": "Class not found or not authorized"
}
```

---

## Field Descriptions

### Class Fields

- `id` (integer) - Unique class identifier
- `training_center_id` (integer) - ID of the training center conducting the class
- `course_id` (integer) - ID of the course this class is for
- `class_id` (integer) - ID of the class model/template
- `instructor_id` (integer) - ID of the instructor assigned to the class
- `start_date` (date) - Class start date (YYYY-MM-DD)
- `end_date` (date) - Class end date (YYYY-MM-DD)
- `schedule_json` (object) - Weekly schedule with days and time slots
- `max_capacity` (integer) - Maximum number of trainees allowed
- `enrolled_count` (integer) - Current number of enrolled trainees
- `status` (string) - Class status: `scheduled`, `in_progress`, `completed`, `cancelled`
- `location` (string) - Location type: `physical`, `online`, `hybrid`
- `location_details` (string) - Detailed location information
- `created_at` (datetime) - Class creation timestamp
- `updated_at` (datetime) - Last update timestamp

### Course Fields (included in response)

- `id` (integer) - Course ID
- `name` (string) - Course name in English
- `name_ar` (string) - Course name in Arabic
- `code` (string) - Course code
- `description` (string) - Course description
- `duration_hours` (integer) - Course duration in hours
- `level` (string) - Course level: `beginner`, `intermediate`, `advanced`
- `status` (string) - Course status: `active`, `inactive`

### Training Center Fields (included in response)

- `id` (integer) - Training center ID
- `name` (string) - Training center name
- `legal_name` (string) - Legal/registered name
- `email` (string) - Contact email
- `phone` (string) - Contact phone
- `country` (string) - Country
- `city` (string) - City
- `status` (string) - Training center status

### Instructor Fields (included in response)

- `id` (integer) - Instructor ID
- `first_name` (string) - Instructor first name
- `last_name` (string) - Instructor last name
- `email` (string) - Instructor email
- `phone` (string) - Instructor phone
- `status` (string) - Instructor status

---

## Usage Examples

### Example 1: Get All Classes

**Request:**
```bash
curl -X GET "https://aeroenix.com/v1/api/acc/classes" \
  -H "Authorization: Bearer {token}"
```

**Response:**
Returns paginated list of all classes from authorized training centers.

---

### Example 2: Filter Classes by Status

**Request:**
```bash
curl -X GET "https://aeroenix.com/v1/api/acc/classes?status=in_progress" \
  -H "Authorization: Bearer {token}"
```

**Response:**
Returns only classes with status `in_progress`.

---

### Example 3: Filter Classes by Training Center

**Request:**
```bash
curl -X GET "https://aeroenix.com/v1/api/acc/classes?training_center_id=1" \
  -H "Authorization: Bearer {token}"
```

**Response:**
Returns only classes from training center with ID 1.

---

### Example 4: Filter Classes by Date Range

**Request:**
```bash
curl -X GET "https://aeroenix.com/v1/api/acc/classes?date_from=2024-02-01&date_to=2024-02-28" \
  -H "Authorization: Bearer {token}"
```

**Response:**
Returns only classes starting between February 1 and February 28, 2024.

---

### Example 5: Get Class Details

**Request:**
```bash
curl -X GET "https://aeroenix.com/v1/api/acc/classes/1" \
  -H "Authorization: Bearer {token}"
```

**Response:**
Returns detailed information about class with ID 1.

---

### Example 6: Combined Filters

**Request:**
```bash
curl -X GET "https://aeroenix.com/v1/api/acc/classes?status=scheduled&course_id=1&date_from=2024-02-01&per_page=20" \
  -H "Authorization: Bearer {token}"
```

**Response:**
Returns scheduled classes for course ID 1 starting from February 1, 2024, with 20 items per page.

---

## JavaScript/TypeScript Example

```javascript
// Get all classes
async function getClasses(filters = {}) {
  const queryParams = new URLSearchParams(filters);
  const response = await fetch(`https://aeroenix.com/v1/api/acc/classes?${queryParams}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  });

  if (!response.ok) {
    throw new Error('Failed to fetch classes');
  }

  return await response.json();
}

// Usage
const classes = await getClasses({
  status: 'in_progress',
  date_from: '2024-02-01',
  per_page: 20
});

// Get specific class
async function getClass(id) {
  const response = await fetch(`https://aeroenix.com/v1/api/acc/classes/${id}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  });

  if (!response.ok) {
    throw new Error('Failed to fetch class');
  }

  return await response.json();
}

// Usage
const classDetails = await getClass(1);
```

---

## Important Notes

1. **Authorization Required**: Only classes from training centers with **approved authorization** from the ACC are visible.

2. **Course Ownership**: Only classes for courses that **belong to the ACC** are shown.

3. **Filtering**: All filters can be combined for more specific queries.

4. **Pagination**: The list endpoint returns paginated results. Use `per_page` and `page` parameters to navigate.

5. **Ordering**: Results are ordered by `start_date` in descending order (most recent first).

6. **Relationships**: The API automatically includes related data (course, training center, instructor) to reduce the need for additional API calls.

---

## Error Responses

### 404 Not Found
```json
{
  "message": "ACC not found"
}
```
Occurs when the authenticated user is not associated with an ACC.

### 404 Class Not Found
```json
{
  "message": "Class not found or not authorized"
}
```
Occurs when:
- The class ID doesn't exist
- The class belongs to a training center without approved authorization
- The class is for a course that doesn't belong to the ACC

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```
Occurs when:
- No authentication token is provided
- The token is invalid or expired

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```
Occurs when the user doesn't have the `acc_admin` role.

---

## Testing

### Using Postman

1. Set the request method to `GET`
2. Enter the endpoint URL: `https://aeroenix.com/v1/api/acc/classes`
3. In the **Headers** tab, add:
   - `Authorization`: `Bearer {your_token}`
   - `Accept`: `application/json`
4. In the **Params** tab, add any query parameters
5. Click **Send**

### Using cURL

```bash
# Get all classes
curl -X GET "https://aeroenix.com/v1/api/acc/classes" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Get classes with filters
curl -X GET "https://aeroenix.com/v1/api/acc/classes?status=in_progress&date_from=2024-02-01" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"

# Get specific class
curl -X GET "https://aeroenix.com/v1/api/acc/classes/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## Summary

This API allows ACC admins to:
- ✅ View all classes from authorized training centers
- ✅ Filter classes by status, training center, course, and date range
- ✅ Get detailed information about specific classes
- ✅ Monitor training activities across all authorized training centers
- ✅ Track class schedules, enrollment, and completion status

All classes shown are guaranteed to be from training centers with approved authorization and for courses that belong to the ACC.

