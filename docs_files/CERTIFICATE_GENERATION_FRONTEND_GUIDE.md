# Certificate Generation - Frontend Developer Guide

## Overview

This guide explains the simplified certificate generation workflow for Training Centers. The system has been streamlined to make certificate issuance easier: select an ACC, select a course, enter the student name, and generate the certificate.

## Workflow

The certificate generation process follows these simple steps:

1. **Get Authorized ACCs** - Retrieve list of ACCs the training center is authorized to work with
2. **Get Courses for Selected ACC** - Retrieve courses available from the selected ACC
3. **Generate Certificate** - Submit certificate request with ACC, course, and student information

## API Endpoints

### 1. Get Authorized ACCs

**Endpoint**: `GET /api/training-center/certificates/accs`

**Authentication**: Required (Sanctum token)

**Description**: Returns a list of ACCs that the training center is authorized to generate certificates for. Only ACCs with approved authorization status are returned.

**Response**:
- `accs`: Array of ACC objects
  - `id`: ACC identifier
  - `name`: ACC name
  - `logo_url`: ACC logo URL (optional)

**Use Case**: Display a dropdown or list of available ACCs for the user to select from.

---

### 2. Get Courses for ACC

**Endpoint**: `GET /api/training-center/courses?acc_id={acc_id}`

**Authentication**: Required (Sanctum token)

**Description**: Retrieves courses available from the selected ACC. This endpoint already exists and can be filtered by `acc_id` query parameter.

**Query Parameters**:
- `acc_id` (required): The ID of the selected ACC
- Additional optional filters: `sub_category_id`, `level`, `search`, `per_page`, `page`

**Response**:
- `courses`: Array of course objects with full course details
- `pagination`: Pagination metadata

**Use Case**: After the user selects an ACC, display available courses for that ACC.

---

### 3. Generate Certificate

**Endpoint**: `POST /api/training-center/certificates`

**Authentication**: Required (Sanctum token)

**Description**: Generates and issues a certificate. The system automatically selects the appropriate template and generates certificate data.

**Request Body** (JSON):

**Required Fields**:
- `acc_id` (integer): ID of the selected ACC
- `course_id` (integer): ID of the selected course
- `trainee_name` (string): Student name (max 255 characters)
- `issue_date` (date): Certificate issue date (format: YYYY-MM-DD)

**Optional Fields**:
- `class_id` (integer): ID of the training class (if applicable)
- `instructor_id` (integer): ID of the instructor (if applicable)
- `trainee_id_number` (string): Student ID number
- `expiry_date` (date): Certificate expiry date (must be after issue_date)

**Response** (Success - 201):
- `message`: Success message
- `certificate`: Certificate object with full details including:
  - Certificate number
  - Course information
  - Template information
  - PDF/image URL
  - Verification code
  - Status
  - Issue and expiry dates

**Error Responses**:
- `401`: Unauthenticated
- `403`: ACC not authorized or course not available
- `404`: ACC, Course, or Template not found
- `422`: Validation error (missing/invalid fields)
- `500`: Server error

---

## How It Works

### Automatic Template Selection

The system automatically finds the appropriate certificate template based on:
- The selected ACC (`acc_id`)
- The course's category (derived from `course.sub_category.category_id`)

The template must:
- Belong to the selected ACC
- Match the course's category
- Be active
- Have a background image configured
- Have template configuration (config_json) set up

If no matching template is found, the API returns a 404 error with a helpful message.

### Automatic Data Generation

The system automatically generates certificate data from:
- Student name (from user input)
- Course name (from course record)
- Certificate number (auto-generated in format: CERT-YYYY-XXXXXXXX)
- Issue date (from user input)
- Other course information

You no longer need to manually construct the `student_data` object - the system handles this automatically.

---

## User Interface Flow

### Step 1: Select ACC
- Display a dropdown/list of authorized ACCs
- Load ACCs from `GET /api/training-center/certificates/accs`
- Allow user to select one ACC

