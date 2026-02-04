# Certificate Generation Feature Documentation

## Overview

This feature enables ACC (Accreditation Certification Center) administrators to generate and automatically send certificate templates for training centers and instructors. The system automatically generates PDF certificates and emails them with congratulatory messages when specific events occur.

## Feature Description

The certificate generation system allows ACC users to:
- Create custom certificate templates for training centers and instructors
- Automatically generate PDF certificates when training centers are approved
- Automatically generate and send individual certificates for each course when instructor payments are completed
- Include all relevant information (ACC details, recipient details, dates, etc.) in the certificates

## Database Changes

### Migration: Add Template Type to Certificate Templates

A new migration file was created to add a `template_type` field to the `certificate_templates` table.

**Field Added:**
- `template_type`: An enumeration field with three possible values:
  - `course`: For course completion certificates (existing functionality)
  - `training_center`: For training center authorization certificates
  - `instructor`: For instructor authorization certificates
- Default value: `course` (to maintain backward compatibility)
- Position: Added after the `acc_id` field

This field allows the system to distinguish between different types of certificate templates and ensures that only one active template of each type exists per ACC.

## Model Updates

### CertificateTemplate Model

The `CertificateTemplate` model was updated to include `template_type` in the fillable array, allowing mass assignment of this field when creating or updating templates.

## API Endpoints

### Certificate Template Management

#### List Certificate Templates

**Endpoint:** `GET /api/acc/certificate-templates`

**Description:** Retrieves all certificate templates for the authenticated ACC with pagination, search, and filtering capabilities.

**Authentication:** Required (Sanctum)

**Query Parameters:**
- `search` (optional, string): Search by template name
- `status` (optional, enum): Filter by template status (`active` or `inactive`)
- `template_type` (optional, enum): Filter by template type (`course`, `training_center`, or `instructor`)
- `per_page` (optional, integer): Number of items per page (default: 10)
- `page` (optional, integer): Page number (default: 1)

**Response:**
- Returns paginated list of certificate templates
- Each template includes: id, acc_id, template_type, name, template_html, background_image_url, status, category, course, courses, and timestamps

**Success Response:** 200 OK

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 404 Not Found: ACC not found

---

#### Create Certificate Template

**Endpoint:** `POST /api/acc/certificate-templates`

**Description:** Creates a new certificate template for the authenticated ACC. Supports three types: course, training center, and instructor templates.

**Authentication:** Required (Sanctum)

**Request Body:**
- `template_type` (required, enum): Type of certificate template
  - `course`: For course completion certificates (requires course_ids)
  - `training_center`: For training center authorization certificates
  - `instructor`: For instructor authorization certificates
- `course_ids` (required if template_type is `course`, array): Array of course IDs to associate with the template
- `name` (required, string, max 255): Template name
- `template_html` (optional, string): HTML template with variable placeholders
- `status` (required, enum): Template status (`active` or `inactive`)

**Validation Rules:**
- For `course` templates: At least one course_id must be provided, and all courses must belong to the ACC
- For `training_center` and `instructor` templates: Only one active template of each type is allowed per ACC
- Course IDs must exist and belong to the ACC
- No course can have multiple templates

**Response:**
- Returns the created template with all relationships loaded

**Success Response:** 201 Created

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 403 Forbidden: Course does not belong to ACC
- 404 Not Found: ACC not found
- 422 Unprocessable Entity: Validation errors or conflicts

**Special Behavior:**
- For training center and instructor templates, the system checks if an active template already exists
- If an active template exists, creation is rejected with details about the existing template
- For course templates, the system ensures no course has multiple templates

---

#### Get Certificate Template Details

**Endpoint:** `GET /api/acc/certificate-templates/{id}`

**Description:** Retrieves detailed information about a specific certificate template.

**Authentication:** Required (Sanctum)

**Path Parameters:**
- `id` (required, integer): Template ID

**Response:**
- Returns complete template information including relationships

**Success Response:** 200 OK

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 404 Not Found: Template not found or does not belong to ACC

