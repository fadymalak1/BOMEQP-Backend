# Payment Intent Validation Guide

## Common 422 Validation Errors

### Issue: 422 Unprocessable Content

When you receive a `422` error when calling the payment intent endpoint, it means validation failed. Check the error response for specific validation errors.

---

## Request Format for Code Purchase Payment Intent

**Endpoint:** `POST /api/training-center/codes/payment-intent`

### Correct Request Format

```json
{
  "acc_id": 3,
  "course_id": 5,
  "quantity": 10,
  "discount_code": "SAVE20"
}
```

### Field Requirements

| Field | Type | Required | Validation Rules | Notes |
|-------|------|----------|------------------|-------|
| `acc_id` | integer | Yes | Must exist in `accs` table | Can be sent as number or string (will be converted) |
| `course_id` | integer | Yes | Must exist in `courses` table | Can be sent as number or string (will be converted) |
| `quantity` | integer | Yes | Minimum: 1 | Must be a positive integer |
| `discount_code` | string | No | Max length: 255 | Optional discount code |

### Common Validation Errors

#### 1. Missing Required Fields

**Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "acc_id": ["The acc id field is required."],
    "course_id": ["The course id field is required."],
    "quantity": ["The quantity field is required."]
  }
}
```

**Solution:** Ensure all required fields are included in the request body.

#### 2. Invalid Data Types

**Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "acc_id": ["The acc id must be an integer."],
    "quantity": ["The quantity must be an integer."]
  }
}
```

**Solution:** Send numeric values for `acc_id`, `course_id`, and `quantity`.

**Frontend Example:**
```javascript
// ✅ Correct
const data = {
  acc_id: 3,           // number
  course_id: 5,        // number
  quantity: 10,        // number
  discount_code: "SAVE20"
};

// ✅ Also correct (strings will be converted)
const data = {
  acc_id: "3",         // string (will be converted to integer)
  course_id: "5",      // string (will be converted to integer)
  quantity: "10",      // string (will be converted to integer)
  discount_code: "SAVE20"
};

// ❌ Wrong (non-numeric strings)
const data = {
  acc_id: "abc",       // Will fail validation
  course_id: 5,
  quantity: 10
};
```

#### 3. Invalid IDs (Not Found in Database)

**Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "acc_id": ["The selected acc id is invalid."],
    "course_id": ["The selected course id is invalid."]
  }
}
```

**Solution:** 
- Verify that `acc_id` exists in the `accs` table
- Verify that `course_id` exists in the `courses` table
- Ensure the IDs are correct

#### 4. Invalid Quantity

**Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "quantity": ["The quantity must be at least 1."]
  }
}
```

**Solution:** Ensure `quantity` is a positive integer (>= 1).

---

## Frontend Implementation Examples

### React/Axios Example

```javascript
import axios from 'axios';

const createPaymentIntent = async (accId, courseId, quantity, discountCode = null) => {
  try {
    const response = await axios.post(
      '/api/training-center/codes/payment-intent',
      {
        acc_id: Number(accId),        // Ensure it's a number
        course_id: Number(courseId),  // Ensure it's a number
        quantity: Number(quantity),   // Ensure it's a number
        discount_code: discountCode   // Can be null or string
      },
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      }
    );
    
    return response.data;
  } catch (error) {
    if (error.response?.status === 422) {
      // Handle validation errors
      console.error('Validation errors:', error.response.data.errors);
      // Display errors to user
      Object.keys(error.response.data.errors).forEach(field => {
        console.error(`${field}:`, error.response.data.errors[field][0]);
      });
    }
    throw error;
  }
};
```

### Fetch API Example

```javascript
const createPaymentIntent = async (accId, courseId, quantity, discountCode = null) => {
  try {
    const response = await fetch('/api/training-center/codes/payment-intent', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        acc_id: parseInt(accId, 10),      // Convert to integer
        course_id: parseInt(courseId, 10), // Convert to integer
        quantity: parseInt(quantity, 10),  // Convert to integer
        discount_code: discountCode || null
      })
    });

    if (!response.ok) {
      const errorData = await response.json();
      
      if (response.status === 422) {
        // Validation errors
        console.error('Validation errors:', errorData.errors);
        return { error: errorData };
      }
      
      throw new Error(errorData.message || 'Request failed');
    }

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error creating payment intent:', error);
    throw error;
  }
};
```

### Vue.js Example

```javascript
async createPaymentIntent(accId, courseId, quantity, discountCode = null) {
  try {
    const response = await this.$http.post('/api/training-center/codes/payment-intent', {
      acc_id: Number(accId),
      course_id: Number(courseId),
      quantity: Number(quantity),
      discount_code: discountCode
    });

    return response.data;
  } catch (error) {
    if (error.response?.status === 422) {
      // Handle validation errors
      const errors = error.response.data.errors;
      for (const field in errors) {
        this.$toast.error(`${field}: ${errors[field][0]}`);
      }
    }
    throw error;
  }
}
```

---

## Debugging Tips

### 1. Check Request Body

Ensure the request body is properly formatted as JSON:

```javascript
// ✅ Good
const body = JSON.stringify({
  acc_id: 3,
  course_id: 5,
  quantity: 10
});

// ❌ Bad - Not stringified
const body = {
  acc_id: 3,
  course_id: 5,
  quantity: 10
};
```

### 2. Check Content-Type Header

Ensure `Content-Type: application/json` header is set:

```javascript
headers: {
  'Content-Type': 'application/json',
  'Authorization': `Bearer ${token}`
}
```

### 3. Verify Data Types

Convert values to numbers if needed:

```javascript
// Convert string IDs to numbers
const accId = parseInt(accIdFromForm, 10);
const courseId = parseInt(courseIdFromForm, 10);
const quantity = parseInt(quantityFromForm, 10);
```

### 4. Check Network Tab

In browser DevTools Network tab:
- Check the request payload
- Verify all fields are included
- Check response for detailed error messages

### 5. Log Request Data

Add logging to see what's being sent:

```javascript
const requestData = {
  acc_id: Number(accId),
  course_id: Number(courseId),
  quantity: Number(quantity),
  discount_code: discountCode
};

console.log('Sending request:', requestData);

const response = await fetch('/api/training-center/codes/payment-intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(requestData)
});
```

---

## Testing with cURL

```bash
curl -X POST https://aeroenix.com/v1/api/training-center/codes/payment-intent \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "acc_id": 3,
    "course_id": 5,
    "quantity": 10,
    "discount_code": "SAVE20"
  }'
```

---

## Validation Rules Summary

The endpoint validates:
1. **acc_id**: Required, must be integer, must exist in `accs` table
2. **course_id**: Required, must be integer, must exist in `courses` table
3. **quantity**: Required, must be integer, minimum value is 1
4. **discount_code**: Optional, must be string if provided, max 255 characters

---

## Quick Checklist

When receiving 422 error, check:

- [ ] All required fields are present (`acc_id`, `course_id`, `quantity`)
- [ ] `acc_id` is a valid integer
- [ ] `course_id` is a valid integer
- [ ] `quantity` is a positive integer (>= 1)
- [ ] `Content-Type: application/json` header is set
- [ ] Request body is properly JSON stringified
- [ ] `acc_id` exists in database
- [ ] `course_id` exists in database
- [ ] Training Center has authorization from ACC
- [ ] ACC is active
- [ ] Course belongs to the specified ACC

