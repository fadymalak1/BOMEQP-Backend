# Training Center Comprehensive Fields Update - API Changes Documentation

## Overview
This document outlines the comprehensive changes made to the Training Center API endpoints. New fields have been added across multiple sections: Company Information, Physical Address, Mailing Address, Primary Contact, Secondary Contact, and Additional Information.

## Date
January 22, 2026

---

## Changes Summary

### New Fields Added
The Training Center model now includes comprehensive fields organized into 6 main sections:

1. **Company Information** - Enhanced with fax and training provider type
2. **Physical Address** - Added postal code
3. **Mailing Address** - New optional section with checkbox for same as physical address
4. **Primary Contact** - New required section with contact details
5. **Secondary Contact** - New optional section with contact details
6. **Additional Information** - New section with registry number, certificates, and interests

### Field Requirements
- Fields marked with (*) are **required**
- Fields without (*) are **optional**

---

## Field Structure

### 1. Company Information (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Company Name * | `name` | String | Yes | Company name |
| Website | `website` | String | No | Company website URL |
| Company Email Address * | `email` | String | Yes | Company email (unique) |
| Telephone Number * | `phone` | String | Yes | Company phone number |
| Fax | `fax` | String | No | Company fax number |
| Training Provider Type * | `training_provider_type` | Enum | Yes | Options: "Training Center", "Institute", "University" |

**Note**: `name`, `email`, and `phone` are existing fields that remain required. `website` is optional.

---

### 2. Physical Address (*)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Address * | `address` | String | Yes | Physical street address |
| City * | `city` | String | Yes | Physical city |
| Country * | `country` | String | Yes | Physical country |
| Postal Code * | `physical_postal_code` | String | Yes | Physical postal/zip code |

**Note**: `address`, `city`, and `country` are existing fields that remain required.

---

### 3. Mailing Address (Optional)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Same as Physical Address | `mailing_same_as_physical` | Boolean | No | If true, copies physical address fields |
| Address | `mailing_address` | String | Conditional | Required if `mailing_same_as_physical` is false |
| City | `mailing_city` | String | Conditional | Required if `mailing_same_as_physical` is false |
| Country | `mailing_country` | String | Conditional | Required if `mailing_same_as_physical` is false |
| Postal Code | `mailing_postal_code` | String | Conditional | Required if `mailing_same_as_physical` is false |

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
| Email Address * | `primary_contact_email` | String | Yes | Primary contact email |
| Country * | `primary_contact_country` | String | Yes | Primary contact country |
| Mobile Number * | `primary_contact_mobile` | String | Yes | Primary contact mobile number |

---

### 5. Secondary Contact (Optional)

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Add Secondary Contact | `has_secondary_contact` | Boolean | No | If true, shows secondary contact fields |
| Title | `secondary_contact_title` | Enum | Conditional | Required if `has_secondary_contact` is true |
| First Name | `secondary_contact_first_name` | String | Conditional | Required if `has_secondary_contact` is true |
| Last Name | `secondary_contact_last_name` | String | Conditional | Required if `has_secondary_contact` is true |
| Email Address | `secondary_contact_email` | String | Conditional | Required if `has_secondary_contact` is true |
| Country | `secondary_contact_country` | String | Conditional | Required if `has_secondary_contact` is true |
| Mobile Number | `secondary_contact_mobile` | String | Conditional | Required if `has_secondary_contact` is true |

**Logic**: 
- If `has_secondary_contact` is `true`, all secondary contact fields become required
- If `has_secondary_contact` is `false` or not provided, secondary contact fields are optional

---

### 6. Additional Information

| Field Name | API Field | Type | Required | Description |
|------------|-----------|------|----------|-------------|
| Company GOV Registry Number * | `company_gov_registry_number` | String | Yes | Government registry number |
| Upload Company Registration Certificate * | `company_registration_certificate` | File | Yes | PDF, JPEG, PNG (max 10MB) |
| Upload Facility Floorplan | `facility_floorplan` | File | No | PDF, JPEG, PNG (max 10MB) |
| Interested Fields | `interested_fields` | Array | No | Multi-select: ["QHSE", "Food Safety", "Management"] |
| How did you hear about us? | `how_did_you_hear_about_us` | String | No | Text field |