---

#### Update Certificate Template

**Endpoint:** `PUT /api/acc/certificate-templates/{id}`

**Description:** Updates an existing certificate template. Allows updating name, template_html, status, config_json, and course associations.

**Authentication:** Required (Sanctum)

**Path Parameters:**
- `id` (required, integer): Template ID

**Request Body (all optional):**
- `course_ids` (optional, array): Array of course IDs (only allowed for course templates)
- `name` (optional, string, max 255): Template name
- `template_html` (optional, string): HTML template content
- `status` (optional, enum): Template status
- `config_json` (optional, array): Template configuration with placeholders, coordinates, and styling

**Validation Rules:**
- Course IDs can only be updated for course-type templates
- All course validation rules apply (must exist, belong to ACC, no conflicts)
- Template type cannot be changed after creation

**Response:**
- Returns updated template with relationships

**Success Response:** 200 OK

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 403 Forbidden: Course does not belong to ACC
- 404 Not Found: Template not found
- 422 Unprocessable Entity: Validation errors

---

#### Upload Background Image

**Endpoint:** `POST /api/acc/certificate-templates/{id}/upload-background`

**Description:** Uploads a high-resolution background image for the certificate template.

**Authentication:** Required (Sanctum)

**Path Parameters:**
- `id` (required, integer): Template ID

**Request Body (multipart/form-data):**
- `background_image` (required, file): Image file (JPEG or PNG, max 10MB)

**Response:**
- Returns template with updated background_image_url

**Success Response:** 200 OK

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 404 Not Found: Template not found
- 422 Unprocessable Entity: Invalid file format or size

---

#### Update Template Configuration

**Endpoint:** `PUT /api/acc/certificate-templates/{id}/config`

**Description:** Updates the template designer configuration (config_json) with placeholders, coordinates, and styling information.

**Authentication:** Required (Sanctum)

**Path Parameters:**
- `id` (required, integer): Template ID

**Request Body:**
- `config_json` (required, array): Array of placeholder configurations
  - Each placeholder includes: variable, x (0-1), y (0-1), font_family, font_size, color, text_align

**Response:**
- Returns updated template

**Success Response:** 200 OK

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 404 Not Found: Template not found
- 422 Unprocessable Entity: Validation errors

---

#### Delete Certificate Template

**Endpoint:** `DELETE /api/acc/certificate-templates/{id}`

**Description:** Deletes a certificate template. Certificates that were generated using this template will remain, but their template reference will be removed.

**Authentication:** Required (Sanctum)

**Path Parameters:**
- `id` (required, integer): Template ID

**Response:**
- Returns success message and count of certificates that were using this template

**Success Response:** 200 OK

**Error Responses:**
- 401 Unauthorized: User not authenticated
- 404 Not Found: Template not found

**Special Behavior:**
- Automatically deletes associated background images from storage
- Preserves existing certificates but removes template reference

---

## Certificate Generation Service

### Service Methods

#### Generate Training Center Certificate

**Method:** `generateTrainingCenterCertificate()`

**Description:** Generates a PDF certificate for a training center authorization using the active training center template.

**Parameters:**
- CertificateTemplate: The template to use
- TrainingCenter: The training center receiving the certificate
- ACC: The ACC issuing the certificate

**Data Included in Certificate:**
- Training center name and legal name
- Training center email, country, city
- Training center registration number
- ACC name and legal name
- ACC registration number and country
- Issue date (formatted and unformatted)

**Output:**
- Returns array with success status, file path, and file URL
- Generates PDF file in storage

---

#### Generate Instructor Certificate

**Method:** `generateInstructorCertificate()`

**Description:** Generates a PDF certificate for an instructor authorization for a specific course using the active instructor template.

**Parameters:**
- CertificateTemplate: The template to use
- Instructor: The instructor receiving the certificate
- Course: The specific course the instructor is authorized to teach
- ACC: The ACC issuing the certificate

