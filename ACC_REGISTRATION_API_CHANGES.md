# ACC Registration API Changes

## Overview
The ACC (Accreditation Body) registration endpoint has been enhanced to include a comprehensive registration form with multiple sections covering accreditation body information, addresses, contacts with passport uploads, and additional details. This document outlines all the changes and requirements for the ACC registration process.

---

## Registration Endpoint

**POST** `/auth/register`

**Content-Type**: `multipart/form-data` (supports file uploads)

**Role**: `acc_admin`

---

## Registration Form Structure

### 1. Basic User Information (Required for All Users)

All users must provide:
- **Name**: User's full name
- **Email**: User's email address (must be unique)
- **Password**: Password (minimum 8 characters)
- **Password Confirmation**: Password confirmation (must match password)
- **Role**: User role (must be `acc_admin` for ACC registration)

---

### 2. Accreditation Body Information (Required for ACC)

When registering as an ACC (`role: acc_admin`), the following accreditation body information is required:

#### Required Fields:
- **Legal Name**: The official legal name of the accreditation body
- **Email Address**: The accreditation body's primary email address
- **Telephone Number**: The accreditation body's main telephone number

#### Optional Fields:
- **Website**: Accreditation body website URL
- **Fax**: Accreditation body fax number

---

### 3. Physical Address (Required for ACC)

All ACCs must provide their physical address:

- **Address**: Street address
- **City**: City name
- **Country**: Country name
- **Postal Code**: Postal/ZIP code

---

### 4. Mailing Address (Conditional for ACC)

ACCs can specify whether their mailing address is the same as their physical address:

#### Checkbox Option:
- **Same as Physical Address**: If checked, the mailing address fields are automatically populated from the physical address

#### If "Same as Physical Address" is Unchecked:
The following fields become required:
- **Mailing Address**: Mailing street address
- **Mailing City**: Mailing city
- **Mailing Country**: Mailing country
- **Mailing Postal Code**: Mailing postal/ZIP code

---

### 5. Primary Contact (Required for ACC)

Every ACC must provide primary contact information:

- **Title**: Contact title (Dropdown: `Mr.`, `Mrs.`, `Eng.`, `Prof.`)
- **First Name**: Primary contact's first name
- **Last Name**: Primary contact's last name
- **Email Address**: Primary contact's email address
- **Country**: Primary contact's country
- **Mobile Number**: Primary contact's mobile phone number
- **Upload Passport Copy**: File upload (PDF, JPG, JPEG, or PNG, maximum 10MB) - **Required**

---

### 6. Secondary Contact (Required for ACC)

**Important**: Unlike training centers where secondary contact is optional, ACCs **must** provide secondary contact information:

- **Title**: Secondary contact title (Dropdown: `Mr.`, `Mrs.`, `Eng.`, `Prof.`)
- **First Name**: Secondary contact's first name
- **Last Name**: Secondary contact's last name
- **Email Address**: Secondary contact's email address
- **Country**: Secondary contact's country
- **Mobile Number**: Secondary contact's mobile phone number
- **Upload Passport Copy**: File upload (PDF, JPG, JPEG, or PNG, maximum 10MB) - **Required**

---

### 7. Additional Information (Required for ACC)

ACCs must provide additional documentation and information:

#### Required Fields:
- **Company GOV Registry Number**: Government registration number for the accreditation body
- **Upload Company Registration Certificate**: File upload (PDF, JPG, JPEG, or PNG, maximum 10MB)

#### Optional Fields:
- **How did you hear about us?**: Text field for feedback

---

### 8. Agreements (Required for ACC)

ACCs must accept two agreements:

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
   - **All secondary contact fields are required** for ACC registration (unlike training centers)
   - This includes all contact information and passport upload

