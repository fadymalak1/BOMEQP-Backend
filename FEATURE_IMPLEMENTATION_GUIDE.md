# Feature Implementation Guide

This document describes the implementation requirements and tasks for the following features:
1. Invoice & Verification
2. Certificate Structure
3. Localization
4. System Optimization

---

## 1. Invoice & Verification

### Overview
Implement a comprehensive invoice upload and verification system for certificate code purchases. This allows training centers to upload payment invoices for manual verification by administrators when using offline payment methods (bank transfer, etc.).

### Current State
- Transaction system exists with support for multiple payment methods
- Certificate code purchase workflow is in place
- No invoice upload or verification mechanism exists
- Payment verification is currently only automated (Stripe)

### Implementation Tasks

#### 1.1 Database Schema

**Create Invoice Verification Table:**
```sql
- invoice_verifications
  - id (primary key)
  - transaction_id (foreign key to transactions)
  - code_batch_id (foreign key to code_batches, nullable)
  - invoice_file_url (string) - Path to uploaded invoice document
  - invoice_file_name (string) - Original filename
  - invoice_file_size (integer) - File size in bytes
  - invoice_file_type (string) - MIME type
  - payment_amount (decimal 10,2) - Amount from invoice (user-entered)
  - verified_amount (decimal 10,2) - Amount verified by admin
  - status (enum: pending, verified, rejected, under_review)
  - verification_notes (text, nullable) - Admin notes
  - verified_by (foreign key to users, nullable) - Admin who verified
  - verified_at (timestamp, nullable)
  - rejection_reason (text, nullable)
  - uploaded_by (foreign key to users) - Training center user who uploaded
  - uploaded_at (timestamp)
  - created_at (timestamp)
  - updated_at (timestamp)
```

**Migration Tasks:**
- [ ] Create migration file: `create_invoice_verifications_table.php`
- [ ] Add indexes on `transaction_id`, `status`, `uploaded_by`, `verified_by`
- [ ] Add foreign key constraints with appropriate cascade rules

#### 1.2 Model Implementation

**Create InvoiceVerification Model:**
- [ ] Create `app/Models/InvoiceVerification.php`
- [ ] Define fillable fields
- [ ] Set up relationships:
  - `belongsTo(Transaction::class)`
  - `belongsTo(CodeBatch::class, 'code_batch_id')`
  - `belongsTo(User::class, 'uploaded_by')`
  - `belongsTo(User::class, 'verified_by')`
- [ ] Add casts for dates and decimals
- [ ] Add scopes for filtering by status
- [ ] Add accessors/mutators if needed

**Update Transaction Model:**
- [ ] Add `hasOne(InvoiceVerification::class)` relationship
- [ ] Add helper methods to check if transaction has pending invoice

**Update CodeBatch Model:**
- [ ] Add `hasOne(InvoiceVerification::class)` relationship

#### 1.3 File Upload Handling

**Storage Configuration:**
- [ ] Configure storage disk for invoice files (local or cloud)
- [ ] Set up proper directory structure: `invoices/{year}/{month}/`
- [ ] Implement file validation:
  - Allowed file types: PDF, JPG, JPEG, PNG
  - Maximum file size: 10MB
  - File name sanitization

**File Upload Service:**
- [ ] Create `app/Services/InvoiceUploadService.php`
- [ ] Implement file upload method
- [ ] Implement file deletion method
- [ ] Add virus scanning (optional but recommended)
- [ ] Generate unique file names to prevent conflicts

#### 1.4 API Endpoints

**Training Center Endpoints:**

**POST `/training-center/code-purchases/{id}/upload-invoice`**
- [ ] Allow training center to upload invoice for a code purchase
- [ ] Validate file upload
- [ ] Store invoice file
- [ ] Create invoice_verification record
- [ ] Update transaction status to 'pending_verification'
- [ ] Send notification to admins
- [ ] Return invoice verification details

