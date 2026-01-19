# Instructor Profile API Update - Documentation

## Overview
This document outlines the changes made to the Instructor Profile API endpoints. The profile API now includes the new required fields (`date_of_birth` and `passport_image_url`) and supports updating them.

## Date
January 22, 2026

---

## Changes Summary

### New Fields Added to Profile Response
- **Date of Birth (D.O.B)**: Now included in profile response
- **Passport Image URL**: Now included in profile response
- **Is Assessor**: Now included in profile response
- **Languages**: Added as an alias for `specializations` field

### Profile Update Support
- Added support for updating `date_of_birth`
- Added support for uploading `passport` file
- Added support for `languages` field (in addition to `specializations`)

### New File Serving Endpoint
- Added route to serve passport files: `/api/storage/instructors/passport/{filename}`

---

## Updated API Endpoints

### 1. Get Instructor Profile
**Endpoint**: `GET /v1/api/instructor/profile`

**Changes**:
- Response now includes `date_of_birth`, `passport_image_url`, `is_assessor`, and `languages` fields

**Response Structure**:
```json
{
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "date_of_birth": "1990-01-15",
    "id_number": "ID123456",
    "country": "US",
    "city": "New York",
    "cv_url": "https://example.com/api/storage/instructors/cv/cv_1234567890.pdf",
    "passport_image_url": "https://example.com/api/storage/instructors/passport/passport_1234567890.pdf",
    "photo_url": "https://example.com/api/storage/instructors/photo/photo_1234567890.jpg",
    "certificates": [],
    "specializations": ["English", "Arabic"],
    "languages": ["English", "Arabic"],
    "is_assessor": false,
    "status": "active",
    "training_center": {
      "id": 1,
      "name": "Training Center Name",
      "email": "tc@example.com",
      "phone": "+1234567890",
      "country": "US",
      "city": "New York"
    },
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "instructor",
      "status": "active"
    }
  }
}
```

**New Response Fields**:
- `date_of_birth` (string, date format: YYYY-MM-DD) - **NEW**
- `passport_image_url` (string, URL) - **NEW**
- `is_assessor` (boolean) - **NEW**
- `languages` (array of strings) - **NEW** (alias for `specializations`)

---

### 2. Update Instructor Profile
**Endpoint**: `POST /v1/api/instructor/profile` or `PUT /v1/api/instructor/profile`

**Changes**:
- Added `date_of_birth` as an updatable field
- Added `passport` as an uploadable file
- Added `languages` as an updatable field (alternative to `specializations`)

**Request Body** (multipart/form-data):
- `first_name` (optional, string)
- `last_name` (optional, string)
- `phone` (optional, string)
- `date_of_birth` (optional, date, format: YYYY-MM-DD) - **NEW**
- `country` (optional, string)
- `city` (optional, string)
- `photo` (optional, file, JPEG/PNG, max 5MB)
- `cv` (optional, file, PDF, max 10MB)
- `passport` (optional, file, JPEG/PNG/PDF, max 10MB) - **NEW**
- `certificates` (optional, array of objects)
- `specializations` (optional, array of strings)
- `languages` (optional, array of strings) - **NEW**

**Validation Rules**:
- `date_of_birth`: Must be a valid date before today
- `passport`: Must be a valid file (JPEG, JPG, PNG, or PDF), max 10MB
- `languages`: Must be an array of strings (if provided)

**Response**: 
- Status Code: 200 OK
- Returns the updated profile object with all fields including the new ones

**Example Request**:
```javascript
const formData = new FormData();
formData.append('first_name', 'John');
formData.append('last_name', 'Doe');
formData.append('date_of_birth', '1990-01-15');
formData.append('passport', passportFile); // File object
formData.append('languages', JSON.stringify(['English', 'Arabic']));
```

---

### 3. Get Passport File
**Endpoint**: `GET /v1/api/storage/instructors/passport/{filename}`

**Description**: 
- Public endpoint to serve instructor passport files
- Supports JPEG, PNG, and PDF formats

**Parameters**:
- `filename` (path parameter, required): The filename of the passport file

**Response**: 
- Status Code: 200 OK
- Returns the file with appropriate Content-Type header
- Status Code: 404 Not Found if file doesn't exist

**Example**:
```
GET /v1/api/storage/instructors/passport/passport_1234567890.pdf
```

---

## Field Details

### Date of Birth (`date_of_birth`)
- **Type**: Date
- **Format**: YYYY-MM-DD (e.g., "1990-01-15")
- **Validation**: Must be a date before today
- **Required**: No (optional in profile update, but required when creating/updating instructor via training center API)
- **Location**: Profile response and update

### Passport Image URL (`passport_image_url`)
- **Type**: String (URL)
- **Format**: Full URL to the passport file
- **File Types**: JPEG, JPG, PNG, PDF
- **Max Size**: 10MB
- **Required**: No (optional in profile update, but required when creating/updating instructor via training center API)
- **Location**: Profile response
- **Upload Field**: `passport` (in update request)

