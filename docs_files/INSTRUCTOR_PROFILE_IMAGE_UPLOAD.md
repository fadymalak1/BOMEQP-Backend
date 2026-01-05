# Instructor Profile Image Upload Feature

## Overview
This document describes the new profile image upload functionality added to the instructor profile API. Instructors can now upload a profile image and manage multiple certificates through their profile dashboard.

## Changes Made

### New Field: Profile Image (`photo_url`)
- Added a new `photo_url` field to the instructor profile
- Instructors can upload a profile image (jpg, jpeg, png formats)
- Maximum file size: 5MB
- The profile image is stored and served via a dedicated API endpoint

### Profile Image Upload
- Instructors can upload a profile image when updating their profile
- When a new image is uploaded, the old image is automatically deleted
- The image is stored in a secure location and accessed via API URL

### Certificate Management (Existing Feature)
- The existing certificate upload functionality remains unchanged
- Instructors can continue to upload multiple certificates
- Certificates are stored as an array in the profile response

## API Endpoints

### Get Instructor Profile
**Endpoint:** `GET /api/instructor/profile`

**Response includes:**
- All existing profile fields
- **NEW:** `photo_url` - URL to the instructor's profile image (null if no image uploaded)
- `certificates` - Array of certificate objects (existing field)

**Example Response:**
```json
{
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "photo_url": "https://yourdomain.com/api/storage/instructors/photo/1234567890_1_profile.jpg",
    "cv_url": "https://yourdomain.com/api/storage/instructors/cv/1234567890_1_cv.pdf",
    "certificates": [
      {
        "name": "Certificate Name",
        "issuer": "Issuer Name",
        "issue_date": "2024-01-01",
        "expiry": "2025-01-01",
        "url": "https://yourdomain.com/api/storage/instructors/certificates/cert_1234567890.pdf"
      }
    ],
    "specializations": ["Specialization 1", "Specialization 2"],
    "status": "active",
    "training_center": { ... }
  }
}
```

### Update Instructor Profile
**Endpoint:** `POST /api/instructor/profile`

**Content-Type:** `multipart/form-data`

**New Field:**
- `photo` - Profile image file (optional)
  - Allowed formats: jpg, jpeg, png
  - Maximum size: 5MB
  - Accepts binary file upload

**Existing Fields (unchanged):**
- `first_name` - String
- `last_name` - String
- `phone` - String
- `country` - String
- `city` - String
- `cv` - PDF file (max 10MB)
- `certificates` - Array of certificate objects
- `specializations` - Array of strings

**Note:** All fields are optional. Only include the fields you want to update.

**Example Request:**
- When uploading a profile image, use `multipart/form-data`
- Include the `photo` field with the image file
- Other fields can be included as text or JSON strings (depending on field type)

**Response:**
- Returns the updated profile with the new `photo_url` value
- If photo upload fails, an error response is returned with details

## Profile Image URL Format

Profile images are served via the following URL pattern:
```
/api/storage/instructors/photo/{filename}
```

**Example:**
```
https://yourdomain.com/api/storage/instructors/photo/1234567890_1_profile.jpg
```

**Important Notes:**
- The URL is publicly accessible (no authentication required)
- The filename includes a timestamp and instructor ID for uniqueness
- When an instructor uploads a new photo, the old photo URL becomes invalid (file is deleted)

## Frontend Implementation Guidelines

### Uploading a Profile Image

1. **Use multipart/form-data:**
   - Set `Content-Type: multipart/form-data` in your request
   - Include the `photo` field with the selected image file
   - Include other profile fields if you want to update them simultaneously

2. **File Selection:**
   - Allow users to select image files (jpg, jpeg, png)
   - Show file size validation before upload (max 5MB)
   - Consider displaying a preview of the selected image

3. **Upload Progress:**
   - Show upload progress indicator
   - Handle large file uploads gracefully

4. **Error Handling:**
   - Display appropriate error messages for:
     - File too large (>5MB)
     - Invalid file format
     - Network errors
     - Server errors

### Displaying the Profile Image

1. **Image Display:**
   - Use the `photo_url` from the profile response
   - Display a placeholder/default image if `photo_url` is null
   - Consider using lazy loading for better performance

2. **Image Preview:**
   - Show a preview after successful upload
   - Display the current profile image on the profile page

3. **Image Removal:**
   - To remove the profile image, you can send an empty/null value (implementation may vary)
   - Note: The old image is automatically deleted when a new one is uploaded

### Handling Certificates

The certificate upload functionality remains the same:
- Multiple certificates can be uploaded
- Each certificate can include a file upload or URL
- Certificates are stored as an array in the profile response

## Validation Rules

### Profile Image
- **Required:** No (optional field)
- **File Types:** jpg, jpeg, png
- **Max Size:** 5MB (5,120 KB)
- **Allowed MIME Types:** image/jpeg, image/jpg, image/png

### Certificates (unchanged)
- **Required:** No
- **File Types:** PDF
- **Max Size:** 10MB per certificate

## Error Responses

### Profile Image Upload Errors

**File Too Large:**
```json
{
  "message": "File size exceeds maximum allowed size of 5MB",
  "error": "File too large",
  "hint": "Maximum file size: 5MB. Your file: X.XX MB"
}
```

**Invalid File Type:**
```json
{
  "message": "Invalid file type. Allowed types: image/jpeg, image/jpg, image/png"
}
```

**Upload Failed:**
```json
{
  "message": "Profile update failed: [error details]",
  "error": "[error message]",
  "error_code": "update_failed"
}
```

## Testing Checklist

- [ ] Upload a profile image successfully
- [ ] Verify image appears in profile response
- [ ] Upload a new image to replace the old one
- [ ] Verify old image URL becomes invalid after replacement
- [ ] Test with different image formats (jpg, jpeg, png)
- [ ] Test file size validation (reject files > 5MB)
- [ ] Test invalid file format rejection
- [ ] Verify image is accessible via the returned URL
- [ ] Test profile update with and without image upload
- [ ] Verify certificates still work as before
- [ ] Test error handling for failed uploads

## Notes for Frontend Developers

1. **Backward Compatibility:**
   - The `photo_url` field is new and will be `null` for existing instructors
   - Always check for null before displaying the image
   - Provide a default placeholder image for users without a profile photo

2. **File Upload Best Practices:**
   - Validate file size and type on the frontend before upload
   - Show user-friendly error messages
   - Consider compressing images before upload to reduce file size
   - Show upload progress for better user experience

3. **Image Display:**
   - The profile image URL is publicly accessible
   - No authentication required to view the image
   - Images are served with appropriate Content-Type headers
   - Consider caching images on the frontend for better performance

4. **Multiple File Uploads:**
   - Profile image and certificates can be uploaded together in the same request
   - Use `multipart/form-data` format for file uploads
   - Ensure proper field naming: `photo` for profile image, `certificates[]` for certificate files

5. **API Response:**
   - The profile response includes the full URL to the profile image
   - No need to construct URLs manually
   - The URL format may change, so always use the URL from the API response

