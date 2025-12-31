# API Update Endpoints Fix - Frontend Developer Guide

## Overview

This document describes the fixes applied to the PUT/update endpoints for Trainees and Instructors. These endpoints were returning `200 OK` responses but not actually updating the data in the database.

## Affected Endpoints

1. **PUT** `/v1/api/training-center/trainees/{id}` - Update Trainee
2. **PUT** `/v1/api/instructor/profile` - Update Instructor Profile

## What Was Fixed

### Problem
The update endpoints were using `$request->only()` which includes all specified keys even when their values are `null` or empty strings. This caused:
- Fields to be unintentionally set to `null` or empty values
- Updates to fail silently when data wasn't properly captured
- Responses showing stale data even after successful updates

### Solution
The endpoints now:
- Explicitly check each field before including it in the update
- Only update fields that are actually provided with non-empty values
- Properly handle both JSON and form-data requests
- Refresh the model after update to ensure accurate responses

## Changes for Frontend Developers

### ✅ What Still Works (No Changes Required)

The API endpoints maintain the same request/response structure. Your existing code should continue to work, but updates will now actually persist.

### 📝 Best Practices

#### 1. Sending Update Requests

**For Trainee Updates:**
```javascript
// ✅ GOOD - Only send fields you want to update
const updateTrainee = async (traineeId, data) => {
  const formData = new FormData();
  
  // Only add fields that have values
  if (data.first_name) formData.append('first_name', data.first_name);
  if (data.last_name) formData.append('last_name', data.last_name);
  if (data.email) formData.append('email', data.email);
  if (data.phone) formData.append('phone', data.phone);
  if (data.id_number) formData.append('id_number', data.id_number);
  if (data.status) formData.append('status', data.status);
  
  // Files (if updating)
  if (data.id_image) formData.append('id_image', data.id_image);
  if (data.card_image) formData.append('card_image', data.card_image);
  
  // Arrays
  if (data.enrolled_classes && data.enrolled_classes.length > 0) {
    data.enrolled_classes.forEach(id => {
      formData.append('enrolled_classes[]', id);
    });
  }
  
  const response = await fetch(`/v1/api/training-center/trainees/${traineeId}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: formData
  });
  
  return response.json();
};
```

**For Instructor Profile Updates:**
```javascript
// ✅ GOOD - Only send fields you want to update
const updateInstructorProfile = async (data) => {
  const formData = new FormData();
  
  // Only add fields that have values
  if (data.first_name) formData.append('first_name', data.first_name);
  if (data.last_name) formData.append('last_name', data.last_name);
  if (data.phone) formData.append('phone', data.phone);
  if (data.country) formData.append('country', data.country);
  if (data.city) formData.append('city', data.city);
  
  // Files (if updating)
  if (data.cv) formData.append('cv', data.cv);
  
  // Arrays
  if (data.specializations && data.specializations.length > 0) {
    data.specializations.forEach(spec => {
      formData.append('specializations[]', spec);
    });
  }
  
  if (data.certificates_json) {
    formData.append('certificates_json', JSON.stringify(data.certificates_json));
  }
  
  const response = await fetch('/v1/api/instructor/profile', {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: formData
  });
  
  return response.json();
};
```

#### 2. Using JSON Requests (Alternative)

You can also send JSON requests:

```javascript
// ✅ GOOD - JSON request
const updateTraineeJSON = async (traineeId, data) => {
  // Filter out null/empty values before sending
  const cleanData = Object.fromEntries(
    Object.entries(data).filter(([_, value]) => 
      value !== null && value !== undefined && value !== ''
    )
  );
  
  const response = await fetch(`/v1/api/training-center/trainees/${traineeId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(cleanData)
  });
  
  return response.json();
};
```

### ❌ What to Avoid

```javascript
// ❌ BAD - Don't send null or empty values
const badUpdate = {
  first_name: "John",
  last_name: null,  // This will be ignored (good!)
  email: "",        // This will be ignored (good!)
  phone: undefined  // This will be ignored (good!)
};

// ❌ BAD - Don't send all fields if you only want to update one
const badUpdate2 = {
  first_name: "John",
  last_name: "",  // Empty string - will be ignored
  email: existingEmail,  // Unchanged - unnecessary
  phone: existingPhone   // Unchanged - unnecessary
};

// ✅ GOOD - Only send what you want to change
const goodUpdate = {
  first_name: "John"  // Only the field you want to update
};
```

## Response Format

Both endpoints return the same response format:

### Success Response (200 OK)
```json
{
  "message": "Trainee updated successfully",
  "trainee": {
    "id": 3,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    // ... other fields
  }
}
```

### Error Responses

**404 Not Found**
```json
{
  "message": "Trainee not found"
}
```

**422 Validation Error**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

## Important Notes

1. **Partial Updates**: You can update just one field or multiple fields. Only provided fields will be updated.

2. **Empty Values**: Empty strings (`""`), `null`, and `undefined` values are automatically filtered out and will NOT update the database field.

3. **Arrays**: For array fields like `enrolled_classes` and `specializations`, make sure to send non-empty arrays. Empty arrays will be ignored.

4. **Files**: File uploads work the same way. If you send a file, it will replace the existing one.

5. **Response Data**: The response always includes the updated entity with the latest data from the database.

## Testing

After these changes, you should verify:

1. ✅ Updates actually persist in the database
2. ✅ Only provided fields are updated
3. ✅ Empty/null values don't overwrite existing data
4. ✅ Response shows the updated data correctly

## Migration Notes

- **No breaking changes** - Existing API calls will continue to work
- **Better behavior** - Updates will now actually persist
- **More reliable** - Empty/null values won't accidentally clear fields

## Support

If you encounter any issues with the update endpoints after these changes, please check:
1. That you're sending data in the correct format
2. That required fields are not empty
3. That you're using the correct authentication token
4. Check the response for any validation errors