**GET `/training-center/invoice-verifications`**
- [ ] List all invoice verifications for the training center
- [ ] Filter by status (pending, verified, rejected)
- [ ] Pagination support
- [ ] Include transaction and code batch details

**GET `/training-center/invoice-verifications/{id}`**
- [ ] Get specific invoice verification details
- [ ] Include invoice file URL
- [ ] Include verification status and notes

**Admin Endpoints (Group Admin & ACC Admin):**

**GET `/admin/invoice-verifications`**
- [ ] List all pending invoice verifications
- [ ] Filter by status, date range, training center
- [ ] Pagination support
- [ ] Include training center and transaction details
- [ ] Sort by upload date (oldest first)

**GET `/admin/invoice-verifications/{id}`**
- [ ] Get invoice verification details
- [ ] Include invoice file download link
- [ ] Include transaction and code batch information
- [ ] Include training center details

**POST `/admin/invoice-verifications/{id}/verify`**
- [ ] Verify and approve invoice
- [ ] Allow admin to enter/confirm payment amount
- [ ] Update invoice_verification status to 'verified'
- [ ] Update transaction status to 'completed'
- [ ] Issue certificate codes if not already issued
- [ ] Send notification to training center
- [ ] Record verification details (verified_by, verified_at, verified_amount)

**POST `/admin/invoice-verifications/{id}/reject`**
- [ ] Reject invoice verification
- [ ] Require rejection reason
- [ ] Update invoice_verification status to 'rejected'
- [ ] Update transaction status to 'failed'
- [ ] Send notification to training center with rejection reason
- [ ] Allow training center to re-upload corrected invoice

**POST `/admin/invoice-verifications/{id}/request-review`**
- [ ] Mark invoice as under review
- [ ] Add verification notes
- [ ] Update status to 'under_review'
- [ ] Send notification to training center

#### 1.5 Code Purchase Workflow Updates

**Update Code Purchase Endpoint:**
- [ ] Modify `POST /training-center/code-purchases` endpoint
- [ ] Add optional `invoice_file` parameter
- [ ] If invoice provided:
  - Create transaction with status 'pending_verification'
  - Create invoice_verification record
  - Do NOT issue codes immediately
  - Send notification to admins
- [ ] If no invoice (online payment):
  - Continue with existing Stripe payment flow
  - Issue codes immediately upon payment success

**Update Code Issuance Logic:**
- [ ] Modify code issuance to check invoice verification status
- [ ] Only issue codes when:
  - Payment is completed (online)
  - OR invoice is verified (offline)
- [ ] Prevent duplicate code issuance

#### 1.6 Notification System

**Notification Types:**
- [ ] `invoice_uploaded` - Sent to admins when invoice is uploaded
- [ ] `invoice_verified` - Sent to training center when invoice is verified
- [ ] `invoice_rejected` - Sent to training center when invoice is rejected
- [ ] `invoice_under_review` - Sent to training center when invoice is under review

**Notification Implementation:**
- [ ] Create notification classes in `app/Notifications/`
- [ ] Include relevant details (transaction ID, amount, status, etc.)
- [ ] Support email and in-app notifications
- [ ] Add notification preferences for admins

#### 1.7 Validation & Security

**Validation Rules:**
- [ ] File type validation (PDF, JPG, PNG only)
- [ ] File size validation (max 10MB)
- [ ] Transaction ownership validation
- [ ] Invoice verification status validation
- [ ] Admin role validation for verification endpoints

**Security Measures:**
- [ ] File upload sanitization
- [ ] Secure file storage (outside public directory)
- [ ] Access control for invoice file downloads
- [ ] Rate limiting on upload endpoints
- [ ] Audit logging for verification actions

#### 1.8 Testing Requirements

**Unit Tests:**
- [ ] Test invoice upload functionality
- [ ] Test file validation
- [ ] Test invoice verification workflow
- [ ] Test code issuance after verification

**Integration Tests:**
- [ ] Test complete code purchase flow with invoice
- [ ] Test admin verification process
- [ ] Test notification delivery
- [ ] Test file storage and retrieval