**Interested Fields Options**:
- "QHSE"
- "Food Safety"
- "Management"

---

## Affected API Endpoints

### 1. Get Training Center Profile
**Endpoint**: `GET /v1/api/training-center/profile`

**Changes**:
- Response now includes all new fields across all 6 sections

**Response Structure**:
```json
{
  "profile": {
    "id": 1,
    "name": "Company Name",
    "website": "https://example.com",
    "email": "company@example.com",
    "phone": "+1234567890",
    "fax": "+1234567891",
    "training_provider_type": "Training Center",
    "address": "123 Main Street",
    "city": "Cairo",
    "country": "Egypt",
    "physical_postal_code": "12345",
    "mailing_same_as_physical": false,
    "mailing_address": "456 Mailing Street",
    "mailing_city": "Cairo",
    "mailing_country": "Egypt",
    "mailing_postal_code": "12345",
    "primary_contact_title": "Mr.",
    "primary_contact_first_name": "John",
    "primary_contact_last_name": "Doe",
    "primary_contact_email": "john@example.com",
    "primary_contact_country": "Egypt",
    "primary_contact_mobile": "+1234567890",
    "has_secondary_contact": true,
    "secondary_contact_title": "Mrs.",
    "secondary_contact_first_name": "Jane",
    "secondary_contact_last_name": "Doe",
    "secondary_contact_email": "jane@example.com",
    "secondary_contact_country": "Egypt",
    "secondary_contact_mobile": "+1234567891",
    "company_gov_registry_number": "REG123456",
    "company_registration_certificate_url": "https://example.com/cert.pdf",
    "facility_floorplan_url": "https://example.com/floorplan.pdf",
    "interested_fields": ["QHSE", "Food Safety"],
    "how_did_you_hear_about_us": "Google Search",
    "status": "active",
    ...
  }
}
```

---

### 2. Update Training Center Profile
**Endpoint**: `POST /v1/api/training-center/profile` or `PUT /v1/api/training-center/profile`

**Changes**:
- Added validation for all new fields
- Added file upload handling for registration certificate and floorplan
- Added logic to handle mailing address same as physical
- Added logic to handle secondary contact conditional fields

**Request Body** (multipart/form-data):
- All fields from all 6 sections can be updated
- File uploads: `company_registration_certificate`, `facility_floorplan`, `logo`
- `interested_fields` can be sent as JSON array or comma-separated string

**Validation Rules**:
- Required fields must be provided when updating
- Conditional fields follow the logic described above
- File uploads: PDF, JPEG, PNG, max 10MB

**Response**: 
- Status Code: 200 OK
- Returns the updated profile with all fields

---

### 3. Admin - Get Training Centers
**Endpoint**: `GET /v1/api/admin/training-centers`

**Changes**:
- Response now includes all new fields for each training center

**Response Structure**:
- Each training center object includes all fields from all 6 sections

---

### 4. Admin - Get Training Center Details
**Endpoint**: `GET /v1/api/admin/training-centers/{id}`

**Changes**:
- Response now includes all new fields

**Response Structure**:
- Training center object includes all fields from all 6 sections

---

### 5. Admin - Update Training Center
**Endpoint**: `PUT /v1/api/admin/training-centers/{id}`

**Changes**:
- Added validation for all new fields
- Added logic to handle mailing address same as physical
- Added logic to handle secondary contact conditional fields

**Request Body** (application/json or multipart/form-data):
- All fields from all 6 sections can be updated
- File URLs can be provided for certificates and floorplan

**Validation Rules**:
- Same as Profile Update endpoint

---

## Field Mapping and Logic

### Mailing Address Logic
When `mailing_same_as_physical` is `true`:
- System automatically copies:
  - `address` → `mailing_address`
  - `city` → `mailing_city`
  - `country` → `mailing_country`
  - `physical_postal_code` → `mailing_postal_code`

