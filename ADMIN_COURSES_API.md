# Admin Courses API Documentation

## Overview
These endpoints allow Group Admin users to view all courses in the system across all ACCs (Accreditation Bodies).

**Base URL**: `https://aeroenix.com/v1/api`

**Authentication**: All endpoints require Bearer token authentication with `group_admin` role.

---

## Endpoints

### 1. Get All Courses
**GET** `/admin/courses`

Get a paginated list of all courses in the system with optional filtering.

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Query Parameters (All Optional)
- `acc_id` (integer) - Filter courses by ACC ID
- `status` (string) - Filter by status: `active`, `inactive`, or `archived`
- `sub_category_id` (integer) - Filter by sub-category ID
- `level` (string) - Filter by level: `beginner`, `intermediate`, or `advanced`
- `search` (string) - Search in course name, Arabic name, code, or description
- `per_page` (integer) - Number of results per page (default: 15)
- `page` (integer) - Page number (default: 1)

#### Example Requests

**Get all courses:**
```javascript
GET /admin/courses
Authorization: Bearer your_token_here
```

**Filter by ACC:**
```javascript
GET /admin/courses?acc_id=1
```

**Filter by status:**
```javascript
GET /admin/courses?status=active
```

**Search courses:**
```javascript
GET /admin/courses?search=fire safety
```

**Combined filters with pagination:**
```javascript
GET /admin/courses?acc_id=1&status=active&level=beginner&per_page=20&page=1
```

#### Response (200 OK)
```json
{
  "courses": [
    {
      "id": 1,
      "sub_category_id": 1,
      "acc_id": 1,
      "name": "Fire Safety Fundamentals",
      "name_ar": "أساسيات السلامة من الحرائق",
      "code": "FSF-101",
      "description": "Comprehensive fire safety training course",
      "duration_hours": 40,
      "level": "beginner",
      "status": "active",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z",
      "acc": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "email": "info@abc.com",
        "status": "active"
      },
      "sub_category": {
        "id": 1,
        "name": "Fire Safety",
        "name_ar": "سلامة الحريق",
        "category": {
          "id": 1,
          "name": "Safety Training",
          "name_ar": "تدريب السلامة"
        }
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

---

### 2. Get Course Details
**GET** `/admin/courses/{id}`

Get detailed information about a specific course including all related data.

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Path Parameters
- `id` (integer, required) - Course ID

#### Example Request
```javascript
GET /admin/courses/1
Authorization: Bearer your_token_here
```

#### Response (200 OK)
```json
{
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Fire Safety Fundamentals",
    "name_ar": "أساسيات السلامة من الحرائق",
    "code": "FSF-101",
    "description": "Comprehensive fire safety training course covering all essential topics.",
    "duration_hours": 40,
    "level": "beginner",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "acc": {
      "id": 1,
      "name": "ABC Accreditation Body",
      "legal_name": "ABC Accreditation Body Ltd.",
      "email": "info@abc.com",
      "phone": "+1234567890",
      "status": "active"
    },
    "sub_category": {
      "id": 1,
      "name": "Fire Safety",
      "name_ar": "سلامة الحريق",
      "category": {
        "id": 1,
        "name": "Safety Training",
        "name_ar": "تدريب السلامة"
      }
    },
    "certificate_pricing": [
      {
        "id": 1,
        "acc_id": 1,
        "course_id": 1,
        "base_price": "500.00",
        "currency": "USD",
        "group_commission_percentage": "10.00",
        "training_center_commission_percentage": "15.00",
        "instructor_commission_percentage": "5.00",
        "effective_from": "2024-01-01T00:00:00.000000Z",
        "effective_to": null
      }
    ],
    "classes": [
      {
        "id": 1,
        "course_id": 1,
        "name": "Fire Safety Fundamentals - Class 1",
        "status": "active"
      }
    ],
    "certificates": [],
    "certificate_codes": [],
    "training_classes": []
  }
}
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```
**Cause**: Missing or invalid authentication token.

---

### 403 Forbidden
```json
{
  "message": "Unauthorized. Required role: group_admin"
}
```
**Cause**: User doesn't have the `group_admin` role.

---

### 404 Not Found
```json
{
  "message": "No query results for model [App\\Models\\Course] 1"
}
```
**Cause**: Course with the specified ID doesn't exist.

---

## JavaScript/TypeScript Examples

### Fetch API Example
```javascript
const baseUrl = 'https://aeroenix.com/v1/api';
const token = 'your_bearer_token_here';

// Get all courses
async function getAllCourses(filters = {}) {
  const queryParams = new URLSearchParams(filters);
  const url = `${baseUrl}/admin/courses?${queryParams}`;
  
  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  return await response.json();
}

// Get course details
async function getCourseDetails(courseId) {
  const url = `${baseUrl}/admin/courses/${courseId}`;
  
  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  return await response.json();
}

// Usage examples
const allCourses = await getAllCourses();
console.log(allCourses.courses);
console.log(allCourses.pagination);

// With filters
const filteredCourses = await getAllCourses({
  acc_id: 1,
  status: 'active',
  per_page: 20
});

// Get specific course
const courseDetails = await getCourseDetails(1);
console.log(courseDetails.course);
```

### Axios Example
```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://aeroenix.com/v1/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Add token to all requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Get all courses
export const getAllCourses = async (params = {}) => {
  const response = await api.get('/admin/courses', { params });
  return response.data;
};

// Get course details
export const getCourseDetails = async (courseId) => {
  const response = await api.get(`/admin/courses/${courseId}`);
  return response.data;
};

// Usage
const courses = await getAllCourses({ status: 'active', per_page: 20 });
const course = await getCourseDetails(1);
```

---

## Data Models

### Course Object
```typescript
interface Course {
  id: number;
  sub_category_id: number;
  acc_id: number;
  name: string;
  name_ar: string | null;
  code: string;
  description: string | null;
  duration_hours: number;
  level: 'beginner' | 'intermediate' | 'advanced';
  status: 'active' | 'inactive' | 'archived';
  created_at: string; // ISO 8601 datetime
  updated_at: string; // ISO 8601 datetime
  acc?: ACC;
  sub_category?: SubCategory;
  certificate_pricing?: CertificatePricing[];
  classes?: Class[];
  certificates?: Certificate[];
  certificate_codes?: CertificateCode[];
  training_classes?: TrainingClass[];
}
```

### Pagination Object
```typescript
interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
```

---

## Notes

1. **Authentication**: Always include the Bearer token in the Authorization header.
2. **Pagination**: Use `per_page` and `page` parameters to control pagination. Default is 15 items per page.
3. **Filtering**: Multiple filters can be combined using query parameters.
4. **Search**: The search parameter searches across name, Arabic name, code, and description fields.
5. **Date Format**: All dates are in ISO 8601 format (YYYY-MM-DDTHH:mm:ss.sssZ).
6. **Empty Arrays**: Related data arrays (certificates, classes, etc.) may be empty if no related records exist.

---

## Testing with cURL

```bash
# Get all courses
curl -X GET "https://aeroenix.com/v1/api/admin/courses" \
  -H "Authorization: Bearer your_token_here" \
  -H "Content-Type: application/json"

# Get courses with filters
curl -X GET "https://aeroenix.com/v1/api/admin/courses?acc_id=1&status=active&per_page=20" \
  -H "Authorization: Bearer your_token_here" \
  -H "Content-Type: application/json"

# Get course details
curl -X GET "https://aeroenix.com/v1/api/admin/courses/1" \
  -H "Authorization: Bearer your_token_here" \
  -H "Content-Type: application/json"
```