**Manual Testing:**
- [ ] Upload invoice with valid file
- [ ] Upload invoice with invalid file (should fail)
- [ ] Admin verifies invoice
- [ ] Admin rejects invoice
- [ ] Training center views verification status
- [ ] Codes are issued after verification

---

## 2. Certificate Structure

### Overview
Enhance and standardize the certificate structure to support flexible template configurations, dynamic field mapping, and improved certificate data management.

### Current State
- Certificate model exists with basic fields
- Certificate templates support HTML templates
- Certificate generation is functional
- Template supports multiple courses (recently implemented)
- Some fields may need restructuring or enhancement

### Implementation Tasks

#### 2.1 Database Schema Review & Updates

**Review Certificate Table Structure:**
- [ ] Audit current `certificates` table schema
- [ ] Identify missing or redundant fields
- [ ] Plan schema improvements:
  - Additional metadata fields
  - Certificate version tracking
  - Digital signature fields
  - QR code data
  - Certificate language/locale

**Potential Schema Enhancements:**
```sql
- certificates (existing + additions)
  - certificate_version (integer, default 1) - For version tracking
  - certificate_language (string, default 'en') - Language of certificate
  - qr_code_data (text, nullable) - QR code content
  - digital_signature_url (string, nullable) - Digital signature image
  - metadata_json (json, nullable) - Additional flexible data
  - issued_by_user_id (foreign key to users) - User who issued certificate
  - revocation_reason (text, nullable) - If certificate is revoked
  - revoked_by (foreign key to users, nullable)
  - revoked_at (timestamp, nullable)
```

**Migration Tasks:**
- [ ] Create migration to add new fields if needed
- [ ] Add indexes on new searchable fields
- [ ] Update existing certificates with default values

#### 2.2 Certificate Template Structure

**Template Configuration Enhancement:**
- [ ] Review `certificate_templates` table structure
- [ ] Enhance `config_json` field usage:
  - Field mapping configuration
  - Layout configuration
  - Font and styling options
  - Multi-language support
  - Dynamic field visibility rules

**Template Variable System:**
- [ ] Document all available template variables
- [ ] Create variable mapping system:
  - Trainee information variables
  - Course information variables
  - Date and time variables
  - Training center variables
  - ACC variables
  - Custom variables from metadata

**Template Validation:**
- [ ] Validate template HTML structure
- [ ] Validate template variables exist
- [ ] Validate required fields are present
- [ ] Test template rendering with sample data

#### 2.3 Certificate Data Model Enhancements

**Update Certificate Model:**
- [ ] Add new relationships if needed
- [ ] Add accessor methods for computed fields
- [ ] Add mutator methods for data formatting
- [ ] Implement certificate versioning logic
- [ ] Add methods for certificate validation
- [ ] Add methods for certificate revocation

**Certificate Service:**
- [ ] Create/update `app/Services/CertificateService.php`
- [ ] Implement certificate generation logic
- [ ] Implement template variable replacement
- [ ] Implement PDF generation
- [ ] Implement QR code generation
- [ ] Implement digital signature integration
- [ ] Implement certificate validation logic

#### 2.4 Certificate Field Mapping

**Dynamic Field Mapping:**
- [ ] Create field mapping configuration system
- [ ] Map database fields to template variables
- [ ] Support custom field mappings per template
- [ ] Support conditional field display
- [ ] Support field formatting (dates, numbers, text)

**Field Mapping Configuration:**
```json
{
  "fields": {
    "trainee_name": {
      "source": "certificates.trainee_name",
      "format": "uppercase",
      "required": true
    },
    "course_name": {
      "source": "courses.name",
      "format": "title",
      "required": true
    },
    "issue_date": {
      "source": "certificates.issue_date",
      "format": "date",
      "locale": "en",
      "required": true
    }
  }
}
```

#### 2.5 Certificate Generation Improvements