**Data Included in Certificate:**
- Instructor full name, first name, last name
- Instructor email, ID number
- Instructor country and city
- Course name (English and Arabic if available)
- Course code
- ACC name and legal name
- ACC registration number and country
- Issue date (formatted and unformatted)

**Output:**
- Returns array with success status, file path, and file URL
- Generates PDF file in storage

---

## Automatic Certificate Generation Flow

### Training Center Approval Flow

**Trigger:** When ACC approves a training center authorization request

**Process:**
1. Training center authorization status is updated to `approved`
2. System checks for an active training center certificate template for the ACC
3. If template exists:
   - Certificate is generated using the template
   - PDF file is created and stored
   - Email is sent to the training center with:
     - Congratulatory message
     - PDF certificate attached
     - ACC name and details
4. If template doesn't exist:
   - Process continues without certificate generation
   - Error is logged for monitoring

**Email Details:**
- Recipient: Training center email address
- Subject: "Congratulations! Your Training Center Authorization Certificate - [App Name]"
- Content: Congratulatory message with ACC details
- Attachment: PDF certificate file named `authorization_certificate.pdf`

**Location:** TrainingCenterController approve method

---

### Instructor Payment Completion Flow

**Trigger:** When training center completes payment for instructor authorization

**Process:**
1. Payment transaction is processed and verified
2. Authorization payment status is updated to `paid`
3. System checks for an active instructor certificate template for the ACC
4. System retrieves all authorized courses for the instructor and ACC
5. For each authorized course:
   - Certificate is generated using the template
   - PDF file is created and stored
   - Individual email is sent to the instructor with:
     - Congratulatory message
     - Course-specific certificate attached
     - Course name and authorization details
6. If template doesn't exist:
   - Process continues without certificate generation
   - Information is logged

**Email Details:**
- Recipient: Instructor email address
- Subject: "Congratulations! Your Instructor Authorization Certificate - [Course Name] - [App Name]"
- Content: Congratulatory message with course and ACC details
- Attachment: PDF certificate file named `instructor_authorization_certificate_[Course_Name].pdf`
- Multiple emails: One email per authorized course

**Location:** InstructorManagementService payment completion method

**Special Notes:**
- Each course receives its own separate certificate
- Each course receives its own separate email
- All certificates are generated and sent in the same transaction
- Errors for individual courses don't stop the process for other courses

---

## Email Templates

### Training Center Certificate Email

**Template File:** `resources/views/emails/training-center-certificate.blade.php`

**Design:**
- Green header with congratulations message
- Professional layout with ACC branding
- Clear message about authorization
- Footer with automated email notice

**Variables Available:**
- `$trainingCenterName`: Name of the training center
- `$accName`: Name of the ACC
- `$appName`: Application name

**Content:**
- Congratulatory message
- Information about authorization
- Details about the attached certificate
- Professional closing

---

### Instructor Certificate Email

**Template File:** `resources/views/emails/instructor-certificate.blade.php`

**Design:**
- Blue header with congratulations message
- Course name prominently displayed
- Professional layout with ACC branding
- Clear message about course authorization
- Footer with automated email notice

**Variables Available:**
- `$instructorName`: Full name of the instructor
- `$courseName`: Name of the authorized course
- `$accName`: Name of the ACC
- `$appName`: Application name

**Content:**
- Congratulatory message
- Course-specific authorization information
- Details about the attached certificate
- Professional closing

---

## Mail Classes

### TrainingCenterCertificateMail

**Class:** `App\Mail\TrainingCenterCertificateMail`

**Purpose:** Handles sending training center authorization certificates via email

**Properties:**
- Training center name
- ACC name
- Certificate PDF file path

**Features:**
- Implements ShouldQueue for background processing
- Attaches PDF certificate to email
- Uses dedicated email template
- Includes all necessary variables

---

### InstructorCertificateMail

**Class:** `App\Mail\InstructorCertificateMail`

**Purpose:** Handles sending instructor authorization certificates via email

**Properties:**
- Instructor name
- Course name
- ACC name
- Certificate PDF file path

