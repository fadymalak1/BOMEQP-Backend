# Course Create and Update API Documentation

Complete documentation for creating and updating courses with pricing support.

## Base URL
```
https://aeroenix.com/v1/api
```

## Authentication
All endpoints require authentication using Laravel Sanctum with `acc_admin` role:
```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. Create Course

**POST** `/api/acc/courses`

Create a new course with optional pricing. You can set the base price and commission percentages when creating the course.

**Request Body:**

**Required Fields:**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Fire Safety",
  "code": "AFS-001",
  "duration_hours": 40,
  "level": "advanced",
  "status": "active"
}
```

**With Optional Fields and Pricing:**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Fire Safety",
  "name_ar": "السلامة من الحرائق المتقدمة",
  "code": "AFS-001",
  "description": "Advanced fire safety training course covering all aspects of fire prevention and safety protocols.",
  "duration_hours": 40,
  "level": "advanced",
  "status": "active",
  "pricing": {
    "base_price": 500.00,
    "currency": "USD",
    "group_commission_percentage": 10.0,
    "training_center_commission_percentage": 5.0,
    "instructor_commission_percentage": 3.0,
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

**Field Descriptions:**

**Course Fields:**
- `sub_category_id` (integer, required) - ID of the sub-category this course belongs to
- `name` (string, required, max:255) - Course name in English
- `name_ar` (string, optional, max:255) - Course name in Arabic
- `code` (string, required, max:255, unique) - Unique course code (e.g., "AFS-001")
- `description` (string, optional) - Detailed course description
- `duration_hours` (integer, required, min:1) - Course duration in hours
- `level` (string, required) - Course level: `beginner`, `intermediate`, or `advanced`
- `status` (string, required) - Course status: `active`, `inactive`, or `archived`

**Pricing Fields (Optional):**
- `pricing` (object, optional) - Pricing information object
  - `base_price` (number, required if pricing provided, min:0) - Base price for the course certificate
  - `currency` (string, required if pricing provided, size:3) - Currency code (e.g., "USD", "EUR")
  - `group_commission_percentage` (number, required if pricing provided, 0-100) - Group admin commission percentage
  - `training_center_commission_percentage` (number, required if pricing provided, 0-100) - Training center commission percentage
  - `instructor_commission_percentage` (number, required if pricing provided, 0-100) - Instructor commission percentage
  - `effective_from` (date, required if pricing provided) - Date from which this pricing is effective (YYYY-MM-DD)
  - `effective_to` (date, optional) - Date until which this pricing is effective (YYYY-MM-DD). Must be after `effective_from`

**Validation Rules:**
- Course code must be unique across all courses
- Total commission percentages (group + training_center + instructor) cannot exceed 100%
- `effective_to` must be after `effective_from` if provided
- All commission percentages must be between 0 and 100

**Response (201):**
```json
{
  "message": "Course created successfully with pricing",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Advanced Fire Safety",
    "name_ar": "السلامة من الحرائق المتقدمة",
    "code": "AFS-001",
    "description": "Advanced fire safety training course covering all aspects of fire prevention and safety protocols.",
    "duration_hours": 40,
    "level": "advanced",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "sub_category": {
      "id": 1,
      "name": "Fire Safety",
      "category": {
        "id": 1,
        "name": "Safety"
      }
    },
    "current_price": {
      "base_price": 500.00,
      "currency": "USD",
      "group_commission_percentage": 10.0,
      "training_center_commission_percentage": 5.0,
      "instructor_commission_percentage": 3.0,
      "effective_from": "2024-01-01",
      "effective_to": "2024-12-31"
    }
  }
}
```

**Response (201) - Without Pricing:**
```json
{
  "message": "Course created successfully",
  "course": {
    "id": 1,
    "name": "Advanced Fire Safety",
    "code": "AFS-001",
    "current_price": null
  }
}
```

**Error Response (422) - Validation Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["The code has already been taken."],
    "sub_category_id": ["The selected sub category id is invalid."]
  }
}
```

**Error Response (422) - Commission Exceeds 100%:**
```json
{
  "message": "Total commission percentages cannot exceed 100%",
  "errors": {
    "pricing.commission_percentages": [
      "The sum of all commission percentages is 120% which exceeds 100%"
    ]
  }
}
```

**Error Response (404):**
```json
{
  "message": "ACC not found"
}
```

---

### 2. Update Course

**PUT** `/api/acc/courses/{id}`

Update course details and optionally update or set pricing. All fields are optional - only provide fields you want to update.

**URL Parameters:**
- `id` (integer, required) - Course ID

**Request Body (All Fields Optional):**

**Update Course Only:**
```json
{
  "name": "Advanced Fire Safety - Updated",
  "description": "Updated course description",
  "status": "inactive"
}
```

**Update Course with Pricing:**
```json
{
  "name": "Advanced Fire Safety - Updated",
  "pricing": {
    "base_price": 550.00,
    "currency": "USD",
    "group_commission_percentage": 12.0,
    "training_center_commission_percentage": 5.0,
    "instructor_commission_percentage": 3.0,
    "effective_from": "2024-01-01",
    "effective_to": null
  }
}
```

**Field Descriptions:**

All fields are the same as in the create endpoint, but all are optional:
- `sub_category_id` (integer, optional)
- `name` (string, optional, max:255)
- `name_ar` (string, optional, max:255)
- `code` (string, optional, max:255, unique except current course)
- `description` (string, optional)
- `duration_hours` (integer, optional, min:1)
- `level` (string, optional) - `beginner`, `intermediate`, or `advanced`
- `status` (string, optional) - `active`, `inactive`, or `archived`
- `pricing` (object, optional) - Same structure as create endpoint

**How Pricing Update Works:**
1. If there's an active pricing with the same `effective_from` date, it will be updated
2. If there's an active pricing with a different `effective_from` date, the old pricing will be ended and a new one created
3. If no active pricing exists, a new pricing record will be created

**Response (200):**
```json
{
  "message": "Course updated successfully and pricing updated",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Advanced Fire Safety - Updated",
    "name_ar": "السلامة من الحرائق المتقدمة",
    "code": "AFS-001",
    "description": "Updated course description",
    "duration_hours": 40,
    "level": "advanced",
    "status": "inactive",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T11:00:00.000000Z",
    "sub_category": {
      "id": 1,
      "name": "Fire Safety",
      "category": {
        "id": 1,
        "name": "Safety"
      }
    },
    "current_price": {
      "base_price": 550.00,
      "currency": "USD",
      "group_commission_percentage": 12.0,
      "training_center_commission_percentage": 5.0,
      "instructor_commission_percentage": 3.0,
      "effective_from": "2024-01-01",
      "effective_to": null
    }
  }
}
```

**Response (200) - Course Only Update:**
```json
{
  "message": "Course updated successfully",
  "course": {
    "id": 1,
    "name": "Advanced Fire Safety - Updated",
    "current_price": {
      "base_price": 500.00,
      "currency": "USD",
      "group_commission_percentage": 10.0,
      "training_center_commission_percentage": 5.0,
      "instructor_commission_percentage": 3.0,
      "effective_from": "2024-01-01",
      "effective_to": null
    }
  }
}
```

**Error Response (404):**
```json
{
  "message": "No query results for model [App\\Models\\Course] {id}"
}
```

**Error Response (422) - Commission Exceeds 100%:**
```json
{
  "message": "Total commission percentages cannot exceed 100%",
  "errors": {
    "pricing.commission_percentages": [
      "The sum of all commission percentages is 110% which exceeds 100%"
    ]
  }
}
```

---

## Usage Examples

### Example 1: Create Course Without Pricing

```javascript
const response = await fetch('/api/acc/courses', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    sub_category_id: 1,
    name: 'Basic First Aid',
    code: 'BFA-001',
    duration_hours: 8,
    level: 'beginner',
    status: 'active'
  })
});