**Generation Process:**
- [ ] Enhance certificate generation endpoint
- [ ] Implement batch certificate generation
- [ ] Add certificate preview functionality
- [ ] Implement certificate regeneration (with version tracking)
- [ ] Add certificate validation before generation

**PDF Generation:**
- [ ] Review current PDF generation library
- [ ] Ensure high-quality PDF output
- [ ] Support custom page sizes
- [ ] Support background images
- [ ] Support embedded fonts
- [ ] Optimize PDF file size

**QR Code Integration:**
- [ ] Generate QR codes for certificates
- [ ] QR code should link to verification page
- [ ] Include verification code in QR data
- [ ] Support custom QR code styling
- [ ] Test QR code scanning and verification

#### 2.6 Certificate Verification Enhancements

**Verification System:**
- [ ] Enhance public verification endpoint
- [ ] Add verification code validation
- [ ] Add certificate status checking (valid, revoked, expired)
- [ ] Return comprehensive certificate data
- [ ] Add verification history tracking
- [ ] Support QR code verification

**Verification Response:**
- [ ] Include all certificate details
- [ ] Include course information
- [ ] Include training center information
- [ ] Include ACC information
- [ ] Include certificate metadata
- [ ] Include verification timestamp

#### 2.7 Certificate Revocation System

**Revocation Functionality:**
- [ ] Create certificate revocation endpoint (admin only)
- [ ] Allow revocation with reason
- [ ] Update certificate status to 'revoked'
- [ ] Record revocation details
- [ ] Send notification to relevant parties
- [ ] Update verification endpoint to show revoked status

**Revocation API:**
- [ ] `POST /admin/certificates/{id}/revoke`
- [ ] Require revocation reason
- [ ] Update certificate status
- [ ] Log revocation action
- [ ] Send notifications

#### 2.8 Certificate Export & Reporting

**Export Functionality:**
- [ ] Create certificate export endpoint
- [ ] Support CSV export
- [ ] Support Excel export
- [ ] Include all certificate fields
- [ ] Support filtering and date ranges
- [ ] Support bulk PDF download

**Reporting:**
- [ ] Certificate issuance statistics
- [ ] Certificate status distribution
- [ ] Certificate expiration tracking
- [ ] Certificate verification statistics

#### 2.9 Testing Requirements

**Unit Tests:**
- [ ] Test certificate generation
- [ ] Test template variable replacement
- [ ] Test PDF generation
- [ ] Test QR code generation
- [ ] Test certificate validation
- [ ] Test certificate revocation

**Integration Tests:**
- [ ] Test complete certificate creation flow
- [ ] Test certificate verification
- [ ] Test certificate export
- [ ] Test template selection logic

**Manual Testing:**
- [ ] Generate certificate with various templates
- [ ] Verify certificate PDF quality
- [ ] Test QR code scanning
- [ ] Test certificate verification
- [ ] Test certificate revocation
- [ ] Test certificate export

---

## 3. Localization

### Overview
Implement comprehensive multi-language support throughout the application, allowing users to interact with the system in their preferred language. This includes API responses, error messages, notifications, and certificate content.

### Current State
- Basic locale configuration exists in `config/app.php`
- `SetUserLocale` middleware exists but may need enhancement
- No translation files exist
- API responses are in English only
- Certificates are generated in single language

### Implementation Tasks

#### 3.1 Locale Configuration

**Supported Languages:**
- [ ] Define list of supported languages (e.g., English, Arabic, French, etc.)
- [ ] Configure language codes (ISO 639-1)
- [ ] Set default language
- [ ] Set fallback language
- [ ] Configure language-specific date/time formats
- [ ] Configure language-specific number formats

**Configuration Updates:**
- [ ] Update `config/app.php` with supported locales
- [ ] Create `config/localization.php` for custom settings
- [ ] Configure locale-specific settings:
  - Date formats
  - Time formats
  - Number formats
  - Currency formats
  - Text direction (RTL/LTR)

#### 3.2 User Locale Management

