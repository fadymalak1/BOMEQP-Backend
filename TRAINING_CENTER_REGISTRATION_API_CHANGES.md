# Training Center Registration API Changes

## Overview
The training center registration endpoint has been enhanced to include a comprehensive registration form with multiple sections covering company information, addresses, contacts, and additional details. This document outlines all the changes and requirements for the registration process.

---

## Registration Endpoint

**POST** `/auth/register`

**Content-Type**: `multipart/form-data` (supports file uploads)

---

## Registration Form Structure

### 1. Basic User Information (Required for All Users)

All users must provide:
- **Name**: User's full name
- **Email**: User's email address (must be unique)
- **Password**: Password (minimum 8 characters)
- **Password Confirmation**: Password confirmation (must match password)
- **Role**: User role (`training_center_admin` or `acc_admin`)

---

### 2. Company Information (Required for Training Centers)

When registering as a training center (`role: training_center_admin`), the following company information is required:

#### Required Fields:
- **Company Name**: The official name of the training center company
- **Company Email Address**: The company's primary email address
- **Telephone Number**: The company's main telephone number
- **Training Provider Type**: Type of training provider (Dropdown options: `Training Center`, `Institute`, `University`)

#### Optional Fields:
- **Website**: Company website URL
- **Fax**: Company fax number

---

### 3. Physical Address (Required for Training Centers)

All training centers must provide their physical address:

- **Address**: Street address
- **City**: City name
- **Country**: Country name
- **Postal Code**: Postal/ZIP code

---

### 4. Mailing Address (Conditional for Training Centers)

Training centers can specify whether their mailing address is the same as their physical address:

#### Checkbox Option:
- **Same as Physical Address**: If checked, the mailing address fields are automatically populated from the physical address

#### If "Same as Physical Address" is Unchecked:
The following fields become required:
- **Mailing Address**: Mailing street address
- **Mailing City**: Mailing city
- **Mailing Country**: Mailing country
- **Mailing Postal Code**: Mailing postal/ZIP code

---

### 5. Primary Contact (Required for Training Centers)

Every training center must provide primary contact information:

- **Title**: Contact title (Dropdown: `Mr.`, `Mrs.`, `Eng.`, `Prof.`)
- **First Name**: Primary contact's first name
- **Last Name**: Primary contact's last name
- **Email Address**: Primary contact's email address
- **Country**: Primary contact's country
- **Mobile Number**: Primary contact's mobile phone number

---

### 6. Secondary Contact (Optional for Training Centers)

Training centers can optionally add a secondary contact:

#### Checkbox Option:
- **Add Secondary Contact**: When checked, secondary contact fields become visible and required

#### If "Add Secondary Contact" is Checked:
The following fields become required:
- **Title**: Secondary contact title (Dropdown: `Mr.`, `Mrs.`, `Eng.`, `Prof.`)
- **First Name**: Secondary contact's first name
- **Last Name**: Secondary contact's last name
- **Email Address**: Secondary contact's email address
- **Country**: Secondary contact's country
- **Mobile Number**: Secondary contact's mobile phone number

---

### 7. Additional Information (Required for Training Centers)

Training centers must provide additional documentation and information:

#### Required Fields:
- **Company GOV Registry Number**: Government registration number for the company
- **Company Registration Certificate**: File upload (PDF, JPG, JPEG, or PNG, maximum 10MB)

#### Optional Fields:
- **Facility Floorplan**: File upload (PDF, JPG, JPEG, or PNG, maximum 10MB)
- **Interested Fields**: Multi-select checkboxes (Options: `QHSE`, `Food Safety`, `Management`)
- **How did you hear about us?**: Text field for feedback

---

### 8. Agreements (Required for Training Centers)

Training centers must accept two agreements:

#### Required Checkboxes:
1. **I agree to receive other communications from (Accreditation Name)**
   - Must be checked (accepted)
   - Links to Privacy Policy

2. **I confirm that I have read, understood, and accepted the terms and conditions**
   - Must be checked (accepted)

---

## Validation Rules

### Conditional Validation

1. **Mailing Address Fields**:
   - Required only if "Same as Physical Address" checkbox is unchecked
   - If checked, physical address values are automatically used

2. **Secondary Contact Fields**:
   - Required only if "Add Secondary Contact" checkbox is checked
   - All secondary contact fields must be provided together

3. **File Uploads**:
   - Company Registration Certificate: Required, must be PDF, JPG, JPEG, or PNG, maximum 10MB
   - Facility Floorplan: Optional, must be PDF, JPG, JPEG, or PNG, maximum 10MB

4. **Agreement Checkboxes**:
   - Both must be checked (accepted) to proceed with registration
   - Validation ensures these are boolean `true` values

---

## Registration Process Flow