When `mailing_same_as_physical` is `false`:
- All mailing address fields become required:
  - `mailing_address` (required)
  - `mailing_city` (required)
  - `mailing_country` (required)
  - `mailing_postal_code` (required)

### Secondary Contact Logic
When `has_secondary_contact` is `true`:
- All secondary contact fields become required:
  - `secondary_contact_title` (required)
  - `secondary_contact_first_name` (required)
  - `secondary_contact_last_name` (required)
  - `secondary_contact_email` (required)
  - `secondary_contact_country` (required)
  - `secondary_contact_mobile` (required)

When `has_secondary_contact` is `false` or not provided:
- All secondary contact fields are optional

---

## Validation Rules

### Required Fields (Always Required)
1. **Company Information**:
   - `name` (Company Name)
   - `email` (Company Email Address)
   - `phone` (Telephone Number)
   - `training_provider_type` (Training Provider Type)

2. **Physical Address**:
   - `address`
   - `city`
   - `country`
   - `physical_postal_code`

3. **Primary Contact**:
   - `primary_contact_title`
   - `primary_contact_first_name`
   - `primary_contact_last_name`
   - `primary_contact_email`
   - `primary_contact_country`
   - `primary_contact_mobile`

4. **Additional Information**:
   - `company_gov_registry_number`
   - `company_registration_certificate` (file upload)

### Conditional Required Fields
1. **Mailing Address** (required if `mailing_same_as_physical` is false):
   - `mailing_address`
   - `mailing_city`
   - `mailing_country`
   - `mailing_postal_code`

2. **Secondary Contact** (required if `has_secondary_contact` is true):
   - `secondary_contact_title`
   - `secondary_contact_first_name`
   - `secondary_contact_last_name`
   - `secondary_contact_email`
   - `secondary_contact_country`
   - `secondary_contact_mobile`

### Optional Fields
- `website`
- `fax`
- `facility_floorplan` (file upload)
- `interested_fields` (array)
- `how_did_you_hear_about_us`

---

## File Uploads

### Company Registration Certificate
- **Field**: `company_registration_certificate`
- **Type**: File upload
- **Allowed Types**: PDF, JPEG, JPG, PNG
- **Max Size**: 10MB
- **Required**: Yes
- **Storage**: Files are stored and a URL is returned in `company_registration_certificate_url`

### Facility Floorplan
- **Field**: `facility_floorplan`
- **Type**: File upload
- **Allowed Types**: PDF, JPEG, JPG, PNG
- **Max Size**: 10MB
- **Required**: No
- **Storage**: Files are stored and a URL is returned in `facility_floorplan_url`

---

## Migration Required

**Important**: Before using the updated API, you must run the database migration:

```bash
php artisan migrate
```

This will add all new columns to the `training_centers` table.

---

## Frontend Implementation Checklist

### Company Information Section
- [ ] Add `fax` field (optional text input)
- [ ] Add `training_provider_type` dropdown (required, options: Training Center, Institute, University)
- [ ] Ensure `name`, `email`, `phone` are marked as required
- [ ] Keep `website` as optional

### Physical Address Section
- [ ] Add `physical_postal_code` field (required text input)
- [ ] Ensure `address`, `city`, `country` are marked as required

### Mailing Address Section
- [ ] Add checkbox "Same as Physical Address" (`mailing_same_as_physical`)
- [ ] Add conditional fields (shown when checkbox is unchecked):
  - [ ] `mailing_address` (text input, required when checkbox unchecked)
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

### Secondary Contact Section
- [ ] Add checkbox "Add Secondary Contact" (`has_secondary_contact`)
- [ ] Add conditional fields (shown when checkbox is checked):
  - [ ] `secondary_contact_title` (dropdown, required when checkbox checked)
  - [ ] `secondary_contact_first_name` (text input, required when checkbox checked)
  - [ ] `secondary_contact_last_name` (text input, required when checkbox checked)
  - [ ] `secondary_contact_email` (email input, required when checkbox checked)
  - [ ] `secondary_contact_country` (dropdown, required when checkbox checked)
  - [ ] `secondary_contact_mobile` (text input, required when checkbox checked)