**User Model Updates:**
- [ ] Add `locale` field to `users` table (string, default 'en')
- [ ] Add `timezone` field to `users` table (string, nullable)
- [ ] Create migration for user locale fields
- [ ] Update User model to include locale fields
- [ ] Add locale accessor/mutator methods

**Locale Selection:**
- [ ] Add locale selection to user profile
- [ ] Add locale selection to registration
- [ ] Add locale selection to settings page
- [ ] Store user locale preference
- [ ] Apply user locale automatically on login

#### 3.3 Middleware Enhancement

**Update SetUserLocale Middleware:**
- [ ] Review current `SetUserLocale` middleware
- [ ] Enhance to read locale from:
  - User preference (database)
  - Request header (`Accept-Language`)
  - Query parameter (`?locale=en`)
  - Session (if applicable)
- [ ] Set application locale based on priority
- [ ] Validate locale before setting
- [ ] Fallback to default locale if invalid

**Middleware Priority:**
1. User database preference (if authenticated)
2. Request header (`Accept-Language`)
3. Query parameter
4. Session
5. Default locale

#### 3.4 Translation Files

**Create Translation Structure:**
- [ ] Create `lang/` directory structure:
  ```
  lang/
    en/
      auth.php
      validation.php
      messages.php
      errors.php
      certificates.php
      courses.php
      ...
    ar/
      auth.php
      validation.php
      messages.php
      errors.php
      certificates.php
      courses.php
      ...
  ```

**Translation Files to Create:**
- [ ] `auth.php` - Authentication messages
- [ ] `validation.php` - Validation error messages
- [ ] `messages.php` - General application messages
- [ ] `errors.php` - Error messages
- [ ] `certificates.php` - Certificate-related messages
- [ ] `courses.php` - Course-related messages
- [ ] `payments.php` - Payment-related messages
- [ ] `notifications.php` - Notification messages
- [ ] `admin.php` - Admin panel messages
- [ ] `common.php` - Common UI elements

**Translation Content:**
- [ ] Translate all API response messages
- [ ] Translate validation error messages
- [ ] Translate notification messages
- [ ] Translate email templates
- [ ] Translate certificate templates
- [ ] Translate error messages

#### 3.5 API Response Localization

**Response Localization:**
- [ ] Create response helper/trait for localized responses
- [ ] Update all API controllers to use translations
- [ ] Replace hardcoded strings with `__()` or `trans()` functions
- [ ] Localize error messages
- [ ] Localize success messages
- [ ] Localize validation messages

**Response Format:**
```json
{
  "message": "Certificate generated successfully", // Translated
  "data": {...},
  "locale": "en"
}
```

**Controller Updates:**
- [ ] Update `AuthController` with translations
- [ ] Update `CertificateController` with translations
- [ ] Update `CourseController` with translations
- [ ] Update `PaymentController` with translations
- [ ] Update all other controllers with translations

#### 3.6 Database Content Localization

**Multi-language Content Tables:**
- [ ] Design approach for database content localization:
  - Option 1: Separate translation tables
  - Option 2: JSON columns with translations
  - Option 3: Separate rows per language

**Recommended Approach (JSON Columns):**
```sql
- courses
  - name (string) - Default language
  - name_translations (json) - {"ar": "اسم الدورة", "fr": "Nom du cours"}
  - description (text) - Default language
  - description_translations (json) - Multi-language descriptions
```

**Tables Requiring Localization:**
- [ ] `courses` - Course names and descriptions
- [ ] `categories` - Category names
- [ ] `certificate_templates` - Template content
- [ ] `materials` - Material titles and descriptions
- [ ] `notifications` - Notification content

**Migration Tasks:**
- [ ] Add translation columns to relevant tables
- [ ] Migrate existing content to translation structure
- [ ] Create helper methods for retrieving translated content

#### 3.7 Certificate Localization

**Certificate Template Localization:**
- [ ] Support multi-language certificate templates
- [ ] Store template content per language
- [ ] Generate certificates in user's preferred language
- [ ] Support RTL languages in templates
- [ ] Localize certificate field labels
- [ ] Localize date formats in certificates