const data = await response.json();
console.log(data.message); // "Course created successfully"
```

### Example 2: Create Course With Pricing

```javascript
const response = await fetch('/api/acc/courses', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    sub_category_id: 1,
    name: 'Advanced Fire Safety',
    name_ar: 'السلامة من الحرائق المتقدمة',
    code: 'AFS-001',
    description: 'Comprehensive fire safety training',
    duration_hours: 40,
    level: 'advanced',
    status: 'active',
    pricing: {
      base_price: 500.00,
      currency: 'USD',
      group_commission_percentage: 10.0,
      training_center_commission_percentage: 5.0,
      instructor_commission_percentage: 3.0,
      effective_from: '2024-01-01'
    }
  })
});

const data = await response.json();
console.log(data.message); // "Course created successfully with pricing"
```

### Example 3: Update Course Name Only

```javascript
const response = await fetch('/api/acc/courses/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    name: 'Advanced Fire Safety - Updated'
  })
});

const data = await response.json();
console.log(data.message); // "Course updated successfully"
```

### Example 4: Update Course with New Pricing

```javascript
const response = await fetch('/api/acc/courses/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    name: 'Advanced Fire Safety - Updated',
    pricing: {
      base_price: 550.00,
      currency: 'USD',
      group_commission_percentage: 12.0,
      training_center_commission_percentage: 5.0,
      instructor_commission_percentage: 3.0,
      effective_from: '2024-01-01'
    }
  })
});

const data = await response.json();
console.log(data.message); // "Course updated successfully and pricing updated"
```

### Example 5: Update Multiple Course Fields

```javascript
const response = await fetch('/api/acc/courses/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    name: 'Advanced Fire Safety - Updated',
    description: 'Updated comprehensive fire safety training',
    duration_hours: 45,
    level: 'advanced',
    status: 'active'
  })
});
```

---

## Testing the Endpoints

### Test Create Course

```bash
curl -X POST "https://aeroenix.com/v1/api/acc/courses" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "sub_category_id": 1,
    "name": "Advanced Fire Safety",
    "code": "AFS-001",
    "duration_hours": 40,
    "level": "advanced",
    "status": "active",
    "pricing": {
      "base_price": 500.00,
      "currency": "USD",
      "group_commission_percentage": 10.0,
      "training_center_commission_percentage": 5.0,
      "instructor_commission_percentage": 3.0,
      "effective_from": "2024-01-01"
    }
  }'