**Features:**
- Implements ShouldQueue for background processing
- Attaches PDF certificate to email
- Uses dedicated email template
- Includes course-specific information
- Dynamic file naming based on course name

---

## Template Variables

### Training Center Template Variables

The following variables are available in training center certificate templates:

- `{{ training_center_name }}`: Full name of the training center
- `{{ training_center_legal_name }}`: Legal/registered name
- `{{ training_center_email }}`: Email address
- `{{ training_center_country }}`: Country location
- `{{ training_center_city }}`: City location
- `{{ training_center_registration_number }}`: Registration number
- `{{ acc_name }}`: ACC name
- `{{ acc_legal_name }}`: ACC legal name
- `{{ acc_registration_number }}`: ACC registration number
- `{{ acc_country }}`: ACC country
- `{{ issue_date }}`: Issue date in YYYY-MM-DD format
- `{{ issue_date_formatted }}`: Issue date in "Month Day, Year" format

---

### Instructor Template Variables

The following variables are available in instructor certificate templates:

- `{{ instructor_name }}`: Full name (first + last)
- `{{ instructor_first_name }}`: First name only
- `{{ instructor_last_name }}`: Last name only
- `{{ instructor_email }}`: Email address
- `{{ instructor_id_number }}`: ID or passport number
- `{{ instructor_country }}`: Country location
- `{{ instructor_city }}`: City location
- `{{ course_name }}`: Course name in English
- `{{ course_name_ar }}`: Course name in Arabic (if available)
- `{{ course_code }}`: Course code
- `{{ acc_name }}`: ACC name
- `{{ acc_legal_name }}`: ACC legal name
- `{{ acc_registration_number }}`: ACC registration number
- `{{ acc_country }}`: ACC country
- `{{ issue_date }}`: Issue date in YYYY-MM-DD format
- `{{ issue_date_formatted }}`: Issue date in "Month Day, Year" format

---

## Error Handling and Logging

### Error Handling Strategy

**Certificate Generation Errors:**
- Errors during certificate generation are caught and logged
- Process continues even if certificate generation fails
- No impact on the main approval/payment process
- Detailed error messages logged for debugging

**Email Sending Errors:**
- Emails are queued for background processing
- Failures are handled by Laravel's queue system
- Retry mechanisms available through queue configuration
- Errors logged for monitoring

**Template Validation:**
- Comprehensive validation before template creation
- Clear error messages for users
- Prevents conflicts and data issues
- Validates ACC ownership of resources

### Logging Points

**Information Logged:**
- Certificate generation success/failure
- Email sending status
- Template creation and updates
- Missing templates (when certificates should be generated)
- Authorization and payment events

**Log Levels:**
- Info: Successful operations, missing optional templates
- Warning: Non-critical failures, validation issues
- Error: Critical failures, exceptions

---

## Security Considerations

### Access Control

- All endpoints require authentication via Sanctum
- ACC users can only manage templates for their own ACC
- Course validation ensures ACC ownership
- Template type restrictions prevent conflicts

### Data Validation

- All input validated before processing
- File uploads restricted to image types
- File size limits enforced
- SQL injection prevention through Eloquent ORM
- XSS prevention through proper escaping

### File Storage

- Certificates stored in secure public storage
- Unique file names prevent conflicts
- Proper file permissions
- Background images validated before storage

---

## Performance Considerations

### Background Processing

- Email sending uses Laravel queues
- Certificate generation happens synchronously but quickly
- PDF generation optimized for performance
- File storage uses efficient disk operations

### Database Optimization

- Efficient queries with proper relationships
- Indexed foreign keys
- Pagination for large result sets
- Eager loading to prevent N+1 queries

### Caching

- Template data can be cached for frequently accessed templates
- File URLs cached for performance
- Consider Redis for high-traffic scenarios

---

## Usage Workflow

### For ACC Administrators

