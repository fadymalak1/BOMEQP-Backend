# Course Base Price API Documentation

Complete documentation for course pricing endpoints (add/update base price).

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

### 1. Set Course Pricing

**POST** `/api/acc/courses/{id}/pricing`

Set base price and commission percentages for a course. If there's an active pricing, it will be automatically ended before creating the new one.

**URL Parameters:**
- `id` (integer, required) - Course ID

**Request Body:**
```json
{
  "base_price": 500.00,
  "currency": "USD",
  "group_commission_percentage": 10.0,
  "training_center_commission_percentage": 5.0,
  "instructor_commission_percentage": 3.0,
  "effective_from": "2024-01-01",
  "effective_to": "2024-12-31"
}
```

**Field Descriptions:**
- `base_price` (number, required) - Base price for the course certificate. Must be >= 0
- `currency` (string, required) - Currency code (3 characters, e.g., "USD", "EUR")
- `group_commission_percentage` (number, required) - Group admin commission percentage (0-100)
- `training_center_commission_percentage` (number, required) - Training center commission percentage (0-100)
- `instructor_commission_percentage` (number, required) - Instructor commission percentage (0-100)
- `effective_from` (date, required) - Date from which this pricing is effective (YYYY-MM-DD)
- `effective_to` (date, optional) - Date until which this pricing is effective (YYYY-MM-DD). Must be after `effective_from`

**Validation Rules:**
- Total commission percentages (group + training_center + instructor) cannot exceed 100%
- `effective_to` must be after `effective_from` if provided
- All commission percentages must be between 0 and 100

**Response (200):**
```json
{
  "message": "Pricing set successfully",
  "pricing": {
    "id": 1,
    "acc_id": 1,
    "course_id": 5,
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
}
```

