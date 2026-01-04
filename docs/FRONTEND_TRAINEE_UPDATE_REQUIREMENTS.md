# Frontend Requirements: Update Trainee Endpoint

## Overview
This document describes the requirements and steps that the frontend application must follow to successfully update trainee information using the POST endpoint `/api/training-center/trainees/{id}` (PUT is also supported for backward compatibility, but POST is recommended especially for file uploads).

## Endpoint Information

**Method:** POST (Recommended) or PUT (for backward compatibility)  
**URL:** `/api/training-center/trainees/{id}`  
**Authentication:** Required (Bearer Token)  
**Content Type:** `multipart/form-data` (required for file uploads) or `application/x-www-form-urlencoded`

**Important Note:** 
- **Use POST method when uploading files** - Laravel/PHP has limitations with PUT requests and multipart/form-data
- PUT method works fine for text-only updates with `application/x-www-form-urlencoded`
- POST method is strongly recommended for all updates, especially when including file uploads

## Authentication Requirements

- All requests must include an authentication token in the Authorization header
- Token format: `Bearer {token}`
- The token should be obtained during login and stored securely (e.g., in localStorage, sessionStorage, or a secure cookie)
- Include the Authorization header with every update request

## Request Headers

The frontend must set the following headers:

1. **Authorization Header:**
   - Required: Yes
   - Format: `Authorization: Bearer {your_auth_token}`
   - Example: `Authorization: Bearer 261|i7SiDYkEoqaZKh178RqdIs5eiPJ4xHvMeHRb49RQb09ceb64`

2. **Content-Type Header:**
   - When using FormData: **DO NOT set this header manually** - the browser will automatically set it with the correct boundary
   - When using form-urlencoded: Set to `application/x-www-form-urlencoded`
   - When using JSON: Set to `application/json` (though this endpoint expects form data)

## Request Body Format

### Using FormData (Recommended)

When using FormData (recommended approach):

1. Create a FormData object
2. Only append the fields that you want to update (partial updates are supported)
3. For text fields, append as key-value pairs
4. For file fields, append the actual File object
5. For array fields (like enrolled_classes), append each array element with array notation `enrolled_classes[]`

### Using Form-URLEncoded

When using form-urlencoded format:

1. Build the data as key-value pairs
2. URL encode the data
3. Send as request body
4. Note: File uploads are not possible with this format - use FormData instead

## Fields Available for Update

All fields are optional - only include the fields you want to update:

### Text Fields

- **first_name** (string)
  - Max length: 255 characters
  - Example: "John"
  
- **last_name** (string)
  - Max length: 255 characters
  - Example: "Doe"
  
- **email** (string)
  - Must be a valid email format
  - Must be unique across all trainees (except for the current trainee being updated)
  - Example: "john.doe@example.com"
  
- **phone** (string)
  - Max length: 255 characters
  - Example: "+1234567890"
  
- **id_number** (string)
  - Must be unique across all trainees (except for the current trainee being updated)
  - Example: "ID123456"

### Status Field

- **status** (string)
  - Allowed values: "active", "inactive", "suspended"
  - Example: "active"

### File Fields

- **id_image** (file)
  - Accepted formats: JPEG, JPG, PNG, PDF
  - Maximum file size: 10MB (10,240 KB)
  - Must be a valid file object from a file input
  
- **card_image** (file)
  - Accepted formats: JPEG, JPG, PNG, PDF
  - Maximum file size: 10MB (10,240 KB)
  - Must be a valid file object from a file input

### Array Field

- **enrolled_classes** (array of integers)
  - Array of training class IDs
  - Format when using FormData: append each ID as `enrolled_classes[]` with the class ID value
  - Format when using form-urlencoded: same as FormData
  - Example: Array containing class IDs [1, 2, 3] should be sent as multiple `enrolled_classes[]` entries

## Implementation Steps

### Step 1: Prepare the Request

1. Get the trainee ID from your application state or URL parameters
2. Retrieve the authentication token from secure storage
3. Collect the form data from your UI components
4. Validate the data on the frontend before sending (optional but recommended)

### Step 2: Build the Request Body

1. Create a FormData object (recommended) or prepare form-urlencoded string
2. Check each field in your form
3. If a field has been modified or has a value, append it to the request body
4. For file fields, only include if a new file has been selected
5. For array fields, append each element separately with array notation

### Step 3: Validate Data (Frontend)

Before sending the request, validate:

- Email format (if email is being updated)
- File size (max 10MB per file)
- File type (only JPEG, PNG, PDF allowed)
- Status value (must be one of the allowed values)
- Required field constraints if applicable
- Array data types (enrolled_classes must contain integers)

### Step 4: Send the Request