1. **User submits registration form** with all required fields
2. **System validates** all fields based on role and conditional requirements
3. **If validation passes**:
   - User account is created with `pending` status
   - Training center record is created with all provided information
   - File uploads are processed and stored
   - Mailing address is automatically populated if "same as physical" is checked
   - Secondary contact is saved only if checkbox is checked
   - Agreement checkboxes are stored
4. **Admin notification** is sent about the new training center application
5. **Response** includes user object and authentication token

---

## Response Format

### Success Response (201 Created)

```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "training_center_admin",
    "status": "pending"
  },
  "token": "1|xxxxxxxxxxxxx"
}
```

### Validation Error Response (422 Unprocessable Entity)

```json
{
  "errors": {
    "company_name": ["The company name field is required."],
    "primary_contact_email": ["The primary contact email field is required."],
    "agreed_to_terms_and_conditions": ["The agreed to terms and conditions field must be accepted."]
  }
}
```

---

## Field Mapping

### User Account Fields
- `name` → User's full name
- `email` → User's email (also used for training center email)
- `password` → Hashed password
- `role` → Set to `training_center_admin`
- `status` → Set to `pending` (requires admin approval)

### Training Center Fields

**Company Information:**
- `name` → Company name
- `legal_name` → Company name (same as name)
- `email` → Company email address
- `phone` → Telephone number
- `website` → Company website (optional)
- `fax` → Fax number (optional)
- `training_provider_type` → Training provider type

**Physical Address:**
- `address` → Physical address
- `city` → Physical city
- `country` → Physical country
- `physical_postal_code` → Postal code

**Mailing Address:**
- `mailing_same_as_physical` → Boolean flag
- `mailing_address` → Mailing address (or copied from physical if same)
- `mailing_city` → Mailing city (or copied from physical if same)
- `mailing_country` → Mailing country (or copied from physical if same)
- `mailing_postal_code` → Mailing postal code (or copied from physical if same)

**Primary Contact:**
- `primary_contact_title` → Contact title
- `primary_contact_first_name` → First name
- `primary_contact_last_name` → Last name
- `primary_contact_email` → Email address
- `primary_contact_country` → Country
- `primary_contact_mobile` → Mobile number

**Secondary Contact:**
- `has_secondary_contact` → Boolean flag
- `secondary_contact_title` → Contact title (if secondary contact exists)
- `secondary_contact_first_name` → First name (if secondary contact exists)
- `secondary_contact_last_name` → Last name (if secondary contact exists)
- `secondary_contact_email` → Email address (if secondary contact exists)
- `secondary_contact_country` → Country (if secondary contact exists)
- `secondary_contact_mobile` → Mobile number (if secondary contact exists)

**Additional Information:**
- `company_gov_registry_number` → Government registry number
- `company_registration_certificate_url` → URL of uploaded certificate file
- `facility_floorplan_url` → URL of uploaded floorplan file (if provided)
- `interested_fields` → JSON array of selected fields
- `how_did_you_hear_about_us` → Text response

**Agreements:**
- `agreed_to_receive_communications` → Boolean (must be true)
- `agreed_to_terms_and_conditions` → Boolean (must be true)

---

## File Upload Handling

### Company Registration Certificate
- **Storage Location**: `training_centers/{id}/documents/`
- **File Naming**: Timestamp + entity ID + random string + original filename
- **Supported Formats**: PDF, JPG, JPEG, PNG
- **Maximum Size**: 10MB
- **Required**: Yes

### Facility Floorplan
- **Storage Location**: `training_centers/{id}/documents/`
- **File Naming**: Timestamp + entity ID + random string + original filename
- **Supported Formats**: PDF, JPG, JPEG, PNG
- **Maximum Size**: 10MB
- **Required**: No

---

## Important Notes

1. **Registration Status**: All training centers are created with `pending` status and require group admin approval before they can access the system.

2. **Email Uniqueness**: The email address must be unique across all users in the system.

3. **Password Requirements**: Password must be at least 8 characters long and match the confirmation field.

4. **Conditional Fields**: Fields marked as conditional are only required when their corresponding checkbox is checked or unchecked (depending on the field).

5. **File Uploads**: Files are uploaded to the public storage disk and URLs are stored in the database. Files are organized by training center ID.

6. **Mailing Address Logic**: If "Same as Physical Address" is checked, the system automatically copies physical address values to mailing address fields.

7. **Secondary Contact**: If "Add Secondary Contact" is unchecked, all secondary contact fields are set to null and not stored.

8. **Interested Fields**: This field accepts an array of strings. Valid values are: `QHSE`, `Food Safety`, `Management`.

9. **Agreement Validation**: Both agreement checkboxes must be explicitly accepted (set to `true`) for registration to proceed.