**Certificate Generation:**
- [ ] Detect user/trainee locale preference
- [ ] Select appropriate template language
- [ ] Generate certificate in correct language
- [ ] Format dates according to locale
- [ ] Format numbers according to locale

#### 3.8 Email & Notification Localization

**Email Templates:**
- [ ] Create localized email templates
- [ ] Support HTML emails with translations
- [ ] Localize email subject lines
- [ ] Localize email body content
- [ ] Support RTL email layouts

**Notification Localization:**
- [ ] Localize notification titles
- [ ] Localize notification messages
- [ ] Store notifications in user's locale
- [ ] Support locale-specific notification templates

#### 3.9 Frontend Integration

**API Locale Headers:**
- [ ] Document `Accept-Language` header usage
- [ ] Document locale query parameter
- [ ] Provide locale selection endpoint
- [ ] Return available locales endpoint

**Locale Endpoints:**
- [ ] `GET /locales` - List available locales
- [ ] `GET /locales/current` - Get current locale
- [ ] `POST /auth/profile/locale` - Update user locale

#### 3.10 Date & Time Localization

**Date/Time Formatting:**
- [ ] Use Carbon for date localization
- [ ] Configure locale-specific date formats
- [ ] Format dates in API responses
- [ ] Format dates in certificates
- [ ] Support timezone conversion
- [ ] Display dates in user's timezone

**Number Formatting:**
- [ ] Format numbers according to locale
- [ ] Format currency according to locale
- [ ] Support locale-specific decimal separators
- [ ] Support locale-specific thousand separators

#### 3.11 Testing Requirements

**Unit Tests:**
- [ ] Test locale detection
- [ ] Test translation retrieval
- [ ] Test date/time formatting
- [ ] Test number formatting

**Integration Tests:**
- [ ] Test API responses in different locales
- [ ] Test certificate generation in different languages
- [ ] Test email sending in different languages
- [ ] Test notification localization

**Manual Testing:**
- [ ] Switch between different locales
- [ ] Verify all messages are translated
- [ ] Verify dates are formatted correctly
- [ ] Verify certificates are generated in correct language
- [ ] Test RTL language support

---

## 4. System Optimization

### Overview
Improve system performance, scalability, and efficiency through database optimization, caching strategies, query optimization, API response optimization, and code refactoring.

### Current State
- Basic caching configuration exists (database cache driver)
- No comprehensive caching strategy implemented
- Database queries may not be optimized
- API responses may include unnecessary data
- No performance monitoring in place

### Implementation Tasks

#### 4.1 Database Optimization

**Query Optimization:**
- [ ] Audit all database queries for N+1 problems
- [ ] Add eager loading where needed (`with()`, `load()`)
- [ ] Review and optimize slow queries
- [ ] Add database indexes on frequently queried columns:
  - Foreign keys
  - Status fields
  - Date fields used in WHERE clauses
  - Search fields
  - Composite indexes for common query patterns

**Index Audit:**
- [ ] Review `certificates` table indexes
- [ ] Review `transactions` table indexes
- [ ] Review `users` table indexes
- [ ] Review `courses` table indexes
- [ ] Review `training_classes` table indexes
- [ ] Add missing indexes based on query patterns

**Query Performance:**
- [ ] Use `select()` to limit columns retrieved
- [ ] Use `chunk()` for large dataset processing
- [ ] Use `cursor()` for memory-efficient iteration
- [ ] Avoid `get()` on large datasets without pagination
- [ ] Optimize joins and relationships

**Database Configuration:**
- [ ] Review database connection pool settings
- [ ] Configure query logging for development
- [ ] Set up database query monitoring
- [ ] Review and optimize database schema

#### 4.2 Caching Strategy

**Cache Configuration:**
- [ ] Review current cache driver (database)
- [ ] Consider upgrading to Redis for better performance
- [ ] Configure cache TTL appropriately
- [ ] Set up cache tags for better invalidation
- [ ] Configure cache prefixes