### Languages (`languages`)
- **Type**: Array of Strings
- **Description**: List of languages the instructor speaks
- **Alias**: This is an alias for `specializations` field
- **Backward Compatibility**: Both `languages` and `specializations` are accepted
- **Example**: `["English", "Arabic", "French"]`
- **Location**: Profile response and update

### Is Assessor (`is_assessor`)
- **Type**: Boolean
- **Values**: 
  - `true` = Assessor
  - `false` = Instructor
- **Required**: No (cannot be changed by instructor, only by training center)
- **Location**: Profile response

---

## Migration Required

**Important**: Before using the updated Profile API, you must run the database migration:

```bash
php artisan migrate
```

This will add the `date_of_birth` and `passport_image_url` columns to the `instructors` table.

---

## Frontend Implementation Checklist

### Profile Display
- [ ] Update profile display to show `date_of_birth` field
- [ ] Update profile display to show `passport_image_url` (with download/view link)
- [ ] Update profile display to show `is_assessor` status
- [ ] Update profile display to show `languages` field (or continue using `specializations`)

### Profile Update Form
- [ ] Add `date_of_birth` date picker field
- [ ] Add `passport` file upload field
- [ ] Update `specializations` field label to "Languages" (or add separate `languages` field)
- [ ] Add validation for `date_of_birth` (must be before today)
- [ ] Add file validation for `passport` (JPEG, PNG, PDF, max 10MB)
- [ ] Display current passport file if exists
- [ ] Allow user to replace passport file

### File Handling
- [ ] Implement passport file upload in profile update
- [ ] Display passport file link/thumbnail in profile view
- [ ] Handle passport file download/view functionality
- [ ] Show file size and type information for passport

### Error Handling
- [ ] Handle validation errors for `date_of_birth`
- [ ] Handle file upload errors for `passport`
- [ ] Display appropriate error messages

### Testing
- [ ] Test profile retrieval with new fields
- [ ] Test profile update with `date_of_birth`
- [ ] Test profile update with `passport` file upload
- [ ] Test profile update with `languages` field
- [ ] Test passport file serving endpoint
- [ ] Test validation errors

---

## Notes

1. **Date Format**: The `date_of_birth` field must be in `YYYY-MM-DD` format. Ensure your date picker component formats the date correctly.

2. **Passport File Upload**: The passport file can be in JPEG, JPG, PNG, or PDF format with a maximum size of 10MB. The file will be stored and a URL will be returned in the `passport_image_url` field.

3. **Languages vs Specializations**: The API accepts both `languages` and `specializations` field names. The `languages` field is the preferred name going forward, but `specializations` is still supported for backward compatibility. Both fields map to the same database column.

4. **Is Assessor Field**: The `is_assessor` field is read-only in the profile API. Instructors cannot change this field themselves; it can only be changed by the training center that created the instructor.

5. **File Serving**: Passport files are served through the public endpoint `/api/storage/instructors/passport/{filename}`. The filename is automatically generated when the file is uploaded.

6. **Backward Compatibility**: Existing instructors without `date_of_birth` or `passport_image_url` will have `null` values for these fields. The profile API will return `null` for these fields if they are not set.

7. **Profile Update**: All fields in the profile update are optional. You can update individual fields without providing all fields. However, when creating or updating an instructor through the training center API, all fields are required.

---

## API Response Examples

### Get Profile Response
```json
{
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "date_of_birth": "1990-01-15",
    "id_number": "ID123456",
    "country": "US",
    "city": "New York",
    "cv_url": "https://aeroenix.com/api/storage/instructors/cv/cv_1234567890.pdf",
    "passport_image_url": "https://aeroenix.com/api/storage/instructors/passport/passport_1234567890.pdf",
    "photo_url": "https://aeroenix.com/api/storage/instructors/photo/photo_1234567890.jpg",
    "certificates": [],
    "specializations": ["English", "Arabic"],
    "languages": ["English", "Arabic"],
    "is_assessor": false,
    "status": "active",
    "training_center": {
      "id": 1,
      "name": "Training Center Name"
    },
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "instructor",
      "status": "active"
    }
  }
}
```

### Update Profile Success Response
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "date_of_birth": "1990-01-15",
    "passport_image_url": "https://aeroenix.com/api/storage/instructors/passport/passport_1234567890.pdf",
    "languages": ["English", "Arabic", "French"],
    ...
  }
}
```

### Validation Error Response
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "date_of_birth": ["The date of birth must be a date before today."],
    "passport": ["The passport must be a file of type: jpeg, jpg, png, pdf."]
  }
}
```

---

## Support

For questions or issues related to these changes, please contact the backend development team.