10. **Backward Compatibility**: Existing registration flow for ACC admins remains unchanged. Only training center registration has been enhanced.

---

## Migration Requirements

A database migration is required to add agreement fields to the training centers table:

**Migration**: `2026_01_28_000003_add_agreement_fields_to_training_centers_table.php`

This migration adds:
- `agreed_to_receive_communications` (boolean, default: false)
- `agreed_to_terms_and_conditions` (boolean, default: false)

---

## Frontend Implementation Guidelines

### Form Structure
1. **Multi-step Form Recommended**: Consider breaking the form into sections for better user experience
2. **Conditional Field Display**: Show/hide fields based on checkbox states
3. **File Upload UI**: Provide clear file upload interfaces with size and format restrictions
4. **Validation Feedback**: Show validation errors immediately for better UX
5. **Progress Indicator**: Show form completion progress

### Conditional Logic
- **Mailing Address Section**: Hide mailing address fields when "Same as Physical Address" is checked
- **Secondary Contact Section**: Show secondary contact fields only when "Add Secondary Contact" is checked
- **Required Field Indicators**: Mark required fields with asterisks (*)

### File Upload Handling
- Display file size limits clearly
- Show accepted file formats
- Provide file preview after upload
- Show upload progress
- Allow file removal before submission

### Validation
- Validate email formats client-side
- Check file sizes before upload
- Validate required fields before submission
- Show clear error messages for each field

---

## Example Registration Request

### Form Data Structure

**Basic Information:**
- name: "John Doe"
- email: "john@abctraining.com"
- password: "SecurePass123"
- password_confirmation: "SecurePass123"
- role: "training_center_admin"

**Company Information:**
- company_name: "ABC Training Center"
- company_email: "info@abctraining.com"
- telephone_number: "+1234567890"
- website: "https://www.abctraining.com"
- fax: "+1234567891"
- training_provider_type: "Training Center"

**Physical Address:**
- address: "123 Main Street"
- city: "New York"
- country: "USA"
- postal_code: "10001"

**Mailing Address:**
- mailing_same_as_physical: true
- (mailing address fields are automatically populated)

**Primary Contact:**
- primary_contact_title: "Mr."
- primary_contact_first_name: "John"
- primary_contact_last_name: "Doe"
- primary_contact_email: "john.doe@abctraining.com"
- primary_contact_country: "USA"
- primary_contact_mobile: "+1234567890"

**Secondary Contact:**
- has_secondary_contact: false
- (secondary contact fields are not required)

**Additional Information:**
- company_gov_registry_number: "REG123456"
- company_registration_certificate: [File Upload]
- facility_floorplan: [File Upload - Optional]
- interested_fields: ["QHSE", "Food Safety"]
- how_did_you_hear_about_us: "Google Search"

**Agreements:**
- agreed_to_receive_communications: true
- agreed_to_terms_and_conditions: true

---

## Error Handling

### Common Validation Errors

1. **Missing Required Fields**: All required fields must be provided
2. **Invalid Email Format**: Email addresses must be valid format
3. **Password Mismatch**: Password and confirmation must match
4. **File Size Exceeded**: Files must not exceed 10MB
5. **Invalid File Format**: Only PDF, JPG, JPEG, PNG are accepted
6. **Agreements Not Accepted**: Both agreement checkboxes must be checked
7. **Conditional Fields Missing**: Conditional fields must be provided when their checkbox is checked/unchecked

### Error Response Format

All validation errors are returned in a structured format with field names as keys and error messages as arrays:

```json
{
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

---

## Summary of Benefits

1. **Comprehensive Data Collection**: All necessary information is collected during registration
2. **Better Organization**: Information is organized into logical sections
3. **Flexible Address Handling**: Supports both same and different mailing addresses
4. **Optional Secondary Contact**: Allows for additional contact person if needed
5. **File Documentation**: Supports document uploads for verification
6. **Clear Validation**: Conditional validation ensures data integrity
7. **User-Friendly**: Conditional fields reduce form complexity when not needed
8. **Compliance**: Agreement checkboxes ensure legal compliance

---

## Notes for Frontend Developers

1. **Form Type**: Use `multipart/form-data` encoding for file uploads
2. **Conditional Rendering**: Implement show/hide logic for conditional fields
3. **File Validation**: Validate file size and type before upload
4. **Progress Tracking**: Consider showing form completion progress
5. **Error Display**: Display validation errors near relevant fields
6. **Success Handling**: Handle successful registration and redirect appropriately
7. **Loading States**: Show loading indicators during form submission
8. **File Preview**: Allow users to preview uploaded files before submission

---

## Backward Compatibility

- ACC Admin registration remains unchanged
- Existing training center registrations continue to work
- All new fields are optional for backward compatibility (except when role is training_center_admin)
- The endpoint supports both old and new registration formats

