# Instructor/Assessor Fields Update - API Changes Documentation

## Overview
This document outlines the changes made to the Instructor/Assessor API endpoints. All instructor fields are now **required** when creating or updating an instructor/assessor.

## Date
January 22, 2026

---

## Changes Summary

### New Fields Added
- **Date of Birth (D.O.B)**: A new required field to store the instructor's date of birth.
- **Passport Copy**: A new required field to upload the instructor's passport copy.

### Field Name Changes
- **Languages**: The field `specializations` is now referred to as `languages` in the API (backward compatible).

### All Fields Now Required
All instructor/assessor fields are now mandatory for both **create** and **update** operations.

---

## Updated Instructor/Assessor Fields

The following fields are **required** for all instructor/assessor operations:

1. **First Name** (`first_name`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes

2. **Last Name** (`last_name`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes

3. **E-mail Address** (`email`)
   - Type: Email
   - Format: Valid email address
   - Unique: Yes (must be unique across all instructors and users)
   - Required: Yes

4. **Date of Birth (D.O.B)** (`date_of_birth`)
   - Type: Date
   - Format: YYYY-MM-DD (e.g., "1990-01-15")
   - Validation: Must be before today's date
   - Required: Yes
   - **NEW FIELD**

5. **Phone No.** (`phone`)
   - Type: String
   - Max Length: 255 characters
   - Required: Yes

6. **Languages** (`languages`)
   - Type: Array of Strings
   - Min Items: 1
   - Example: `["English", "Arabic", "French"]`
   - Required: Yes
   - **Note**: Previously called `specializations`, now accepts both field names for backward compatibility

7. **Selection "Instructor or Assessor"** (`is_assessor`)
   - Type: Boolean
   - Values: 
     - `true` = Assessor
     - `false` = Instructor
   - Required: Yes

8. **Upload C.V + Supporting Certificates** (`cv`)
   - Type: File (multipart/form-data)
   - Allowed Types: PDF only
   - Max Size: 10MB
   - Required: Yes
   - **Note**: This file should contain both CV and supporting certificates

9. **Upload Passport Copy** (`passport`)
   - Type: File (multipart/form-data)
   - Allowed Types: JPEG, JPG, PNG, PDF
   - Max Size: 10MB
   - Required: Yes
   - **NEW FIELD**

---

## Affected API Endpoints

### 1. Create Instructor/Assessor
**Endpoint**: `POST /v1/api/training-center/instructors`

**Changes**:
- Added `date_of_birth` as a required field
- Added `passport` as a required file upload
- Changed `specializations` to `languages` (backward compatible)
- All fields are now required (previously some were optional)

**Request Body** (multipart/form-data):
- `first_name` (required, string)
- `last_name` (required, string)
- `email` (required, email, unique)
- `date_of_birth` (required, date, format: YYYY-MM-DD) - **NEW**
- `phone` (required, string)
- `languages` (required, array of strings, min: 1) - **UPDATED NAME**
- `is_assessor` (required, boolean)
- `cv` (required, file, PDF, max 10MB)
- `passport` (required, file, JPEG/PNG/PDF, max 10MB) - **NEW**

**Response**: 
- Status Code: 201 Created
- Returns the created instructor object
- Credentials are automatically sent to the instructor's email

---

### 2. Update Instructor/Assessor
**Endpoint**: `POST /v1/api/training-center/instructors/{id}` or `PUT /v1/api/training-center/instructors/{id}`

**Changes**:
- Added `date_of_birth` as a required field
- Added `passport` as a required file upload
- Changed `specializations` to `languages` (backward compatible)
- **All fields are now required** (previously optional for updates)
- Files (`cv` and `passport`) are now required on update

**Request Body** (multipart/form-data):
- `first_name` (required, string)
- `last_name` (required, string)
- `email` (required, email, unique, except for current instructor)
- `date_of_birth` (required, date, format: YYYY-MM-DD) - **NEW**
- `phone` (required, string)
- `languages` (required, array of strings, min: 1) - **UPDATED NAME**
- `is_assessor` (required, boolean)
- `cv` (required, file, PDF, max 10MB)
- `passport` (required, file, JPEG/PNG/PDF, max 10MB) - **NEW**

**Response**: 
- Status Code: 200 OK
- Returns the updated instructor object

---

### 3. Get Instructor List
**Endpoint**: `GET /v1/api/training-center/instructors`

**Changes**:
- Response now includes `date_of_birth` and `passport_image_url` fields for each instructor

**Response Fields**:
- Each instructor object now includes:
  - `id`
  - `first_name`
  - `last_name`
  - `email`
  - `phone`
  - `date_of_birth` - **NEW**
  - `id_number`
  - `cv_url`
  - `passport_image_url` - **NEW**
  - `photo_url`
  - `certificates_json`
  - `specializations` (contains languages)
  - `is_assessor`
  - `status`
  - `training_center_id`
  - `created_at`
  - `updated_at`

---

### 4. Get Instructor Details
**Endpoint**: `GET /v1/api/training-center/instructors/{id}`

**Changes**:
- Response now includes `date_of_birth` and `passport_image_url` fields

**Response Fields**:
- Same as Get Instructor List, with additional relationship data (training center, courses, authorizations)

---

## Validation Rules

### Create Instructor/Assessor
- All 9 fields are required
- `email` must be unique (not used by another instructor or user)
- `date_of_birth` must be a valid date before today
- `languages` must be an array with at least 1 language
- `is_assessor` must be a boolean (true for Assessor, false for Instructor)
- `cv` must be a valid PDF file (max 10MB)
- `passport` must be a valid file (JPEG, JPG, PNG, or PDF, max 10MB)

### Update Instructor/Assessor
- All 9 fields are required
- `email` must be unique (except for the current instructor being updated)
- `date_of_birth` must be a valid date before today
- `languages` must be an array with at least 1 language
- `is_assessor` must be a boolean
- `cv` must be a valid PDF file (max 10MB)
- `passport` must be a valid file (JPEG, JPG, PNG, or PDF, max 10MB)

---

## Field Mapping

### Languages Field
- **API Field Name**: `languages` (preferred)
- **Database Field**: `specializations`
- **Backward Compatibility**: The API still accepts `specializations` for backward compatibility, but `languages` is the preferred field name

### Instructor vs Assessor
- **Field**: `is_assessor`
- **Value**: `false` = Instructor, `true` = Assessor
- **Required**: Yes

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
If email already exists:

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

This will add the `date_of_birth` and `passport_image_url` columns to the `instructors` table.

---

## Frontend Implementation Checklist

- [ ] Update create instructor form to include `date_of_birth` field (date picker)
- [ ] Update create instructor form to include `passport` file upload field
- [ ] Update update instructor form to include `date_of_birth` field
- [ ] Update update instructor form to include `passport` file upload field
- [ ] Change `specializations` field label to "Languages" in UI
- [ ] Make all fields required in both create and update forms
- [ ] Update form validation to ensure all fields are filled
- [ ] Add date validation for `date_of_birth` (must be before today)
- [ ] Add array validation for `languages` (minimum 1 item)
- [ ] Update instructor list display to show `date_of_birth` and `passport_image_url`
- [ ] Update instructor detail view to show `date_of_birth` and `passport_image_url`
- [ ] Ensure file upload fields (`cv` and `passport`) are required
- [ ] Add file type validation for `cv` (PDF only)
- [ ] Add file type validation for `passport` (JPEG, PNG, PDF)
- [ ] Add file size validation (max 10MB for both files)
- [ ] Update error handling to display validation errors for all required fields
- [ ] Test create instructor with all required fields
- [ ] Test update instructor with all required fields
- [ ] Test validation errors when fields are missing
- [ ] Test file upload validation (type and size)

---

## Notes

1. **File Uploads**: Both `cv` and `passport` must be sent as multipart/form-data files. They cannot be sent as URLs or base64 strings.

2. **Date Format**: The `date_of_birth` field must be in `YYYY-MM-DD` format (e.g., "1990-01-15").

3. **Languages Field**: The API accepts both `languages` and `specializations` field names for backward compatibility, but `languages` is the preferred name going forward.

4. **CV File**: The CV file should contain both the instructor's CV and supporting certificates in a single PDF file.

5. **Passport File**: The passport file can be in JPEG, JPG, PNG, or PDF format.

6. **Instructor vs Assessor**: Use the `is_assessor` boolean field to distinguish between Instructor (`false`) and Assessor (`true`).

7. **Backward Compatibility**: Existing instructors without `date_of_birth` or `passport_image_url` will need to be updated. The migration adds these fields as nullable initially, but the API requires them for all new and updated records.

8. **Email Uniqueness**: The email must be unique across both the `instructors` table and the `users` table, as each instructor gets a user account created automatically.

---

## Support

For questions or issues related to these changes, please contact the backend development team.