- [ ] Implement logic to make secondary contact fields required when checkbox is checked

### Additional Information Section
- [ ] Add `company_gov_registry_number` field (required text input)
- [ ] Add `company_registration_certificate` file upload (required, PDF/JPEG/PNG, max 10MB)
- [ ] Add `facility_floorplan` file upload (optional, PDF/JPEG/PNG, max 10MB)
- [ ] Add `interested_fields` multi-select checkboxes (optional):
  - [ ] QHSE checkbox
  - [ ] Food Safety checkbox
  - [ ] Management checkbox
- [ ] Add `how_did_you_hear_about_us` text area (optional)

### Form Validation
- [ ] Implement validation for all required fields
- [ ] Implement conditional validation for mailing address
- [ ] Implement conditional validation for secondary contact
- [ ] Validate file uploads (type and size)
- [ ] Validate email formats
- [ ] Validate enum values (title, training_provider_type)

### API Integration
- [ ] Update GET profile API call to display all new fields
- [ ] Update POST/PUT profile API call to send all new fields
- [ ] Handle file uploads in multipart/form-data format
- [ ] Handle `interested_fields` as array in request
- [ ] Handle conditional field logic (mailing address, secondary contact)
- [ ] Display file URLs for uploaded certificates and floorplan
- [ ] Allow users to replace uploaded files

### Display and UI
- [ ] Organize fields into 6 sections as described
- [ ] Show/hide conditional fields based on checkbox states
- [ ] Display validation errors for all fields
- [ ] Show file upload progress
- [ ] Display uploaded file links/thumbnails
- [ ] Show required field indicators (*)

### Testing
- [ ] Test profile retrieval with all new fields
- [ ] Test profile update with all required fields
- [ ] Test profile update with optional fields
- [ ] Test mailing address same as physical logic
- [ ] Test secondary contact conditional logic
- [ ] Test file uploads (registration certificate, floorplan)
- [ ] Test validation errors for required fields
- [ ] Test conditional field validation
- [ ] Test file type and size validation

---

## API Request Examples

### Update Profile Request (multipart/form-data)
```javascript
const formData = new FormData();

// Company Information
formData.append('name', 'Company Name');
formData.append('email', 'company@example.com');
formData.append('phone', '+1234567890');
formData.append('fax', '+1234567891');
formData.append('training_provider_type', 'Training Center');
formData.append('website', 'https://example.com');

// Physical Address
formData.append('address', '123 Main Street');
formData.append('city', 'Cairo');
formData.append('country', 'Egypt');
formData.append('physical_postal_code', '12345');

// Mailing Address
formData.append('mailing_same_as_physical', 'false');
formData.append('mailing_address', '456 Mailing Street');
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

// Secondary Contact
formData.append('has_secondary_contact', 'true');
formData.append('secondary_contact_title', 'Mrs.');
formData.append('secondary_contact_first_name', 'Jane');
formData.append('secondary_contact_last_name', 'Doe');
formData.append('secondary_contact_email', 'jane@example.com');
formData.append('secondary_contact_country', 'Egypt');
formData.append('secondary_contact_mobile', '+1234567891');

// Additional Information
formData.append('company_gov_registry_number', 'REG123456');
formData.append('company_registration_certificate', certificateFile); // File object
formData.append('facility_floorplan', floorplanFile); // File object (optional)
formData.append('interested_fields', JSON.stringify(['QHSE', 'Food Safety']));
formData.append('how_did_you_hear_about_us', 'Google Search');
```

