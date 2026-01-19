# Trainee Fields Update - API Changes Documentation

## Overview
This document outlines the changes made to the Trainee API endpoints. All trainee fields are now **required** when creating or updating a trainee.

## Date
January 22, 2026

---

## Changes Summary

### New Field Added
- **Nationality**: A new required field has been added to store the trainee's nationality.

### All Fields Now Required
All trainee fields are now mandatory for both **create** and **update** operations.

---

## Updated Trainee Fields

The following fields are **required** for all trainee operations:

1. **Trainee First Name** (`first_name`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes

2. **Trainee Last Name** (`last_name`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes

3. **E-mail Address** (`email`)
   - Type: Email
   - Format: Valid email address
   - Unique: Yes (must be unique across all trainees)
   - Required: Yes

4. **Nationality** (`nationality`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes
   - **NEW FIELD**

5. **Phone No.** (`phone`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes

6. **Passport/National ID Number** (`id_number`)
   - Type: String
   - Unique: Yes (must be unique across all trainees)
   - Required: Yes

7. **Upload Passport/National ID Copy** (`id_image`)
   - Type: File (multipart/form-data)
   - Allowed Types: JPEG, JPG, PNG, PDF
   - Max Size: 10MB
   - Required: Yes

8. **Pic Upload** (`card_image`)
   - Type: File (multipart/form-data)
   - Allowed Types: JPEG, JPG, PNG, PDF
   - Max Size: 10MB
   - Required: Yes

---

## Affected API Endpoints

### 1. Create Trainee
**Endpoint**: `POST /v1/api/training-center/trainees`

**Changes**:
- Added `nationality` as a required field
- All fields are now required (previously some were optional)

**Request Body** (multipart/form-data):
- `first_name` (required)
- `last_name` (required)
- `email` (required, unique)
- `phone` (required)
- `nationality` (required) - **NEW**
- `id_number` (required, unique)
- `id_image` (required, file)
- `card_image` (required, file)
- `enrolled_classes` (optional, array of training class IDs)
- `status` (optional, default: "active")

**Response**: 
- Status Code: 201 Created
- Returns the created trainee object

---

### 2. Update Trainee
**Endpoint**: `POST /v1/api/training-center/trainees/{id}` or `PUT /v1/api/training-center/trainees/{id}`

**Changes**:
- Added `nationality` as a required field
- **All fields are now required** (previously optional for updates)
- Files (`id_image` and `card_image`) are now required on update

**Request Body** (multipart/form-data):
- `first_name` (required)
- `last_name` (required)
- `email` (required, unique, except for current trainee)
- `phone` (required)
- `nationality` (required) - **NEW**
- `id_number` (required, unique, except for current trainee)
- `id_image` (required, file)
- `card_image` (required, file)
- `enrolled_classes` (optional, array of training class IDs)
- `status` (optional)

**Response**: 
- Status Code: 200 OK
- Returns the updated trainee object

---

### 3. Get Trainee List
**Endpoint**: `GET /v1/api/training-center/trainees`

**Changes**:
- Response now includes `nationality` field for each trainee

**Response Fields**:
- Each trainee object now includes:
  - `id`
  - `first_name`
  - `last_name`
  - `email`
  - `phone`
  - `nationality` - **NEW**
  - `id_number`
  - `id_image_url`
  - `card_image_url`
  - `status`
  - `training_center_id`
  - `created_at`
  - `updated_at`

---

### 4. Get Trainee Details
**Endpoint**: `GET /v1/api/training-center/trainees/{id}`

**Changes**:
- Response now includes `nationality` field

**Response Fields**:
- Same as Get Trainee List, with additional relationship data (training classes, course, instructor)

---

## Validation Rules

### Create Trainee
- All 8 fields are required
- `email` must be unique (not used by another trainee)
- `id_number` must be unique (not used by another trainee)
- `id_image` and `card_image` must be valid files (JPEG, JPG, PNG, or PDF)
- File size must not exceed 10MB

### Update Trainee
- All 8 fields are required
- `email` must be unique (except for the current trainee being updated)
- `id_number` must be unique (except for the current trainee being updated)
- `id_image` and `card_image` must be valid files (JPEG, JPG, PNG, or PDF)
- File size must not exceed 10MB

---

## Error Responses

### 422 Validation Error
If any required field is missing or invalid:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["The field name field is required."]
  }
}
```

### 409 Conflict
If email or ID number already exists:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

---

## Migration Required

**Important**: Before using the updated API, you must run the database migration:

```bash
php artisan migrate
```

This will add the `nationality` column to the `trainees` table.

---

## Frontend Implementation Checklist

- [ ] Update create trainee form to include `nationality` field
- [ ] Update update trainee form to include `nationality` field
- [ ] Make all fields required in both create and update forms
- [ ] Update form validation to ensure all fields are filled
- [ ] Update trainee list display to show `nationality` field
- [ ] Update trainee detail view to show `nationality` field
- [ ] Ensure file upload fields (`id_image` and `card_image`) are required
- [ ] Update error handling to display validation errors for all required fields
- [ ] Test create trainee with all required fields
- [ ] Test update trainee with all required fields
- [ ] Test validation errors when fields are missing

---

## Notes

1. **File Uploads**: Both `id_image` and `card_image` must be sent as multipart/form-data files. They cannot be sent as URLs or base64 strings.

2. **Nationality Format**: The nationality field accepts any string value. Consider using country codes (e.g., "US", "UK", "SA") or full country names based on your application's requirements.

3. **Backward Compatibility**: Existing trainees without a nationality value will need to be updated. The migration adds the field as nullable initially, but the API requires it for all new and updated records.

4. **Search Functionality**: The search functionality in the trainee list endpoint remains unchanged and does not search by nationality. This can be added in a future update if needed.

---

## Support

For questions or issues related to these changes, please contact the backend development team.

