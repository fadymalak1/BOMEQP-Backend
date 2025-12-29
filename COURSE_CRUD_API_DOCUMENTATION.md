# Course Create, Update, and View API Documentation

Complete documentation for creating, updating, and viewing courses with optional pricing support. Pricing is completely optional - you can create and manage courses without setting any pricing information.

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

## Important Notes

### Pricing is Completely Optional

- **You can create courses without any pricing information**
- **You can add pricing later if needed**
- **You can update courses without touching pricing**
- **Pricing is not required for course creation or management**

The endpoints support optional pricing, but courses work perfectly fine without it. Only include pricing when you want to set the base price for the course certificate.

---

## Endpoints Overview

1. **Create Course** - Create a new course (with or without pricing)
2. **Update Course** - Update course details and/or pricing
3. **View Course** - Get course details including pricing (if set)

---

## Endpoints

### 1. Create Course

**Note:** Pricing is completely optional. You can create a course without any pricing information and add it later if needed.

**POST** `/api/acc/courses`

Create a new course. Pricing is completely optional - you can create a course without pricing and add it later. If you want to set pricing, you can include the base price when creating the course. Commission percentages are managed by Group Admins separately and are not part of this endpoint.

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
    "currency": "USD"
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

**Note:** 
- Commission percentages are not set by ACC admins. They are managed by Group Admins separately.
- Pricing is always effective immediately when set. There are no date restrictions.

**Validation Rules:**
- Course code must be unique across all courses

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
        "currency": "USD"
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
    "currency": "USD"
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
1. If pricing exists for the course, it will be updated with the new values
2. If no pricing exists, a new pricing record will be created
3. Pricing is always effective immediately - no date restrictions

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
        "currency": "USD"
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
        "currency": "USD"
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


---

### 3. View Course

**GET** `/api/acc/courses/{id}`

Get detailed information about a specific course, including its current pricing (if set).

**URL Parameters:**
- `id` (integer, required) - Course ID

**Response (200):**
```json
{
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
    "certificate_pricing": [
      {
        "id": 1,
        "base_price": 500.00,
        "currency": "USD",
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ]
  }
}
```

**Response (200) - Course Without Pricing:**
```json
{
  "course": {
    "id": 1,
    "name": "Advanced Fire Safety",
    "code": "AFS-001",
    "status": "active",
    "certificate_pricing": []
  }
}
```

**Error Response (404):**
```json
{
  "message": "No query results for model [App\\Models\\Course] {id}"
}
```

---

## Usage Examples

### Example 1: Create Course Without Pricing (Recommended First Step)

**Note:** You can always add pricing later. It's perfectly fine to create a course without pricing.

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

### Example 2: Create Course With Optional Pricing

**Note:** Pricing is optional. Only include it if you want to set the base price immediately.

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
      currency: 'USD'
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
      currency: 'USD'
    }
  })
});

const data = await response.json();
console.log(data.message); // "Course updated successfully and pricing updated"
```

### Example 5: View Course Details

```javascript
const response = await fetch('/api/acc/courses/1', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const data = await response.json();
console.log('Course:', data.course.name);
console.log('Current Price:', data.course.certificate_pricing);
```

### Example 6: Update Multiple Course Fields

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
        "currency": "USD"
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
        "currency": "USD"
      }
  }'
```

### Test View Course

```bash
curl -X GET "https://aeroenix.com/v1/api/acc/courses/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
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



### Issue: "The selected sub category id is invalid"

**Solution:** Make sure the `sub_category_id` exists in the database and is assigned to your ACC.

---

## Pricing Behavior

### Creating Course with Pricing

When you create a course with pricing:
1. The course is created first
2. A new pricing record is created and linked to the course
3. The pricing is **always effective immediately** - no date restrictions

### Updating Course with Pricing

When you update a course with pricing:
1. **If pricing exists:**
   - The existing pricing record is updated with new values
   - Pricing remains effective immediately
   
2. **If no pricing exists:**
   - A new pricing record is created
   - Pricing is effective immediately

**Note:** Pricing is always effective when set. There are no date-based restrictions or expiration dates.

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

**Note:** Commission percentages are stored in the database but are managed by Group Admins, not through this API.

```json
{
  "id": 1,
  "acc_id": 1,
  "course_id": 1,
  "base_price": 500.00,
  "currency": "USD",
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z"
}
```

---

## Best Practices

1. **Pricing is Optional**: You can create courses without pricing and add it later. Don't feel pressured to set pricing immediately.
2. **Set Pricing When Ready**: If you know the pricing, include it when creating the course to avoid an extra API call. But it's perfectly fine to add it later.
3. **Use Unique Codes**: Always use unique, descriptive course codes
4. **Pricing is Always Effective**: When you set pricing, it becomes effective immediately. There are no date restrictions.
5. **Partial Updates**: When updating, only send fields you want to change
6. **View Before Update**: Use the GET endpoint to view current course details and pricing before making updates
7. **Simple Pricing Updates**: Updating pricing simply updates the existing price - no complex date logic needed

---

## Summary

✅ **Create Course (POST)**: Creates new course with completely optional pricing  
✅ **Update Course (PUT)**: Updates course details and/or optional pricing  
✅ **View Course (GET)**: View course details including current pricing (if set)  
✅ **Pricing is Optional**: You can create and manage courses without any pricing information  
✅ **Pricing Support**: When pricing is provided, supports base price and currency  
✅ **Always Effective**: Pricing is always effective immediately when set - no date restrictions  
✅ **Error Handling**: Provides clear error messages for validation failures  
✅ **Flexible Updates**: Update any combination of course fields and pricing  
✅ **Commission Management**: Commission percentages are managed by Group Admins, not ACC admins

---

**Last Updated:** December 28, 2024  
**API Version:** 1.0

