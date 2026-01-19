# ACC (Accreditation Body) Comprehensive Fields Update - API Changes Documentation

## Overview
This document outlines the comprehensive changes made to the ACC (Accreditation Body) API endpoints. New fields have been added across multiple sections: Company Information, Physical Address, Mailing Address, Primary Contact, Secondary Contact, Additional Information, and Agreement Checkboxes.

## Date
January 22, 2026

---

## Changes Summary

### New Fields Added
The ACC model now includes comprehensive fields organized into 7 main sections:

1. **Accreditation Body Information** - Enhanced with fax
2. **Physical Address** - Already exists, no changes
3. **Mailing Address** - Added checkbox for same as physical address
4. **Primary Contact** - New required section with contact details and passport upload
5. **Secondary Contact** - New required section with contact details and passport upload
6. **Additional Information** - New section with registry number, registration certificate, and how did you hear about us
7. **Agreement Checkboxes** - New required checkboxes for communications and terms acceptance

### Field Requirements
- Fields marked with (*) are **required**
- Fields without (*) are **optional**
- **Note**: Secondary Contact is **required** (not optional like Training Center)

---

## Field Structure

### 1. Accreditation Body Information (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Accreditation Legal Name * | `legal_name` | String | Yes | Legal name of the accreditation body |
| Website | `website` | String | No | Company website URL |
| Email address * | `email` | String | Yes | Company email (unique) |
| Telephone Number * | `phone` | String | Yes | Company phone number |
| Fax | `fax` | String | No | Company fax number |

**Note**: `legal_name`, `email`, and `phone` are existing fields that remain required. `website` is optional.

---

### 2. Physical Address (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Address * | `physical_street` | String | Yes | Physical street address |
| City * | `physical_city` | String | Yes | Physical city |
| Country * | `physical_country` | String | Yes | Physical country |
| Postal code * | `physical_postal_code` | String | Yes | Physical postal/zip code |

**Note**: All physical address fields already exist and remain required.

---

### 3. Mailing Address (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Same as Physical address | `mailing_same_as_physical` | Boolean | No | If true, copies physical address fields |
| Address | `mailing_street` | String | Conditional | Required if `mailing_same_as_physical` is false |
| City | `mailing_city` | String | Conditional | Required if `mailing_same_as_physical` is false |
| Country | `mailing_country` | String | Conditional | Required if `mailing_same_as_physical` is false |
| Postal code | `mailing_postal_code` | String | Conditional | Required if `mailing_same_as_physical` is false |

**Logic**: 
- If `mailing_same_as_physical` is `true`, the system automatically copies physical address fields to mailing address fields
- If `mailing_same_as_physical` is `false`, all mailing address fields become required

---

### 4. Primary Contact (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Title * | `primary_contact_title` | Enum | Yes | Options: "Mr.", "Mrs.", "Eng.", "Prof." |
| First Name * | `primary_contact_first_name` | String | Yes | Primary contact first name |
| Last Name * | `primary_contact_last_name` | String | Yes | Primary contact last name |
| E-mail address * | `primary_contact_email` | String | Yes | Primary contact email |
| Country * | `primary_contact_country` | String | Yes | Primary contact country |
| Mobile Number * | `primary_contact_mobile` | String | Yes | Primary contact mobile number |
| Upload passport copy * | `primary_contact_passport` | File | Yes | PDF, JPEG, PNG (max 10MB) |

---

### 5. Secondary Contact (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Title * | `secondary_contact_title` | Enum | Yes | Options: "Mr.", "Mrs.", "Eng.", "Prof." |
| First Name * | `secondary_contact_first_name` | String | Yes | Secondary contact first name |
| Last Name * | `secondary_contact_last_name` | String | Yes | Secondary contact last name |
| E-mail address * | `secondary_contact_email` | String | Yes | Secondary contact email |
| Country * | `secondary_contact_country` | String | Yes | Secondary contact country |
| Mobile Number * | `secondary_contact_mobile` | String | Yes | Secondary contact mobile number |
| Upload passport copy * | `secondary_contact_passport` | File | Yes | PDF, JPEG, PNG (max 10MB) |

**Important**: Secondary Contact is **required** (not optional). All fields must be provided.

---