**Cache Implementation:**
- [ ] Cache frequently accessed data:
  - [ ] User permissions and roles
  - [ ] Course lists
  - [ ] Category lists
  - [ ] ACC lists
  - [ ] Training center lists
  - [ ] Certificate templates
  - [ ] Configuration settings
  - [ ] Translation files

**Cache Invalidation:**
- [ ] Implement cache invalidation on data updates
- [ ] Use cache tags for grouped invalidation
- [ ] Clear cache on model updates
- [ ] Implement cache warming for critical data

**Cache Examples:**
```php
// Cache course list
Cache::remember('courses:list', 3600, function() {
    return Course::all();
});

// Cache with tags
Cache::tags(['courses', 'acc:1'])->remember('courses:acc:1', 3600, function() {
    return Course::where('acc_id', 1)->get();
});
```

#### 4.3 API Response Optimization

**Response Size Reduction:**
- [ ] Use API Resources for consistent formatting
- [ ] Implement sparse fieldsets (field selection)
- [ ] Remove unnecessary data from responses
- [ ] Use pagination for list endpoints
- [ ] Implement response compression (gzip)

**API Resources:**
- [ ] Create Resource classes for major models:
  - [ ] `UserResource`
  - [ ] `CertificateResource`
  - [ ] `CourseResource`
  - [ ] `TransactionResource`
  - [ ] `TrainingCenterResource`
  - [ ] `ACCResource`

**Pagination:**
- [ ] Ensure all list endpoints use pagination
- [ ] Set appropriate page sizes
- [ ] Include pagination metadata
- [ ] Support cursor-based pagination for large datasets

**Field Selection:**
- [ ] Implement `?fields=` parameter support
- [ ] Allow clients to request only needed fields
- [ ] Reduce response payload size

#### 4.4 Code Optimization

**Code Refactoring:**
- [ ] Review and refactor large controller methods
- [ ] Extract business logic to Service classes
- [ ] Reduce code duplication
- [ ] Improve code organization
- [ ] Apply SOLID principles

**Service Layer:**
- [ ] Create service classes for complex operations:
  - [ ] `CertificateGenerationService`
  - [ ] `PaymentProcessingService`
  - [ ] `NotificationService`
  - [ ] `InvoiceVerificationService`
  - [ ] `CodePurchaseService`

**Repository Pattern (Optional):**
- [ ] Consider implementing Repository pattern
- [ ] Abstract database queries
- [ ] Improve testability
- [ ] Centralize query logic

**Code Quality:**
- [ ] Run static analysis tools (PHPStan, Psalm)
- [ ] Fix code quality issues
- [ ] Improve type hints
- [ ] Add PHPDoc comments
- [ ] Follow PSR standards

#### 4.5 File Storage Optimization

**Storage Strategy:**
- [ ] Review file storage implementation
- [ ] Consider cloud storage (S3, etc.) for production
- [ ] Implement file CDN for faster delivery
- [ ] Optimize image uploads (resize, compress)
- [ ] Implement file cleanup for orphaned files

**File Optimization:**
- [ ] Compress uploaded images
- [ ] Generate thumbnails for images
- [ ] Optimize PDF file sizes
- [ ] Implement lazy loading for file URLs
- [ ] Use signed URLs for secure file access

#### 4.6 Background Job Optimization

**Queue Configuration:**
- [ ] Review queue driver (database/Redis)
- [ ] Consider Redis queue for better performance
- [ ] Configure appropriate queue workers
- [ ] Set up failed job handling
- [ ] Implement job prioritization

**Job Optimization:**
- [ ] Move heavy operations to background jobs:
  - [ ] Certificate PDF generation
  - [ ] Email sending
  - [ ] File processing
  - [ ] Report generation
  - [ ] Bulk operations

**Job Monitoring:**
- [ ] Monitor queue length
- [ ] Monitor failed jobs
- [ ] Set up job retry logic
- [ ] Implement job timeout handling

