# Training Center Profile API Documentation

## Overview
New API endpoints have been added to allow training center administrators to view and update their training center profile information.

## Endpoints Added

### 1. Get Training Center Profile
**Endpoint:** `GET /api/training-center/profile`

**Description:** Retrieves the complete profile information for the authenticated training center.

**Authentication Required:** Yes (Bearer token with training_center_admin role)

**Response:** 
- **Success (200):** Returns the training center profile object containing all fields
- **Not Found (404):** Training center profile not found
- **Unauthorized (401):** User not authenticated or doesn't have required role

**Response Data Includes:**
- Basic Information: name, legal_name, registration_number
- Location: country, city, address
- Contact: phone, email, website
- Additional: logo_url, status, referred_by_group
- Timestamps: created_at, updated_at

---

### 2. Update Training Center Profile
**Endpoint:** `PUT /api/training-center/profile`

**Description:** Updates the training center profile information. All fields are optional - only send the fields you want to update.

**Authentication Required:** Yes (Bearer token with training_center_admin role)

**Request Body:**
All fields are optional. Send only the fields you want to update:
- `name` (string, max 255 characters)
- `legal_name` (string, max 255 characters)
- `registration_number` (string, max 255 characters, must be unique)
- `country` (string, max 255 characters)
- `city` (string, max 255 characters)
- `address` (string, no length limit)
- `phone` (string, max 255 characters)
- `email` (string, valid email format, must be unique)
- `website` (string, valid URL format, optional)
- `logo_url` (string, valid URL format, optional)

**Response:**
- **Success (200):** Returns updated profile object with success message
- **Validation Error (422):** Request validation failed (e.g., invalid email format, duplicate email/registration number)
- **Not Found (404):** Training center profile not found
- **Unauthorized (401):** User not authenticated or doesn't have required role
- **Server Error (500):** Internal server error during update

**Success Response Format:**
```json
{
  "message": "Profile updated successfully",
  "profile": { /* updated training center object */ }
}
```

**Error Response Format:**
```json
{
  "message": "Validation error message",
  "errors": {
    "field_name": ["Error message for this field"]
  }
}
```

---

## Important Notes

### Authentication
- Both endpoints require authentication using Bearer token in the Authorization header
- User must have the `training_center_admin` role
- Token format: `Authorization: Bearer {your_token}`

### Profile Lookup
- The system automatically finds the training center associated with the authenticated user's email
- No need to pass training center ID in the request
- The user's email must match the training center's email in the system

### Update Behavior
- All fields in the update request are optional
- Only fields provided in the request will be updated
- Fields not included in the request will remain unchanged
- Empty or null values will not update the corresponding fields

### Field Validation Rules
- **email:** Must be a valid email format and unique across all training centers
- **registration_number:** Must be unique across all training centers
- **website:** Must be a valid URL format (if provided)
- **logo_url:** Must be a valid URL format (if provided)
- String fields have maximum character limits as specified above

### Error Handling
- Always check the response status code
- For 422 errors, check the `errors` object for specific field validation messages
- For 404 errors, the training center profile was not found (contact support)
- For 401 errors, the authentication token is invalid or expired (re-authenticate)

---

## Usage Recommendations

### When to Use GET Profile
- On page load to display current profile information
- After successful update to refresh the displayed data
- When user navigates to profile settings page

### When to Use PUT Profile
- When user submits the profile edit form
- After user makes changes to profile fields
- When updating specific profile information

### Best Practices
1. Always fetch the current profile data before displaying the edit form
2. Show validation errors to the user for better UX
3. Display a success message after successful update
4. Refresh the profile data after successful update
5. Handle network errors gracefully
6. Show loading states during API calls

---

## Integration Checklist

- [ ] Add GET endpoint call to fetch profile on page load
- [ ] Create form/UI to display profile information
- [ ] Add PUT endpoint call to update profile on form submit
- [ ] Handle validation errors and display them to users
- [ ] Show success/error messages after update
- [ ] Implement loading states during API calls
- [ ] Test with valid and invalid data
- [ ] Test with missing authentication token
- [ ] Test with expired authentication token
- [ ] Verify unique constraints (email, registration_number)
- [ ] Test with optional fields (website, logo_url)

---

## Example Flow

1. User navigates to profile page
2. Frontend calls GET `/api/training-center/profile`
3. Display profile data in form fields
4. User edits profile information
5. User submits the form
6. Frontend calls PUT `/api/training-center/profile` with updated fields
7. If successful, show success message and refresh profile data
8. If validation error, display field-specific error messages
9. If authentication error, redirect to login

---

## Support

If you encounter any issues or need clarification on the API endpoints, please contact the backend team or refer to the Swagger/OpenAPI documentation at `/api/documentation`.