### 6. Additional Information

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Company GOV Registry Number * | `company_gov_registry_number` | String | Yes | Government registry number |
| Upload the Company Registration Certificate * | `company_registration_certificate` | File | Yes | PDF, JPEG, PNG (max 10MB) |
| How did you hear about us? | `how_did_you_hear_about_us` | String | No | Text field |

---

### 7. Agreement Checkboxes (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| I agreed to receive other communications from (BOMEQP) * | `agreed_to_receive_communications` | Boolean | Yes | Must be true |
| I confirm that I have read, understood, and accepted the terms and conditions * | `agreed_to_terms_and_conditions` | Boolean | Yes | Must be true |

**Important**: Both checkboxes are **required** and must be set to `true`.

---

## Affected API Endpoints

### 1. Get ACC Profile
**Endpoint**: `GET /v1/api/acc/profile`

**Changes**:
- Response now includes all new fields across all 7 sections

**Response Structure**:
```json
{
  "profile": {
    "id": 1,
    "name": "ACC Name",
    "legal_name": "ACC Legal Name",
    "email": "acc@example.com",
    "phone": "+1234567890",
    "fax": "+1234567891",
    "website": "https://example.com",
    "physical_address": {
      "street": "123 Main Street",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345"
    },
    "mailing_address": {
      "street": "456 Mailing Street",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345",
      "same_as_physical": false
    },
    "primary_contact": {
      "title": "Mr.",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "country": "Egypt",
      "mobile": "+1234567890",
      "passport_url": "https://example.com/passport.pdf"
    },
    "secondary_contact": {
      "title": "Mrs.",
      "first_name": "Jane",
      "last_name": "Doe",
      "email": "jane@example.com",
      "country": "Egypt",
      "mobile": "+1234567891",
      "passport_url": "https://example.com/passport2.pdf"
    },
    "company_gov_registry_number": "REG123456",
    "company_registration_certificate_url": "https://example.com/cert.pdf",
    "how_did_you_hear_about_us": "Google Search",
    "agreed_to_receive_communications": true,
    "agreed_to_terms_and_conditions": true,
    "status": "active",
    ...
  }
}
```

---

### 2. Update ACC Profile
**Endpoint**: `POST /v1/api/acc/profile` or `PUT /v1/api/acc/profile`

**Changes**:
- Added validation for all new fields
- Added file upload handling for primary contact passport, secondary contact passport, and registration certificate
- Added logic to handle mailing address same as physical
- Added validation for agreement checkboxes

**Request Body** (multipart/form-data):
- All fields from all 7 sections can be updated
- File uploads: `primary_contact_passport`, `secondary_contact_passport`, `company_registration_certificate`, `logo`
- Agreement checkboxes must be boolean values

**Validation Rules**:
- Required fields must be provided when updating
- Conditional fields follow the logic described above
- File uploads: PDF, JPEG, PNG, max 10MB
- Agreement checkboxes must be true

**Response**: 
- Status Code: 200 OK
- Returns the updated profile with all fields

---

### 3. Admin - Get ACC Applications
**Endpoint**: `GET /v1/api/admin/accs/applications`

**Changes**:
- Response now includes all new fields for each ACC application

**Response Structure**:
- Each ACC object includes all fields from all 7 sections

---

### 4. Admin - Get ACC Details
**Endpoint**: `GET /v1/api/admin/accs/{id}`

**Changes**:
- Response now includes all new fields

**Response Structure**:
- ACC object includes all fields from all 7 sections

---

## Field Mapping and Logic

### Mailing Address Logic
When `mailing_same_as_physical` is `true`:
- System automatically copies:
  - `physical_street` → `mailing_street`
  - `physical_city` → `mailing_city`
  - `physical_country` → `mailing_country`
  - `physical_postal_code` → `mailing_postal_code`

When `mailing_same_as_physical` is `false`:
- All mailing address fields become required:
  - `mailing_street` (required)
  - `mailing_city` (required)
  - `mailing_country` (required)
  - `mailing_postal_code` (required)