**Error Response (422) - Commission Exceeds 100%:**
```json
{
  "message": "Total commission percentages cannot exceed 100%",
  "errors": {
    "commission_percentages": [
      "The sum of all commission percentages is 120% which exceeds 100%"
    ]
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

### 2. Update Course Pricing

**PUT** `/api/acc/courses/{id}/pricing`

Update the active pricing for a course. Updates the most recent active pricing record.

**URL Parameters:**
- `id` (integer, required) - Course ID

**Request Body (all fields optional):**
```json
{
  "base_price": 550.00,
  "currency": "USD",
  "group_commission_percentage": 12.0,
  "training_center_commission_percentage": 5.0,
  "instructor_commission_percentage": 3.0,
  "effective_from": "2024-01-01",
  "effective_to": null
}
```

**Field Descriptions:**
- All fields are optional - only provide fields you want to update
- Same validation rules apply as in setPricing

**Response (200):**
```json
{
  "message": "Pricing updated successfully",
  "pricing": {
    "id": 1,
    "acc_id": 1,
    "course_id": 5,
    "base_price": 550.00,
    "currency": "USD",
    "group_commission_percentage": 12.00,
    "training_center_commission_percentage": 5.00,
    "instructor_commission_percentage": 3.00,
    "effective_from": "2024-01-01",
    "effective_to": null,
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

**Error Response (404):**
```json
{
  "message": "Pricing not found for this course"
}
```

---

## How It Works

### Setting New Pricing

When you set a new pricing:
1. The system checks for any active pricing that overlaps with the new `effective_from` date
2. If found, the previous pricing's `effective_to` is set to one day before the new pricing starts
3. A new pricing record is created with the provided values

**Example:**
- Existing pricing: `effective_from: 2024-01-01`, `effective_to: null` (active)
- New pricing: `effective_from: 2024-06-01`
- Result: Previous pricing gets `effective_to: 2024-05-31`, new pricing is created

### Updating Pricing

When you update pricing:
1. The system finds the most recent active pricing (where `effective_from <= now` and `effective_to` is null or >= now)
2. If no active pricing exists, it uses the latest pricing record
3. Only provided fields are updated

### Commission Percentage Validation

The system ensures that:
- Each commission percentage is between 0 and 100
- The sum of all three commission percentages does not exceed 100%

**Example Valid Combinations:**
- Group: 10%, Training Center: 5%, Instructor: 3% = Total: 18% ✅
- Group: 50%, Training Center: 30%, Instructor: 20% = Total: 100% ✅
- Group: 40%, Training Center: 40%, Instructor: 30% = Total: 110% ❌ (Invalid)

---

## Usage Examples

### Example 1: Set Initial Pricing

```javascript
// Set pricing for a new course
const response = await fetch('/api/acc/courses/5/pricing', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    base_price: 500.00,
    currency: 'USD',
    group_commission_percentage: 10.0,
    training_center_commission_percentage: 5.0,
    instructor_commission_percentage: 3.0,
    effective_from: '2024-01-01'
  })
});

const data = await response.json();
console.log(data.message); // "Pricing set successfully"
```

### Example 2: Update Base Price

```javascript
// Update only the base price
const response = await fetch('/api/acc/courses/5/pricing', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    base_price: 550.00
  })
});

const data = await response.json();
console.log(data.message); // "Pricing updated successfully"
```

### Example 3: Update Commission Percentages

```javascript
// Update commission percentages
const response = await fetch('/api/acc/courses/5/pricing', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    group_commission_percentage: 12.0,
    training_center_commission_percentage: 6.0,
    instructor_commission_percentage: 4.0
  })
});
```

### Example 4: Set Future Pricing

```javascript
// Set pricing that will be effective from a future date
const response = await fetch('/api/acc/courses/5/pricing', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    base_price: 600.00,
    currency: 'USD',
    group_commission_percentage: 10.0,
    training_center_commission_percentage: 5.0,
    instructor_commission_percentage: 3.0,
    effective_from: '2024-06-01',
    effective_to: '2024-12-31'
  })
});
```

---

## Testing the Endpoints

### Test Set Pricing

```bash
curl -X POST "https://aeroenix.com/v1/api/acc/courses/5/pricing" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "base_price": 500.00,
    "currency": "USD",
    "group_commission_percentage": 10.0,
    "training_center_commission_percentage": 5.0,
    "instructor_commission_percentage": 3.0,
    "effective_from": "2024-01-01"
  }'
```

### Test Update Pricing

```bash
curl -X PUT "https://aeroenix.com/v1/api/acc/courses/5/pricing" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "base_price": 550.00
  }'
```

---

## Common Issues and Solutions

### Issue: "Total commission percentages cannot exceed 100%"

**Solution:** Ensure the sum of all three commission percentages is 100% or less.

```json
// ❌ Invalid
{
  "group_commission_percentage": 50,
  "training_center_commission_percentage": 40,
  "instructor_commission_percentage": 30  // Total: 120%
}

// ✅ Valid
{
  "group_commission_percentage": 50,
  "training_center_commission_percentage": 30,
  "instructor_commission_percentage": 20  // Total: 100%
}
```

### Issue: "Pricing not found for this course"

**Solution:** Make sure you've set pricing first using the POST endpoint before trying to update.

### Issue: "effective_to must be after effective_from"

**Solution:** Ensure the `effective_to` date is after the `effective_from` date.

```json
// ❌ Invalid
{
  "effective_from": "2024-12-31",
  "effective_to": "2024-01-01"
}

// ✅ Valid
{
  "effective_from": "2024-01-01",
  "effective_to": "2024-12-31"
}
```

---

## Data Model

### CertificatePricing Model

```json
{
  "id": 1,
  "acc_id": 1,
  "course_id": 5,
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

### Relationships

- **ACC**: Belongs to one ACC
- **Course**: Belongs to one Course
- A course can have multiple pricing records (with different effective dates)

---

## Best Practices

1. **Set Initial Pricing**: Always set pricing when creating a course or immediately after
2. **Use Effective Dates**: Use `effective_from` and `effective_to` to manage price changes over time
3. **Validate Commissions**: Always ensure commission percentages total 100% or less
4. **Update vs Set**: Use `PUT` to update existing pricing, use `POST` to create new pricing with future dates
5. **Check Active Pricing**: Before setting new pricing, check if there's already an active one

---

## Summary

✅ **Set Pricing (POST)**: Creates new pricing, automatically ends overlapping active pricing  
✅ **Update Pricing (PUT)**: Updates the active pricing record  
✅ **Validation**: Ensures commission percentages don't exceed 100%  
✅ **Date Management**: Handles effective dates and automatically manages pricing transitions  
✅ **Error Handling**: Provides clear error messages for validation failures

---

**Last Updated:** December 28, 2024  
**API Version:** 1.0

