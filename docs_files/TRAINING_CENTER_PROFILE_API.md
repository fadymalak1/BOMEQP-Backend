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
**Endpoint:** `POST /api/training-center/profile` (recommended for file uploads)  
**Alternative:** `PUT /api/training-center/profile` (for text-only updates, backward compatibility)

**Description:** Updates the training center profile information. All fields are optional - only send the fields you want to update. **Use POST method when uploading logo files.**

**Authentication Required:** Yes (Bearer token with training_center_admin role)

**Request Methods:**
- **POST (Recommended):** Use for file uploads (multipart/form-data). Supports logo file upload.
- **PUT (Backward Compatibility):** Use for text-only updates (application/json or application/x-www-form-urlencoded). Does NOT support file uploads.

**Request Body:**
All fields are optional. Send only the fields you want to update:

**Text Fields:**
- `name` (string, max 255 characters)
- `legal_name` (string, max 255 characters)
- `registration_number` (string, max 255 characters, must be unique)
- `country` (string, max 255 characters)
- `city` (string, max 255 characters)
- `address` (string, no length limit)
- `phone` (string, max 255 characters)
- `email` (string, valid email format, must be unique)
- `website` (string, valid URL format, optional)

**Logo Options (choose one):**
- `logo` (file, image file: jpg, jpeg, png, max 5MB) - **Use POST method with multipart/form-data**
- `logo_url` (string, valid URL format) - Provide logo URL instead of uploading file

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

### Logo Upload
- **Use POST method** when uploading logo files (required for multipart/form-data)
- Logo file must be an image (jpg, jpeg, png format)
- Maximum file size: 5MB
- If logo file is uploaded, the `logo_url` field will be automatically set (don't send both)
- If only `logo_url` is provided (no file upload), it will update the logo URL directly
- When a new logo is uploaded, the old logo file will be automatically deleted from storage

### Field Validation Rules
- **email:** Must be a valid email format and unique across all training centers
- **registration_number:** Must be unique across all training centers
- **website:** Must be a valid URL format (if provided)
- **logo_url:** Must be a valid URL format (if provided, and logo file is NOT uploaded)
- **logo:** Must be an image file (jpg, jpeg, png), maximum 5MB (only with POST method)
- String fields have maximum character limits as specified above
- **Note:** Cannot send both `logo` (file) and `logo_url` (URL) in the same request - choose one

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

### When to Use POST Profile
- When user submits the profile edit form **with logo file upload**
- When uploading a new logo image
- When submitting forms with file uploads (recommended method)

### When to Use PUT Profile
- For text-only updates without file uploads
- For backward compatibility with existing implementations
- When updating profile fields without changing the logo

### Best Practices
1. Always fetch the current profile data before displaying the edit form
2. **Use POST method when the form includes file uploads** (logo file)
3. Show validation errors to the user for better UX
4. Display a success message after successful update
5. Refresh the profile data after successful update
6. Handle network errors gracefully
7. Show loading states during API calls
8. Validate file size and type before uploading (check on client side for better UX)
9. Show image preview after logo upload
10. Handle large file uploads with progress indicators

---

## Integration Checklist

- [ ] Add GET endpoint call to fetch profile on page load
- [ ] Create form/UI to display profile information
- [ ] Add file input field for logo upload
- [ ] Add POST endpoint call to update profile on form submit (when logo file is present)
- [ ] Add PUT endpoint call to update profile on form submit (text-only updates)
- [ ] Implement file upload handling with multipart/form-data
- [ ] Add client-side file validation (size, type) before upload
- [ ] Handle validation errors and display them to users
- [ ] Show success/error messages after update
- [ ] Implement loading states during API calls
- [ ] Add image preview for logo upload
- [ ] Test with valid and invalid data
- [ ] Test with missing authentication token
- [ ] Test with expired authentication token
- [ ] Verify unique constraints (email, registration_number)
- [ ] Test with optional fields (website, logo_url)
- [ ] Test logo file upload (POST method)
- [ ] Test logo URL update (PUT/POST method)
- [ ] Test with various image formats (jpg, jpeg, png)
- [ ] Test file size limits (max 5MB)
- [ ] Test that old logo is replaced when new one is uploaded

---

## Example Flow

### Text-Only Update (PUT method):
1. User navigates to profile page
2. Frontend calls GET `/api/training-center/profile`
3. Display profile data in form fields
4. User edits profile information (text fields only, no logo)
5. User submits the form
6. Frontend calls PUT `/api/training-center/profile` with updated fields (application/json)
7. If successful, show success message and refresh profile data
8. If validation error, display field-specific error messages
9. If authentication error, redirect to login

### Update with Logo Upload (POST method):
1. User navigates to profile page
2. Frontend calls GET `/api/training-center/profile`
3. Display profile data in form fields
4. User edits profile information and selects a new logo file
5. Frontend validates logo file (size, type) on client side
6. User submits the form
7. Frontend creates FormData object with all fields and logo file
8. Frontend calls POST `/api/training-center/profile` with multipart/form-data
9. If successful, show success message and refresh profile data (new logo_url will be in response)
10. If validation error, display field-specific error messages (including file validation errors)
11. If authentication error, redirect to login

---

## Support

If you encounter any issues or need clarification on the API endpoints, please contact the backend team or refer to the Swagger/OpenAPI documentation at `/api/documentation`.