### Step 2: Select Course
- After ACC selection, load courses for that ACC
- Use `GET /api/training-center/courses?acc_id={selected_acc_id}`
- Display courses in a dropdown/list
- Allow user to select one course

### Step 3: Enter Student Information
- Display a form with the following fields:
  - **Student Name** (required)
  - **Issue Date** (required, date picker)
  - **Expiry Date** (optional, date picker)
  - **Student ID Number** (optional, text input)
  - **Class** (optional, if applicable)
  - **Instructor** (optional, if applicable)

### Step 4: Generate Certificate
- Submit the form to `POST /api/training-center/certificates`
- Show loading state while processing
- On success: Display certificate details and download link
- On error: Display appropriate error message

---

## Error Handling

### Common Error Scenarios

1. **ACC Not Authorized (403)**
   - Message: "ACC is not authorized for this training center"
   - Action: Refresh ACC list or contact administrator

2. **Course Not Found (404)**
   - Message: "Course does not belong to the selected ACC"
   - Action: Verify course selection matches selected ACC

3. **Template Not Found (404)**
   - Message: "No certificate template found for this ACC and course category"
   - Hint: "Please ensure the ACC has created a certificate template for this course category"
   - Action: Contact the ACC to create a template for this course category

4. **Validation Errors (422)**
   - Missing required fields
   - Invalid date formats
   - Expiry date before issue date
   - Action: Display field-specific error messages

5. **Generation Failure (500)**
   - Message: "Failed to generate certificate"
   - Action: Retry or contact support

---

## Form Validation

### Client-Side Validation (Recommended)

Before submitting the form, validate:

1. **ACC Selection**: Must be selected
2. **Course Selection**: Must be selected
3. **Student Name**: 
   - Required
   - Maximum 255 characters
   - Not empty after trimming
4. **Issue Date**: 
   - Required
   - Valid date format
   - Not in the future (if business rules require)
5. **Expiry Date** (if provided):
   - Valid date format
   - Must be after issue date

---

## User Experience Recommendations

### Loading States
- Show loading spinner when fetching ACCs
- Show loading spinner when fetching courses
- Show loading state during certificate generation (this may take a few seconds)

### Success Feedback
- Display success message
- Show certificate details (number, verification code)
- Provide download link for the certificate
- Option to generate another certificate

### Error Feedback
- Display clear, user-friendly error messages
- Highlight fields with validation errors
- Provide actionable guidance for resolving errors

### Progressive Disclosure
- Only show course selection after ACC is selected
- Only show the form after course is selected
- Consider disabling submit button until all required fields are filled

---

## Changes from Previous Implementation

### What Changed

1. **Simplified Request**: No longer need to provide `template_id` or `student_data`
2. **New Endpoint**: Added `GET /api/training-center/certificates/accs` for getting authorized ACCs
3. **Removed Endpoint**: The `GET /api/training-center/certificates/templates` endpoint is no longer needed

### What Stayed the Same

1. **Get Courses Endpoint**: Still uses the existing `/api/training-center/courses` endpoint
2. **Certificate Structure**: Certificate object structure remains the same
3. **Authentication**: Still requires Sanctum authentication
4. **Response Format**: Response format follows the same pattern

---

## Testing Checklist

When implementing the frontend, test the following scenarios:

- [ ] Loading and displaying authorized ACCs
- [ ] Loading and displaying courses for selected ACC
- [ ] Form validation (required fields, date formats)
- [ ] Successful certificate generation
- [ ] Error handling for unauthorized ACC
- [ ] Error handling for missing template
- [ ] Error handling for validation errors
- [ ] Display of certificate details after generation
- [ ] Download/access to generated certificate
- [ ] Loading states and user feedback
- [ ] Responsive design (mobile/tablet/desktop)

---

## Notes

- The certificate generation process may take a few seconds, so provide appropriate loading feedback
- Certificate templates are automatically selected based on ACC and course category - users don't need to select templates
- All date fields should use ISO 8601 format (YYYY-MM-DD)
- The system generates unique certificate numbers and verification codes automatically
- Certificates are generated as PNG images (PDF generation may be added in future updates)