3. **File Uploads**:
   - Primary Contact Passport: Required, must be PDF, JPG, JPEG, or PNG, maximum 10MB
   - Secondary Contact Passport: Required, must be PDF, JPG, JPEG, or PNG, maximum 10MB
   - Company Registration Certificate: Required, must be PDF, JPG, JPEG, or PNG, maximum 10MB

4. **Agreement Checkboxes**:
   - Both must be checked (accepted) to proceed with registration
   - Validation ensures these are boolean `true` values

---

## Registration Process Flow

1. **User submits registration form** with all required fields
2. **System validates** all fields based on role and conditional requirements
3. **If validation passes**:
   - User account is created with `pending` status
   - ACC record is created with all provided information
   - File uploads are processed and stored (passports and certificate)
   - Mailing address is automatically populated if "same as physical" is checked
   - Secondary contact information is saved (required for ACC)
   - Agreement checkboxes are stored
4. **Admin notification** is sent about the new ACC application
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
    "role": "acc_admin",
    "status": "pending"
  },
  "token": "1|xxxxxxxxxxxxx"
}
```

### Validation Error Response (422 Unprocessable Entity)

```json
{
  "errors": {
    "legal_name": ["The legal name field is required."],
    "primary_contact_passport": ["The primary contact passport field is required."],
    "secondary_contact_passport": ["The secondary contact passport field is required."],
    "agreed_to_terms_and_conditions": ["The agreed to terms and conditions field must be accepted."]
  }
}
```

---

## Field Mapping

### User Account Fields
- `name` → User's full name
- `email` → User's email
- `password` → Hashed password
- `role` → Set to `acc_admin`
- `status` → Set to `pending` (requires admin approval)

### ACC Fields

**Accreditation Body Information:**
- `name` → Legal name
- `legal_name` → Legal name (same as name)
- `email` → Accreditation body email address
- `phone` → Telephone number
- `website` → Website (optional)
- `fax` → Fax number (optional)

**Physical Address:**
- `address` → Physical address (legacy field)
- `country` → Physical country (legacy field)
- `physical_street` → Physical address
- `physical_city` → Physical city
- `physical_country` → Physical country
- `physical_postal_code` → Postal code

**Mailing Address:**
- `mailing_same_as_physical` → Boolean flag
- `mailing_street` → Mailing address (or copied from physical if same)
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
- `primary_contact_passport_url` → URL of uploaded passport file

**Secondary Contact:**
- `secondary_contact_title` → Contact title (required)
- `secondary_contact_first_name` → First name (required)
- `secondary_contact_last_name` → Last name (required)
- `secondary_contact_email` → Email address (required)
- `secondary_contact_country` → Country (required)
- `secondary_contact_mobile` → Mobile number (required)
- `secondary_contact_passport_url` → URL of uploaded passport file (required)

**Additional Information:**
- `company_gov_registry_number` → Government registry number
- `company_registration_certificate_url` → URL of uploaded certificate file
- `how_did_you_hear_about_us` → Text response (optional)

**Agreements:**
- `agreed_to_receive_communications` → Boolean (must be true)
- `agreed_to_terms_and_conditions` → Boolean (must be true)

---

## File Upload Handling

### Primary Contact Passport
- **Storage Location**: `accs/{id}/documents/`
- **File Naming**: Timestamp + entity ID + random string + original filename
- **Supported Formats**: PDF, JPG, JPEG, PNG
- **Maximum Size**: 10MB
- **Required**: Yes

### Secondary Contact Passport
- **Storage Location**: `accs/{id}/documents/`
- **File Naming**: Timestamp + entity ID + random string + original filename
- **Supported Formats**: PDF, JPG, JPEG, PNG
- **Maximum Size**: 10MB
- **Required**: Yes

### Company Registration Certificate
- **Storage Location**: `accs/{id}/documents/`
- **File Naming**: Timestamp + entity ID + random string + original filename
- **Supported Formats**: PDF, JPG, JPEG, PNG
- **Maximum Size**: 10MB
- **Required**: Yes

---

## Important Notes

1. **Registration Status**: All ACCs are created with `pending` status and require group admin approval before they can access the system.

2. **Email Uniqueness**: The email address must be unique across all users in the system.

3. **Password Requirements**: Password must be at least 8 characters long and match the confirmation field.

4. **Conditional Fields**: Mailing address fields are only required when "Same as Physical Address" is unchecked.

5. **Secondary Contact Requirement**: Unlike training centers, ACCs **must** provide secondary contact information. This is a mandatory requirement.

6. **File Uploads**: Files are uploaded to the public storage disk and URLs are stored in the database. Files are organized by ACC ID.

7. **Mailing Address Logic**: If "Same as Physical Address" is checked, the system automatically copies physical address values to mailing address fields.

8. **Passport Uploads**: Both primary and secondary contacts must upload passport copies. These are required documents.

9. **Agreement Validation**: Both agreement checkboxes must be explicitly accepted (set to `true`) for registration to proceed.

10. **Backward Compatibility**: The system maintains backward compatibility with legacy `address` and `country` fields while using the new `physical_street` and `physical_country` fields.

---

## Differences from Training Center Registration

### Key Differences:

1. **Secondary Contact**: 
   - **ACC**: Required (all fields mandatory)
   - **Training Center**: Optional (only required if checkbox is checked)

2. **Passport Uploads**:
   - **ACC**: Required for both primary and secondary contacts
   - **Training Center**: Not required

3. **Additional Fields**:
   - **ACC**: Does not have "Interested Fields" or "Facility Floorplan" fields
   - **Training Center**: Has these optional fields

4. **Training Provider Type**:
   - **ACC**: Not applicable
   - **Training Center**: Required field (Training Center, Institute, or University)

5. **Company Name vs Legal Name**:
   - **ACC**: Uses "Legal Name" field
   - **Training Center**: Uses "Company Name" field

---

## Example Registration Request

### Form Data Structure

**Basic Information:**
- name: "John Doe"
- email: "john@abcaccreditation.com"
- password: "SecurePass123"
- password_confirmation: "SecurePass123"
- role: "acc_admin"

**Accreditation Body Information:**
- legal_name: "ABC Accreditation Body"
- acc_email: "info@abcaccreditation.com"
- telephone_number: "+1234567890"
- website: "https://www.abcaccreditation.com"
- fax: "+1234567891"

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
- primary_contact_email: "john.doe@abcaccreditation.com"
- primary_contact_country: "USA"
- primary_contact_mobile: "+1234567890"
- primary_contact_passport: [File Upload - Required]

**Secondary Contact:**
- secondary_contact_title: "Mrs."
- secondary_contact_first_name: "Jane"
- secondary_contact_last_name: "Smith"
- secondary_contact_email: "jane.smith@abcaccreditation.com"
- secondary_contact_country: "USA"
- secondary_contact_mobile: "+1234567891"
- secondary_contact_passport: [File Upload - Required]

**Additional Information:**
- company_gov_registry_number: "REG123456"
- company_registration_certificate: [File Upload - Required]
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
7. **Missing Secondary Contact**: All secondary contact fields are required for ACC
8. **Missing Passport Uploads**: Both primary and secondary contact passports are required
9. **Conditional Fields Missing**: Mailing address fields must be provided when "same as physical" is unchecked

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
4. **Mandatory Secondary Contact**: Ensures ACCs have backup contact information
5. **Document Verification**: Passport uploads enable identity verification
6. **Clear Validation**: Conditional validation ensures data integrity
7. **User-Friendly**: Conditional fields reduce form complexity when not needed
8. **Compliance**: Agreement checkboxes ensure legal compliance
9. **Documentation**: Company registration certificate provides official verification

---

## Notes for Frontend Developers

1. **Form Type**: Use `multipart/form-data` encoding for file uploads
2. **Conditional Rendering**: Implement show/hide logic for mailing address fields
3. **File Validation**: Validate file size and type before upload (especially for passports)
4. **Progress Tracking**: Consider showing form completion progress
5. **Error Display**: Display validation errors near relevant fields
6. **Success Handling**: Handle successful registration and redirect appropriately
7. **Loading States**: Show loading indicators during form submission
8. **File Preview**: Allow users to preview uploaded files before submission
9. **Secondary Contact**: Always show secondary contact section for ACC (it's required, not optional)
10. **Passport Uploads**: Clearly indicate that passport uploads are required for both contacts

---

## Backward Compatibility

- Existing ACC registrations continue to work
- Legacy `address` and `country` fields are populated for backward compatibility
- New physical address fields (`physical_street`, `physical_city`, etc.) are used alongside legacy fields
- The endpoint supports both old and new registration formats

---

## Registration Status Flow

1. **Registration**: ACC is created with `pending` status
2. **Admin Review**: Group admin reviews the application
3. **Approval**: Admin approves the application (status changes to `approved`)
4. **Activation**: Admin activates the ACC (status changes to `active`)
5. **Access**: ACC can now access the system and start work

---

## Field Requirements Summary

### Always Required:
- Basic user information (name, email, password)
- Accreditation body legal name
- Accreditation body email
- Telephone number
- Physical address (all fields)
- Primary contact (all fields including passport)
- Secondary contact (all fields including passport)
- Company GOV registry number
- Company registration certificate upload
- Both agreement checkboxes

### Conditionally Required:
- Mailing address fields (if "same as physical" is unchecked)
- Website (optional)
- Fax (optional)
- How did you hear about us (optional)

---

## File Upload Best Practices

1. **File Size Validation**: Check file size client-side before upload
2. **File Type Validation**: Validate file types before upload
3. **Progress Indicators**: Show upload progress for better UX
4. **Error Handling**: Handle upload errors gracefully
5. **File Preview**: Allow users to preview uploaded files
6. **File Replacement**: Allow users to replace uploaded files before submission
7. **Clear Instructions**: Provide clear instructions about file requirements

---

## Security Considerations

1. **File Validation**: All uploaded files are validated server-side
2. **File Storage**: Files are stored securely in the public storage disk
3. **Access Control**: Files are organized by ACC ID for proper access control
4. **File Naming**: Files are renamed with timestamps and random strings to prevent conflicts
5. **Size Limits**: File size limits prevent abuse and ensure system performance

---

## Migration Requirements

No additional database migrations are required for ACC registration. All necessary fields already exist in the `accs` table from previous migrations.

---

## Testing Checklist

When testing ACC registration, verify:

- [ ] All required fields are validated
- [ ] Conditional mailing address validation works correctly
- [ ] File uploads work for all three files (two passports + certificate)
- [ ] File size and type validation works
- [ ] Mailing address auto-population works when checkbox is checked
- [ ] Secondary contact is always required (unlike training center)
- [ ] Agreement checkboxes must be accepted
- [ ] Registration creates user with pending status
- [ ] Registration creates ACC record with all fields
- [ ] Admin notification is sent
- [ ] Response includes user and token
- [ ] Error messages are clear and helpful

---

## Support and Troubleshooting

### Common Issues:

1. **File Upload Fails**: Check file size (must be under 10MB) and format (PDF, JPG, JPEG, PNG only)
2. **Validation Errors**: Ensure all required fields are provided
3. **Secondary Contact Required**: Remember that ACC requires secondary contact (unlike training center)
4. **Passport Uploads**: Both primary and secondary contacts must upload passports
5. **Agreement Checkboxes**: Both must be explicitly checked (set to `true`)

### Getting Help:

- Check validation error messages for specific field requirements
- Verify file uploads meet size and format requirements
- Ensure all conditional fields are provided when their conditions are met
- Review the field mapping section to understand how form fields map to database fields