### Update Profile Request (JSON - for non-file fields)
```json
{
  "name": "Company Name",
  "email": "company@example.com",
  "phone": "+1234567890",
  "fax": "+1234567891",
  "training_provider_type": "Training Center",
  "website": "https://example.com",
  "address": "123 Main Street",
  "city": "Cairo",
  "country": "Egypt",
  "physical_postal_code": "12345",
  "mailing_same_as_physical": false,
  "mailing_address": "456 Mailing Street",
  "mailing_city": "Cairo",
  "mailing_country": "Egypt",
  "mailing_postal_code": "12345",
  "primary_contact_title": "Mr.",
  "primary_contact_first_name": "John",
  "primary_contact_last_name": "Doe",
  "primary_contact_email": "john@example.com",
  "primary_contact_country": "Egypt",
  "primary_contact_mobile": "+1234567890",
  "has_secondary_contact": true,
  "secondary_contact_title": "Mrs.",
  "secondary_contact_first_name": "Jane",
  "secondary_contact_last_name": "Doe",
  "secondary_contact_email": "jane@example.com",
  "secondary_contact_country": "Egypt",
  "secondary_contact_mobile": "+1234567891",
  "company_gov_registry_number": "REG123456",
  "interested_fields": ["QHSE", "Food Safety"],
  "how_did_you_hear_about_us": "Google Search"
}
```

---

## Response Examples

### Profile Response
```json
{
  "profile": {
    "id": 1,
    "name": "Company Name",
    "legal_name": "Company Legal Name",
    "registration_number": "REG123456",
    "website": "https://example.com",
    "email": "company@example.com",
    "phone": "+1234567890",
    "fax": "+1234567891",
    "training_provider_type": "Training Center",
    "address": "123 Main Street",
    "city": "Cairo",
    "country": "Egypt",
    "physical_postal_code": "12345",
    "mailing_same_as_physical": false,
    "mailing_address": "456 Mailing Street",
    "mailing_city": "Cairo",
    "mailing_country": "Egypt",
    "mailing_postal_code": "12345",
    "primary_contact_title": "Mr.",
    "primary_contact_first_name": "John",
    "primary_contact_last_name": "Doe",
    "primary_contact_email": "john@example.com",
    "primary_contact_country": "Egypt",
    "primary_contact_mobile": "+1234567890",
    "has_secondary_contact": true,
    "secondary_contact_title": "Mrs.",
    "secondary_contact_first_name": "Jane",
    "secondary_contact_last_name": "Doe",
    "secondary_contact_email": "jane@example.com",
    "secondary_contact_country": "Egypt",
    "secondary_contact_mobile": "+1234567891",
    "company_gov_registry_number": "REG123456",
    "company_registration_certificate_url": "https://aeroenix.com/api/storage/training_centers/1/registration_certificate/cert_1234567890.pdf",
    "facility_floorplan_url": "https://aeroenix.com/api/storage/training_centers/1/floorplan/floorplan_1234567890.pdf",
    "interested_fields": ["QHSE", "Food Safety"],
    "how_did_you_hear_about_us": "Google Search",
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
    "training_provider_type": ["The training provider type field is required."],
    "physical_postal_code": ["The physical postal code field is required."],
    "primary_contact_first_name": ["The primary contact first name field is required."],
    "mailing_address": ["The mailing address field is required when mailing same as physical is false."],
    "company_registration_certificate": ["The company registration certificate must be a file of type: pdf, jpeg, jpg, png."]
  }
}
```

---

## Notes

1. **Mailing Address Logic**: When `mailing_same_as_physical` is `true`, the system automatically copies physical address fields. The frontend should handle this logic or rely on the backend to do it.

2. **Secondary Contact Logic**: When `has_secondary_contact` is `true`, all secondary contact fields become required. The frontend should validate this conditionally.

3. **File Uploads**: File uploads must be sent as `multipart/form-data`. JSON requests cannot include file uploads.

4. **Interested Fields**: Can be sent as:
   - JSON array: `["QHSE", "Food Safety"]`
   - Comma-separated string: `"QHSE,Food Safety"`
   - The backend will parse and store as JSON array

5. **Training Provider Type**: Must be exactly one of: "Training Center", "Institute", "University" (case-sensitive)

6. **Contact Titles**: Must be exactly one of: "Mr.", "Mrs.", "Eng.", "Prof." (case-sensitive, including the period)

7. **Backward Compatibility**: Existing training centers without the new fields will have `null` values for optional fields. Required fields should be filled when updating the profile.

8. **File Storage**: Uploaded files are stored and URLs are returned. The URLs can be used to access the files through the storage API.

---

## Support

For questions or issues related to these changes, please contact the backend development team.