1. **Create Certificate Templates:**
   - Navigate to certificate templates section
   - Choose template type (training center or instructor)
   - Design template with HTML and variables
   - Upload background image (optional)
   - Configure placeholder positions and styling
   - Activate template

2. **Manage Templates:**
   - View all templates with filtering
   - Update template content and configuration
   - Deactivate templates when needed
   - Delete unused templates

3. **Automatic Generation:**
   - Templates automatically used when events occur
   - No manual intervention needed
   - Certificates generated and sent automatically

### For Training Centers

1. **Receive Authorization:**
   - Submit authorization request
   - Wait for ACC approval
   - Receive email with certificate automatically
   - Download and save certificate PDF

### For Instructors

1. **Complete Payment:**
   - Training center pays for authorization
   - Wait for payment processing
   - Receive separate emails for each authorized course
   - Each email contains course-specific certificate
   - Download and save all certificates

---

## Integration Points

### Training Center Approval

**Controller:** `App\Http\Controllers\API\ACC\TrainingCenterController`
**Method:** `approve()`
**Integration:** Certificate generation added after approval update

### Instructor Payment

**Service:** `App\Services\InstructorManagementService`
**Method:** Payment completion handler
**Integration:** Certificate generation added after payment processing

### Certificate Generation

**Service:** `App\Services\CertificateGenerationService`
**Methods:** 
- `generateTrainingCenterCertificate()`
- `generateInstructorCertificate()`

### Email Sending

**Mail Classes:**
- `App\Mail\TrainingCenterCertificateMail`
- `App\Mail\InstructorCertificateMail`

**Queue:** Uses Laravel queue system for background processing

---

## Future Enhancements

### Potential Improvements

1. **Template Preview:**
   - Preview certificates before generation
   - Test with sample data
   - Real-time preview in template editor

2. **Batch Generation:**
   - Generate certificates for multiple recipients
   - Bulk email sending
   - Progress tracking

3. **Certificate Verification:**
   - Unique verification codes
   - Public verification portal
   - QR codes on certificates

4. **Template Library:**
   - Pre-designed templates
   - Template sharing between ACCs
   - Template marketplace

5. **Advanced Customization:**
   - More design options
   - Custom fonts
   - Multiple signature fields
   - Watermarks and security features

6. **Analytics:**
   - Certificate generation statistics
   - Email delivery tracking
   - Template usage analytics

---

## Testing Considerations

### Test Scenarios

1. **Template Creation:**
   - Create training center template
   - Create instructor template
   - Create course template
   - Validate restrictions

2. **Certificate Generation:**
   - Generate training center certificate
   - Generate instructor certificate
   - Handle missing templates
   - Handle generation errors

3. **Email Sending:**
   - Verify email delivery
   - Check PDF attachments
   - Validate email content
   - Test multiple course certificates

4. **Integration:**
   - Test approval flow
   - Test payment flow
   - Verify automatic generation
   - Check error handling

### Edge Cases

- Missing templates
- Invalid template data
- Email delivery failures
- Large file sizes
- Multiple simultaneous requests
- Template updates during generation

---

## Support and Troubleshooting

### Common Issues

**Certificates Not Generated:**
- Check if active template exists
- Verify template status is "active"
- Check logs for errors
- Verify ACC ownership

**Emails Not Received:**
- Check queue status
- Verify email configuration
- Check spam folder
- Verify email address

**Template Not Found:**
- Ensure template is created
- Verify template type matches
- Check template status
- Verify ACC ownership

### Debugging Steps

1. Check application logs
2. Verify queue workers running
3. Test template generation manually
4. Verify email configuration
5. Check file storage permissions
6. Validate template HTML
7. Test with sample data

---

## Conclusion

This feature provides a comprehensive certificate generation and distribution system for ACC administrators. It automates the process of creating and sending authorization certificates, reducing manual work and ensuring consistent, professional certificates for all authorized training centers and instructors.

The system is designed to be flexible, allowing ACCs to customize certificate templates while maintaining automatic generation and distribution. Error handling ensures that certificate generation issues don't impact the core approval and payment processes.