```

### Test Update Course

```bash
curl -X PUT "https://aeroenix.com/v1/api/acc/courses/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Advanced Fire Safety - Updated",
    "pricing": {
      "base_price": 550.00,
      "currency": "USD",
      "group_commission_percentage": 12.0,
      "training_center_commission_percentage": 5.0,
      "instructor_commission_percentage": 3.0,
      "effective_from": "2024-01-01"
    }
  }'
```

---

## Common Issues and Solutions

### Issue: "The code has already been taken"

**Solution:** Use a unique course code. Each course must have a unique code.

```json
// ❌ Invalid - code already exists
{
  "code": "AFS-001"  // Already used
}

// ✅ Valid - use a different code
{
  "code": "AFS-002"
}
```

### Issue: "Total commission percentages cannot exceed 100%"

**Solution:** Ensure the sum of all three commission percentages is 100% or less.

```json
// ❌ Invalid
{
  "pricing": {
    "group_commission_percentage": 50,
    "training_center_commission_percentage": 40,
    "instructor_commission_percentage": 30  // Total: 120%
  }
}

// ✅ Valid
{
  "pricing": {
    "group_commission_percentage": 50,
    "training_center_commission_percentage": 30,
    "instructor_commission_percentage": 20  // Total: 100%
  }
}
```

### Issue: "effective_to must be after effective_from"

**Solution:** Ensure the `effective_to` date is after the `effective_from` date.

```json
// ❌ Invalid
{
  "pricing": {
    "effective_from": "2024-12-31",
    "effective_to": "2024-01-01"
  }
}

// ✅ Valid
{
  "pricing": {
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

### Issue: "The selected sub category id is invalid"

**Solution:** Make sure the `sub_category_id` exists in the database and is assigned to your ACC.

---

## Pricing Behavior

### Creating Course with Pricing

When you create a course with pricing:
1. The course is created first
2. A new pricing record is created and linked to the course
3. The pricing becomes active immediately if `effective_from` is today or in the past

### Updating Course with Pricing

When you update a course with pricing:
1. **If active pricing exists with same `effective_from` date:**
   - The existing pricing record is updated with new values
   
2. **If active pricing exists with different `effective_from` date:**
   - The old pricing's `effective_to` is set to one day before the new pricing starts
   - A new pricing record is created
   
3. **If no active pricing exists:**
   - A new pricing record is created

**Example Scenario:**
- Existing pricing: `effective_from: 2024-01-01`, `effective_to: null` (active)
- Update with: `effective_from: 2024-06-01`
- Result: 
  - Old pricing gets `effective_to: 2024-05-31`
  - New pricing is created with `effective_from: 2024-06-01`

---

## Data Model

### Course Model

```json
{
  "id": 1,
  "sub_category_id": 1,
  "acc_id": 1,
  "name": "Advanced Fire Safety",
  "name_ar": "السلامة من الحرائق المتقدمة",
  "code": "AFS-001",
  "description": "Advanced fire safety training course",
  "duration_hours": 40,
  "level": "advanced",
  "status": "active",
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z"
}
```

### Pricing Model

```json
{
  "id": 1,
  "acc_id": 1,
  "course_id": 1,
  "base_price": 500.00,
  "currency": "USD",
  "group_commission_percentage": 10.00,
  "training_center_commission_percentage": 5.00,
  "instructor_commission_percentage": 3.00,
  "effective_from": "2024-01-01",
  "effective_to": "2024-12-31",
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z"
}
```

---

## Best Practices

1. **Set Pricing on Creation**: If you know the pricing, include it when creating the course to avoid an extra API call
2. **Use Unique Codes**: Always use unique, descriptive course codes
3. **Validate Commissions**: Always ensure commission percentages total 100% or less
4. **Use Effective Dates**: Use `effective_from` and `effective_to` to manage price changes over time
5. **Partial Updates**: When updating, only send fields you want to change
6. **Check Active Pricing**: Before updating pricing, check if there's already an active one to understand the behavior

---

## Summary

✅ **Create Course (POST)**: Creates new course with optional pricing  
✅ **Update Course (PUT)**: Updates course details and/or pricing  
✅ **Pricing Support**: Full pricing with commission percentages in both create and update  
✅ **Validation**: Ensures commission percentages don't exceed 100%  
✅ **Date Management**: Handles effective dates and automatically manages pricing transitions  
✅ **Error Handling**: Provides clear error messages for validation failures  
✅ **Flexible Updates**: Update any combination of course fields and pricing

---

**Last Updated:** December 28, 2024  
**API Version:** 1.0

