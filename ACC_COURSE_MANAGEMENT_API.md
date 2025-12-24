# ACC Course Management API Documentation

## Overview
This document describes the Course Management API endpoints for ACC (Accreditation Body) administrators. These endpoints allow ACC admins to create, read, update, and delete courses, as well as manage course pricing in a unified way.

**Base URL**: `/api/acc/courses`

**Authentication**: All endpoints require Bearer token authentication with `acc_admin` role.

---

## Table of Contents

1. [Create Course](#1-create-course)
2. [List Courses](#2-list-courses)
3. [Get Course Details](#3-get-course-details)
4. [Update Course](#4-update-course)
5. [Delete Course](#5-delete-course)

---

## 1. Create Course

**Endpoint:** `POST /api/acc/courses`

Create a new course. You can optionally include pricing information to set the course price at creation time, eliminating the need for a separate pricing endpoint.

### Request Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body

#### With Pricing (Recommended)
```json
{
  "sub_category_id": 1,
  "name": "Advanced Safety Training",
  "name_ar": "تدريب السلامة المتقدم",
  "code": "AST-101",
  "description": "Comprehensive safety training course covering all aspects of workplace safety",
  "duration_hours": 40,
  "level": "intermediate",
  "status": "active",
  "pricing": {
    "base_price": 500.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

#### Without Pricing
```json
{
  "sub_category_id": 1,
  "name": "Advanced Safety Training",
  "name_ar": "تدريب السلامة المتقدم",
  "code": "AST-101",
  "description": "Comprehensive safety training course",
  "duration_hours": 40,
  "level": "intermediate",
  "status": "active"
}
```

### Field Requirements

#### Required Course Fields
- `sub_category_id` (integer) - Sub category ID (must exist)
- `name` (string, max:255) - Course name
- `code` (string, max:255) - Unique course code
- `duration_hours` (integer, min:1) - Course duration in hours
- `level` (string) - Course level: `beginner`, `intermediate`, or `advanced`
- `status` (string) - Course status: `active`, `inactive`, or `archived`

#### Optional Course Fields
- `name_ar` (string, max:255) - Course name in Arabic
- `description` (string) - Course description

#### Optional Pricing Object
If `pricing` is included, all pricing fields are required:
- `pricing.base_price` (numeric, min:0) - Base price per certificate code
- `pricing.currency` (string, size:3) - Currency code (e.g., "USD", "EUR")
- `pricing.effective_from` (date) - When pricing becomes effective (format: YYYY-MM-DD)
- `pricing.effective_to` (date, nullable) - When pricing expires (format: YYYY-MM-DD). If null, pricing has no expiration

**Important Note:** Commission percentage is NOT set by ACC. It is automatically taken from the ACC's `commission_percentage` field, which is set by Group Admin when approving the ACC. When Training Centers purchase codes, the commission is automatically calculated using the ACC's commission percentage.

### Response (201 Created)

**With Pricing:**
```json
{
  "message": "Course created successfully with pricing",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Advanced Safety Training",
    "name_ar": "تدريب السلامة المتقدم",
    "code": "AST-101",
    "description": "Comprehensive safety training course covering all aspects of workplace safety",
    "duration_hours": 40,
    "level": "intermediate",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "current_price": {
      "base_price": "500.00",
      "currency": "USD",
      "group_commission_percentage": "15.00",
      "effective_from": "2024-01-01",
      "effective_to": "2024-12-31"
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
}
```

**Without Pricing:**
```json
{
  "message": "Course created successfully",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Advanced Safety Training",
    "name_ar": "تدريب السلامة المتقدم",
    "code": "AST-101",
    "description": "Comprehensive safety training course",
    "duration_hours": 40,
    "level": "intermediate",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "current_price": null,
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
}
```

### Error Responses

**400 Bad Request** - Validation errors
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["The code has already been taken."],
    "pricing.base_price": ["The pricing.base price must be at least 0."]
  }
}
```

**404 Not Found** - ACC not found
```json
{
  "message": "ACC not found"
}
```

---

## 2. List Courses

**Endpoint:** `GET /api/acc/courses`

Get all courses for the authenticated ACC with full details and current pricing.

### Query Parameters (All Optional)
- `sub_category_id` (integer) - Filter by sub category ID
- `status` (string) - Filter by status: `active`, `inactive`, or `archived`
- `level` (string) - Filter by level: `beginner`, `intermediate`, or `advanced`
- `search` (string) - Search in course name, Arabic name, code, or description

### Response (200 OK)
```json
{
  "courses": [
    {
      "id": 1,
      "sub_category_id": 1,
      "acc_id": 1,
      "name": "Advanced Safety Training",
      "name_ar": "تدريب السلامة المتقدم",
      "code": "AST-101",
      "description": "Comprehensive advanced safety training course covering all aspects of workplace safety",
      "duration_hours": 40,
      "level": "intermediate",
      "status": "active",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z",
      "current_price": {
        "base_price": "500.00",
        "currency": "USD",
        "group_commission_percentage": "15.00",
        "effective_from": "2024-01-01",
        "effective_to": "2024-12-31"
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
  ]
}
```

**Note:** The `current_price` field will be `null` if no active pricing is set for the course.

---

## 3. Get Course Details

**Endpoint:** `GET /api/acc/courses/{id}`

Get detailed information about a specific course.

### Response (200 OK)
```json
{
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Advanced Safety Training",
    "name_ar": "تدريب السلامة المتقدم",
    "code": "AST-101",
    "description": "Comprehensive safety training course",
    "duration_hours": 40,
    "level": "intermediate",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
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
        "base_price": "500.00",
        "currency": "USD",
        "effective_from": "2024-01-01",
        "effective_to": "2024-12-31"
      }
    ]
  }
}
```

---

## 4. Update Course

**Endpoint:** `PUT /api/acc/courses/{id}`

Update course details and/or pricing in a single request. All fields are optional - only include the fields you want to update.

### Request Body

#### Update Course Only
```json
{
  "name": "Updated Course Name",
  "description": "Updated description",
  "status": "active"
}
```

#### Update Pricing Only
```json
{
  "pricing": {
    "base_price": 550.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

#### Update Both Course and Pricing
```json
{
  "name": "Updated Course Name",
  "description": "Updated description",
  "duration_hours": 50,
  "pricing": {
    "base_price": 550.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

### Field Requirements

All fields are optional. When updating:
- **Course fields**: Use `sometimes` validation (only validate if present)
- **Pricing fields**: If `pricing` object is included, all pricing fields are required

### Response (200 OK)
```json
{
  "message": "Course updated successfully and pricing updated",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Updated Course Name",
    "name_ar": "اسم الدورة المحدث",
    "code": "AST-101",
    "description": "Updated description",
    "duration_hours": 50,
    "level": "advanced",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-12-19T15:45:00.000000Z",
    "current_price": {
      "base_price": "550.00",
      "currency": "USD",
      "group_commission_percentage": "15.00",
      "effective_from": "2024-01-01",
      "effective_to": "2024-12-31"
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
}
```

**Note:** 
- If `pricing` is provided, it will update the existing active pricing or create a new one if none exists
- The response message will indicate if pricing was updated: "Course updated successfully and pricing updated" or just "Course updated successfully"
- **Commission percentage is NOT included in pricing** - It is automatically taken from ACC's `commission_percentage` field (set by Group Admin)

---

## 5. Delete Course

**Endpoint:** `DELETE /api/acc/courses/{id}`

Delete a course. This will also delete associated pricing records.

### Response (200 OK)
```json
{
  "message": "Course deleted successfully"
}
```

### Error Responses

**404 Not Found** - Course not found or doesn't belong to ACC
```json
{
  "message": "No query results for model [App\\Models\\Course] {id}"
}
```

---

## Pricing Management Notes

### Pricing Behavior

1. **Creating Pricing:**
   - Pricing can be set when creating a course (optional)
   - If pricing is provided, it becomes the active pricing immediately
   - `effective_from` should typically be today's date or a future date

2. **Updating Pricing:**
   - When updating pricing via the update endpoint, it updates the existing active pricing
   - If no active pricing exists, a new one is created
   - The system automatically manages active pricing based on `effective_from` and `effective_to` dates

3. **Active Pricing:**
   - Active pricing is determined by:
     - `effective_from <= now()`
     - `effective_to >= now()` OR `effective_to IS NULL`
   - Only one pricing can be active at a time
   - The most recent pricing (by `effective_from`) is used if multiple match

4. **Commission Percentages:**
   - **Commission is NOT set by ACC** - It is automatically taken from the ACC's `commission_percentage` field
   - The `commission_percentage` is set by Group Admin when approving the ACC
   - When Training Centers purchase certificate codes, the commission is automatically calculated:
     - Group Admin receives: `amount * (ACC.commission_percentage / 100)`
     - ACC receives: `amount * ((100 - ACC.commission_percentage) / 100)`
   - ACC only sets the `base_price` for the course, not commission percentages

---

## Examples

### Example 1: Create Course with Pricing
```javascript
POST /api/acc/courses
{
  "sub_category_id": 1,
  "name": "Fire Safety Fundamentals",
  "code": "FSF-101",
  "duration_hours": 40,
  "level": "beginner",
  "status": "active",
  "pricing": {
    "base_price": 400.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": null
  }
}
```

### Example 2: Update Course Name Only
```javascript
PUT /api/acc/courses/1
{
  "name": "Updated Fire Safety Fundamentals"
}
```

### Example 3: Update Pricing Only
```javascript
PUT /api/acc/courses/1
{
  "pricing": {
    "base_price": 450.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

### Example 4: Update Both Course and Pricing
```javascript
PUT /api/acc/courses/1
{
  "name": "Advanced Fire Safety",
  "duration_hours": 50,
  "pricing": {
    "base_price": 500.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": null
  }
}
```

---

## Best Practices

1. **Always set pricing when creating a course** - This ensures the course is immediately available for purchase
2. **Use appropriate effective dates** - Set `effective_from` to today or a future date, and `effective_to` for time-limited pricing
3. **Validate commission percentages** - Ensure they make business sense and don't exceed 100% total
4. **Update pricing carefully** - Remember that updating pricing affects the existing active pricing
5. **Use search and filters** - When listing courses, use query parameters to filter results efficiently

---

**Last Updated:** December 19, 2025