### Secondary Contact Logic
**Important**: Secondary Contact is **always required** (unlike Training Center where it's optional). All secondary contact fields must be provided.

---

## Validation Rules

### Required Fields (Always Required)
1. **Accreditation Body Information**:
   - `legal_name` (Accreditation Legal Name)
   - `email` (Email address)
   - `phone` (Telephone Number)

2. **Physical Address**:
   - `physical_street`
   - `physical_city`
   - `physical_country`
   - `physical_postal_code`

3. **Mailing Address** (required if `mailing_same_as_physical` is false):
   - `mailing_street`
   - `mailing_city`
   - `mailing_country`
   - `mailing_postal_code`

4. **Primary Contact**:
   - `primary_contact_title`
   - `primary_contact_first_name`
   - `primary_contact_last_name`
   - `primary_contact_email`
   - `primary_contact_country`
   - `primary_contact_mobile`
   - `primary_contact_passport` (file upload)

5. **Secondary Contact** (Always Required):
   - `secondary_contact_title`
   - `secondary_contact_first_name`
   - `secondary_contact_last_name`
   - `secondary_contact_email`
   - `secondary_contact_country`
   - `secondary_contact_mobile`
   - `secondary_contact_passport` (file upload)

6. **Additional Information**:
   - `company_gov_registry_number`
   - `company_registration_certificate` (file upload)

7. **Agreement Checkboxes**:
   - `agreed_to_receive_communications` (must be true)
   - `agreed_to_terms_and_conditions` (must be true)

### Optional Fields
- `website`
- `fax`
- `how_did_you_hear_about_us`

---

## File Uploads

### Primary Contact Passport
- **Field**: `primary_contact_passport`
- **Type**: File upload
- **Allowed Types**: PDF, JPEG, JPG, PNG
- **Max Size**: 10MB
- **Required**: Yes
- **Storage**: Files are stored and a URL is returned in `primary_contact_passport_url`

### Secondary Contact Passport
- **Field**: `secondary_contact_passport`
- **Type**: File upload
- **Allowed Types**: PDF, JPEG, JPG, PNG
- **Max Size**: 10MB
- **Required**: Yes
- **Storage**: Files are stored and a URL is returned in `secondary_contact_passport_url`

### Company Registration Certificate
- **Field**: `company_registration_certificate`
- **Type**: File upload
- **Allowed Types**: PDF, JPEG, JPG, PNG
- **Max Size**: 10MB
- **Required**: Yes
- **Storage**: Files are stored and a URL is returned in `company_registration_certificate_url`

---

## Migration Required

**Important**: Before using the updated API, you must run the database migration:

```bash
php artisan migrate
```

This will add all new columns to the `accs` table.

---

## Frontend Implementation Checklist

### Accreditation Body Information Section
- [ ] Add `fax` field (optional text input)
- [ ] Ensure `legal_name`, `email`, `phone` are marked as required
- [ ] Keep `website` as optional

### Physical Address Section
- [ ] Ensure `physical_street`, `physical_city`, `physical_country`, `physical_postal_code` are marked as required
- [ ] All fields already exist, no new fields needed

### Mailing Address Section
- [ ] Add checkbox "Same as Physical Address" (`mailing_same_as_physical`)
- [ ] Add conditional fields (shown when checkbox is unchecked):
  - [ ] `mailing_street` (text input, required when checkbox unchecked)
  - [ ] `mailing_city` (text input, required when checkbox unchecked)
  - [ ] `mailing_country` (dropdown, required when checkbox unchecked)
  - [ ] `mailing_postal_code` (text input, required when checkbox unchecked)
- [ ] Implement logic to copy physical address when checkbox is checked
- [ ] Implement logic to make mailing fields required when checkbox is unchecked

### Primary Contact Section
- [ ] Add `primary_contact_title` dropdown (required, options: Mr., Mrs., Eng., Prof.)
- [ ] Add `primary_contact_first_name` (required text input)
- [ ] Add `primary_contact_last_name` (required text input)
- [ ] Add `primary_contact_email` (required email input)
- [ ] Add `primary_contact_country` (required dropdown)
- [ ] Add `primary_contact_mobile` (required text input)
- [ ] Add `primary_contact_passport` file upload (required, PDF/JPEG/PNG, max 10MB)

### Secondary Contact Section
- [ ] **Important**: All secondary contact fields are **required** (not optional)
- [ ] Add `secondary_contact_title` dropdown (required, options: Mr., Mrs., Eng., Prof.)
- [ ] Add `secondary_contact_first_name` (required text input)
- [ ] Add `secondary_contact_last_name` (required text input)
- [ ] Add `secondary_contact_email` (required email input)
- [ ] Add `secondary_contact_country` (required dropdown)
- [ ] Add `secondary_contact_mobile` (required text input)
- [ ] Add `secondary_contact_passport` file upload (required, PDF/JPEG/PNG, max 10MB)

### Additional Information Section
- [ ] Add `company_gov_registry_number` field (required text input)
- [ ] Add `company_registration_certificate` file upload (required, PDF/JPEG/PNG, max 10MB)
- [ ] Add `how_did_you_hear_about_us` text area (optional)

### Agreement Checkboxes Section
- [ ] Add checkbox "I agreed to receive other communications from (BOMEQP)" (`agreed_to_receive_communications`) - required, must be checked
- [ ] Add link to Privacy Policy URL
- [ ] Add checkbox "I confirm that I have read, understood, and accepted the terms and conditions" (`agreed_to_terms_and_conditions`) - required, must be checked
- [ ] Add link to Terms and Conditions URL
- [ ] Both checkboxes must be checked to submit the form

### Form Validation
- [ ] Implement validation for all required fields
- [ ] Implement conditional validation for mailing address
- [ ] Validate file uploads (type and size)
- [ ] Validate email formats
- [ ] Validate enum values (title)
- [ ] Validate agreement checkboxes (both must be true)

### API Integration
- [ ] Update GET profile API call to display all new fields
- [ ] Update POST/PUT profile API call to send all new fields
- [ ] Handle file uploads in multipart/form-data format
- [ ] Handle conditional field logic (mailing address)
- [ ] Display file URLs for uploaded passports and registration certificate
- [ ] Allow users to replace uploaded files
- [ ] Send agreement checkboxes as boolean values

### Display and UI
- [ ] Organize fields into 7 sections as described
- [ ] Show/hide conditional fields based on checkbox states
- [ ] Display validation errors for all fields
- [ ] Show file upload progress
- [ ] Display uploaded file links/thumbnails
- [ ] Show required field indicators (*)
- [ ] Display Privacy Policy and Terms and Conditions links

### Testing
- [ ] Test profile retrieval with all new fields
- [ ] Test profile update with all required fields
- [ ] Test profile update with optional fields
- [ ] Test mailing address same as physical logic
- [ ] Test file uploads (primary passport, secondary passport, registration certificate)
- [ ] Test validation errors for required fields
- [ ] Test conditional field validation
- [ ] Test file type and size validation
- [ ] Test agreement checkboxes validation (both must be true)

---

## API Request Examples

### Update Profile Request (multipart/form-data)
```javascript
const formData = new FormData();

// Accreditation Body Information
formData.append('legal_name', 'ACC Legal Name');
formData.append('email', 'acc@example.com');
formData.append('phone', '+1234567890');
formData.append('fax', '+1234567891');
formData.append('website', 'https://example.com');

// Physical Address
formData.append('physical_street', '123 Main Street');
formData.append('physical_city', 'Cairo');
formData.append('physical_country', 'Egypt');
formData.append('physical_postal_code', '12345');

// Mailing Address
formData.append('mailing_same_as_physical', 'false');
formData.append('mailing_street', '456 Mailing Street');
formData.append('mailing_city', 'Cairo');
formData.append('mailing_country', 'Egypt');
formData.append('mailing_postal_code', '12345');

// Primary Contact
formData.append('primary_contact_title', 'Mr.');
formData.append('primary_contact_first_name', 'John');
formData.append('primary_contact_last_name', 'Doe');
formData.append('primary_contact_email', 'john@example.com');
formData.append('primary_contact_country', 'Egypt');
formData.append('primary_contact_mobile', '+1234567890');
formData.append('primary_contact_passport', passportFile1); // File object

// Secondary Contact
formData.append('secondary_contact_title', 'Mrs.');
formData.append('secondary_contact_first_name', 'Jane');
formData.append('secondary_contact_last_name', 'Doe');
formData.append('secondary_contact_email', 'jane@example.com');
formData.append('secondary_contact_country', 'Egypt');
formData.append('secondary_contact_mobile', '+1234567891');
formData.append('secondary_contact_passport', passportFile2); // File object

// Additional Information
formData.append('company_gov_registry_number', 'REG123456');
formData.append('company_registration_certificate', certFile); // File object
formData.append('how_did_you_hear_about_us', 'Google Search');

// Agreement Checkboxes
formData.append('agreed_to_receive_communications', 'true');
formData.append('agreed_to_terms_and_conditions', 'true');
```

### Update Profile Request (JSON - for non-file fields)
```json
{
  "legal_name": "ACC Legal Name",
  "email": "acc@example.com",
  "phone": "+1234567890",
  "fax": "+1234567891",
  "website": "https://example.com",
  "physical_street": "123 Main Street",
  "physical_city": "Cairo",
  "physical_country": "Egypt",
  "physical_postal_code": "12345",
  "mailing_same_as_physical": false,
  "mailing_street": "456 Mailing Street",
  "mailing_city": "Cairo",
  "mailing_country": "Egypt",
  "mailing_postal_code": "12345",
  "primary_contact_title": "Mr.",
  "primary_contact_first_name": "John",
  "primary_contact_last_name": "Doe",
  "primary_contact_email": "john@example.com",
  "primary_contact_country": "Egypt",
  "primary_contact_mobile": "+1234567890",
  "secondary_contact_title": "Mrs.",
  "secondary_contact_first_name": "Jane",
  "secondary_contact_last_name": "Doe",
  "secondary_contact_email": "jane@example.com",
  "secondary_contact_country": "Egypt",
  "secondary_contact_mobile": "+1234567891",
  "company_gov_registry_number": "REG123456",
  "how_did_you_hear_about_us": "Google Search",
  "agreed_to_receive_communications": true,
  "agreed_to_terms_and_conditions": true
}
```

---

## Response Examples

### Profile Response
```json
{
  "profile": {
    "id": 1,
    "name": "ACC Name",
    "legal_name": "ACC Legal Name",
    "registration_number": "REG123456",
    "email": "acc@example.com",
    "phone": "+1234567890",
    "fax": "+1234567891",
    "website": "https://example.com",
    "physical_address": {
      "street": "123 Main Street",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345"
    },
    "mailing_address": {
      "street": "456 Mailing Street",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345",
      "same_as_physical": false
    },
    "primary_contact": {
      "title": "Mr.",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "country": "Egypt",
      "mobile": "+1234567890",
      "passport_url": "https://aeroenix.com/api/storage/accs/1/primary_contact_passport/passport_1234567890.pdf"
    },
    "secondary_contact": {
      "title": "Mrs.",
      "first_name": "Jane",
      "last_name": "Doe",
      "email": "jane@example.com",
      "country": "Egypt",
      "mobile": "+1234567891",
      "passport_url": "https://aeroenix.com/api/storage/accs/1/secondary_contact_passport/passport_1234567891.pdf"
    },
    "company_gov_registry_number": "REG123456",
    "company_registration_certificate_url": "https://aeroenix.com/api/storage/accs/1/registration_certificate/cert_1234567890.pdf",
    "how_did_you_hear_about_us": "Google Search",
    "agreed_to_receive_communications": true,
    "agreed_to_terms_and_conditions": true,
    "status": "active",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "primary_contact_first_name": ["The primary contact first name field is required."],
    "secondary_contact_email": ["The secondary contact email field is required."],
    "mailing_street": ["The mailing street field is required when mailing same as physical is false."],
    "primary_contact_passport": ["The primary contact passport must be a file of type: pdf, jpeg, jpg, png."],
    "agreed_to_terms_and_conditions": ["The agreed to terms and conditions field must be true."]
  }
}
```

---

## Notes

1. **Mailing Address Logic**: When `mailing_same_as_physical` is `true`, the system automatically copies physical address fields. The frontend should handle this logic or rely on the backend to do it.

2. **Secondary Contact**: Unlike Training Center, Secondary Contact is **always required** for ACC. All fields must be provided.

3. **File Uploads**: File uploads must be sent as `multipart/form-data`. JSON requests cannot include file uploads.

4. **Agreement Checkboxes**: Both checkboxes are **required** and must be set to `true`. The form should not be submittable unless both are checked.

5. **Contact Titles**: Must be exactly one of: "Mr.", "Mrs.", "Eng.", "Prof." (case-sensitive, including the period)

6. **Backward Compatibility**: Existing ACCs without the new fields will have `null` values for optional fields. Required fields should be filled when updating the profile.

7. **File Storage**: Uploaded files are stored and URLs are returned. The URLs can be used to access the files through the storage API.

8. **Privacy Policy and Terms Links**: The frontend should provide links to the Privacy Policy and Terms and Conditions pages. These URLs should be configurable or provided by the backend.

---

## Support

For questions or issues related to these changes, please contact the backend development team.