#### 4.7 API Rate Limiting

**Rate Limiting Implementation:**
- [ ] Configure API rate limiting
- [ ] Set different limits for different endpoints
- [ ] Set different limits for different user roles
- [ ] Implement rate limit headers in responses
- [ ] Handle rate limit exceeded gracefully

**Rate Limit Configuration:**
```php
// In routes/api.php or RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

#### 4.8 Monitoring & Logging

**Performance Monitoring:**
- [ ] Set up application performance monitoring (APM)
- [ ] Monitor slow queries
- [ ] Monitor API response times
- [ ] Monitor memory usage
- [ ] Monitor CPU usage

**Logging Enhancement:**
- [ ] Implement structured logging
- [ ] Log important operations:
  - [ ] Payment transactions
  - [ ] Certificate generation
  - [ ] User actions
  - [ ] Admin actions
  - [ ] Errors and exceptions
- [ ] Set up log rotation
- [ ] Implement log levels appropriately

**Error Tracking:**
- [ ] Set up error tracking service (Sentry, etc.)
- [ ] Track and monitor errors
- [ ] Set up error alerts
- [ ] Implement error reporting

#### 4.9 Database Connection Optimization

**Connection Pooling:**
- [ ] Review database connection settings
- [ ] Configure connection pool size
- [ ] Implement connection reuse
- [ ] Monitor connection usage

**Read/Write Separation (Optional):**
- [ ] Consider read replica for read-heavy operations
- [ ] Configure read/write database connections
- [ ] Route read queries to replica
- [ ] Route write queries to primary

#### 4.10 Security Optimization

**Security Enhancements:**
- [ ] Review and update security headers
- [ ] Implement CSRF protection for web routes
- [ ] Review input validation
- [ ] Implement SQL injection prevention (use Eloquent/Query Builder)
- [ ] Implement XSS prevention
- [ ] Review file upload security
- [ ] Implement rate limiting on authentication endpoints

**Authentication Optimization:**
- [ ] Review token expiration times
- [ ] Implement token refresh mechanism
- [ ] Optimize authentication checks
- [ ] Cache user permissions

#### 4.11 Testing & Performance Testing

**Performance Testing:**
- [ ] Create performance test suite
- [ ] Test API response times
- [ ] Test database query performance
- [ ] Test under load
- [ ] Identify bottlenecks

**Load Testing:**
- [ ] Set up load testing tools
- [ ] Test critical endpoints under load
- [ ] Identify maximum capacity
- [ ] Test caching effectiveness
- [ ] Test database performance under load

#### 4.12 Documentation

**Performance Documentation:**
- [ ] Document optimization changes
- [ ] Document caching strategy
- [ ] Document database indexes
- [ ] Document API performance considerations
- [ ] Create performance tuning guide

---

## Implementation Priority

### Phase 1 (High Priority)
1. **Invoice & Verification** - Critical for payment workflow
2. **System Optimization** - Improves overall performance

### Phase 2 (Medium Priority)
3. **Certificate Structure** - Enhances core functionality
4. **Localization** - Improves user experience

### Phase 3 (Ongoing)
- Continuous optimization
- Performance monitoring
- Additional language support
- Feature enhancements

---

## Notes

- All implementations should maintain backward compatibility where possible
- Database migrations should be reversible
- All changes should be tested thoroughly
- API changes should be documented in OpenAPI/Swagger
- Consider creating feature flags for gradual rollout
- Monitor system performance after each optimization
- Keep security as a top priority throughout implementation

---

## Resources

- Laravel Documentation: https://laravel.com/docs
- Laravel Localization: https://laravel.com/docs/localization
- Laravel Caching: https://laravel.com/docs/cache
- Laravel Queues: https://laravel.com/docs/queues
- Database Optimization: https://laravel.com/docs/eloquent#eager-loading

---

*Last Updated: [Current Date]*
*Version: 1.0*