1. **Use POST HTTP method** (recommended, especially for file uploads) or PUT (for backward compatibility with text-only updates)
2. If using POST and you need PUT semantics, you can include `_method: "PUT"` in your FormData (Laravel's method spoofing)
3. Include the Authorization header with Bearer token
4. For FormData: Let the browser set Content-Type automatically (don't set it manually)
5. For form-urlencoded: Set Content-Type to `application/x-www-form-urlencoded`
6. Include the trainee ID in the URL path
7. Send the prepared request body

**Why POST instead of PUT?**
- Laravel/PHP has limitations parsing multipart/form-data with PUT requests
- POST method properly handles file uploads with multipart/form-data
- POST is the recommended method for updates that include file uploads

### Step 5: Handle Response

**Success Response (200 OK):**
- The response will contain:
  - `message`: "Trainee updated successfully"
  - `trainee`: Complete trainee object with all fields and relationships
- Update your local state/UI with the returned trainee data
- Show a success message to the user
- Refresh or update the trainee list if displayed

**Error Responses:**

- **401 Unauthorized:**
  - Token is missing or invalid
  - Action: Redirect to login page or refresh the token
  
- **404 Not Found:**
  - Trainee doesn't exist or doesn't belong to the training center
  - Action: Show error message, navigate away from the update form
  
- **422 Validation Error:**
  - One or more fields failed validation
  - Response contains `errors` object with field-specific error messages
  - Action: Display validation errors next to the corresponding form fields
  
- **403 Forbidden:**
  - User doesn't have permission to update this trainee
  - Action: Show error message
  
- **500 Server Error:**
  - Server-side error occurred
  - Response contains error message in `message` field
  - Action: Show generic error message, log error for debugging

### Step 6: Update UI State

After successful update:

1. Update the trainee object in your application state
2. Refresh any lists that display this trainee
3. Close the edit form or navigate back
4. Show success notification to the user

## Important Notes for Frontend Developers

### HTTP Method Selection

- **Use POST method** when uploading files or using FormData
- **PUT method works** but has limitations with multipart/form-data due to PHP/Laravel constraints
- POST is the **recommended method** for all updates, especially those involving file uploads
- If using POST, you can optionally include `_method: "PUT"` in FormData for Laravel's method spoofing (not required)

### Content-Type Handling

- **CRITICAL:** When using FormData, DO NOT manually set the Content-Type header
- The browser automatically sets it to `multipart/form-data` with the correct boundary
- Manually setting it will break the request and cause the server to not receive the data properly

### Partial Updates

- You can update only the fields that have changed
- Fields not included in the request will remain unchanged
- Example: If you only want to update the email, you can send just the email field

### File Upload Handling

- Only include file fields if a new file has been selected
- If the user doesn't select a new file, don't include that field in the request
- The existing file will remain unchanged if the field is omitted
- Validate file size and type before sending to avoid server-side validation errors

### Array Field Format

- When sending arrays (like enrolled_classes), use array notation `enrolled_classes[]`
- Append each array element separately with the same key name `enrolled_classes[]`
- Example: For classes [27, 28], send:
  - `enrolled_classes[]` = 27
  - `enrolled_classes[]` = 28

### Error Handling Best Practices

1. Always check the response status code
2. Parse error messages and display them to users in a user-friendly way
3. Handle network errors (timeouts, connection failures)
4. Show loading indicators while the request is in progress
5. Disable form submission buttons during the request to prevent duplicate submissions

### Loading States

- Show a loading indicator while the request is in progress
- Disable form inputs and submit button during the request
- Prevent user from navigating away or closing the form during update

### Optimistic Updates (Optional)

- You can update the UI immediately before receiving server confirmation
- If the request fails, revert the optimistic update
- This provides better user experience but requires careful error handling

## Testing Checklist

Ensure your implementation handles:

- [ ] Updating single field (e.g., first name only)
- [ ] Updating multiple fields at once
- [ ] Updating with file uploads (ID image and/or card image)
- [ ] Updating enrolled classes (adding and removing)
- [ ] Handling validation errors (duplicate email, invalid file type, etc.)
- [ ] Handling network errors (timeout, connection failure)
- [ ] Handling authentication errors (expired token, invalid token)
- [ ] Proper loading states during request
- [ ] Success notification after update
- [ ] Updating UI state after successful update
- [ ] File size validation (files over 10MB)
- [ ] File type validation (only JPEG, PNG, PDF)
- [ ] Empty form submissions (should not send empty requests)
- [ ] Large file uploads (test with files close to 10MB limit)

## Common Mistakes to Avoid

1. **Manually setting Content-Type with FormData** - This breaks file uploads
2. **Sending all fields even when unchanged** - Only send modified fields for cleaner requests
3. **Not handling errors properly** - Always show error messages to users
4. **Not validating on frontend** - Validate before sending to improve UX
5. **Forgetting to include Authorization header** - All requests must be authenticated
6. **Incorrect array format** - Use `enrolled_classes[]` notation, not `enrolled_classes` as a single field
7. **Not handling loading states** - Users should know when a request is in progress
8. **Sending null or undefined values** - Only include fields with actual values

## API Response Structure

### Success Response Structure

```json
{
  "message": "Trainee updated successfully",
  "trainee": {
    "id": 9,
    "training_center_id": 11,
    "first_name": "Tester",
    "last_name": "1",
    "email": "tester1@gmail.com",
    "phone": "01233465656",
    "id_number": "13252222222222",
    "id_image_url": "https://example.com/storage/...",
    "card_image_url": "https://example.com/storage/...",
    "status": "inactive",
    "created_at": "2026-01-04T22:14:23.000000Z",
    "updated_at": "2026-01-04T22:15:00.000000Z",
    "training_classes": [
      {
        "id": 27,
        "course": {...},
        "instructor": {...}
      }
    ]
  }
}
```

### Error Response Structure

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "id_number": ["The id number has already been taken."]
  }
}
```

## Additional Considerations

### File Upload Progress

- Consider showing upload progress for large files
- Most HTTP libraries support upload progress callbacks
- This improves user experience for slow connections

### Form State Management

- Track which fields have been modified
- Only send modified fields to the server
- This reduces request size and processing time

### Validation Timing

- Perform validation on blur (when user leaves a field)
- Perform validation on submit (before sending request)
- Show inline validation errors immediately
- Highlight fields with errors visually

### Accessibility

- Ensure form is keyboard navigable
- Provide clear error messages for screen readers
- Use proper ARIA labels for form fields
- Make loading states accessible

### Mobile Considerations

- Ensure file inputs work on mobile devices
- Test with mobile browsers (iOS Safari, Chrome Mobile)
- Consider camera access for ID and card images on mobile
- Handle large file uploads gracefully on slower mobile connections

