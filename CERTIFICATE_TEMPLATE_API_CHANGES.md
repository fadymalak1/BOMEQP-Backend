# Certificate Template API Changes

## Overview
This document outlines the recent changes made to the Certificate Template API endpoints. These changes enhance the flexibility of template management and improve data integrity.

---

## 1. Template Creation - Category or Course Selection

### What Changed
ACCs can now create certificate templates in two ways:
- **Category-level templates**: Apply to all courses within a specific category
- **Course-specific templates**: Apply only to a specific course

### API Endpoint
**POST** `/acc/certificate-templates`

### Request Parameters
- `category_id` (optional): ID of the category - applies template to all courses in this category
- `course_id` (optional): ID of the course - applies template to this specific course only
- `name` (required): Name of the template
- `template_html` (optional): HTML content of the template
- `status` (required): Status of the template (`active` or `inactive`)

### Validation Rules
- **Either** `category_id` **OR** `course_id` must be provided (not both)
- At least one of these fields is required
- If `course_id` is provided, the course must belong to the ACC creating the template
- Cannot create duplicate templates for the same category or course

### Response
Returns the created template with both category and course relationships loaded (if applicable).

### Example Scenarios
1. **Creating a category template**: Provide `category_id` only
   - Template will be used for all courses in that category
   
2. **Creating a course template**: Provide `course_id` only
   - Template will be used only for that specific course

---

## 2. Duplicate Template Prevention

### What Changed
The system now prevents creating multiple templates for the same category or course within the same ACC.

### Validation Logic
- **Category templates**: Only one template per category per ACC
- **Course templates**: Only one template per course per ACC

### Error Response
When attempting to create a duplicate template, the API returns:
- **Status Code**: 422 (Unprocessable Entity)
- **Message**: Indicates which field has a conflict
- **Existing Template Info**: Includes ID and name of the existing template

### Update Behavior
When updating a template:
- Cannot change to a category or course that already has a template (unless it's the same template being updated)
- Cannot clear both `category_id` and `course_id` at the same time
- Can switch from category to course (or vice versa) if no conflict exists

---

## 3. Template Deletion - Preserve Certificates

### What Changed
Templates can now be deleted even when certificates are using them. The certificates are preserved, but their template reference is removed.

### API Endpoint
**DELETE** `/acc/certificate-templates/{id}`

### Behavior
- **Template deletion**: The template is permanently deleted
- **Background image**: Automatically removed from storage
- **Certificates**: All certificates using this template are preserved
- **Template reference**: The `template_id` field in certificates is automatically set to `null`

### Response
- **Status Code**: 200 (Success)
- **Message**: Confirmation message with count of preserved certificates
- **Certificates Preserved**: Number of certificates that were using the deleted template

### Important Notes
- Certificates remain fully functional after template deletion
- The certificate PDF files are not affected
- Only the template reference is removed from certificates
- This allows for template cleanup without losing certificate data

---

## 4. Template Listing and Details

### What Changed
Template endpoints now include both category and course information in responses.

### API Endpoints
- **GET** `/acc/certificate-templates` - List all templates
- **GET** `/acc/certificate-templates/{id}` - Get template details

### Response Includes
- Template details
- Category information (if template is category-level)
- Course information (if template is course-specific)
- All other template properties (name, HTML, status, etc.)

---

## Migration Requirements

### Database Changes
A database migration is required to support these changes:
- Adds `course_id` column to `certificate_templates` table
- Makes `category_id` nullable in `certificate_templates` table
- Updates foreign key constraint in `certificates` table to preserve certificates when templates are deleted

### Running the Migration
Run the following migrations in order:
1. `2026_01_28_000001_add_course_id_to_certificate_templates_table.php`
2. `2026_01_28_000002_modify_template_id_foreign_key_in_certificates_table.php`

---

## Summary of Benefits

1. **Flexibility**: ACCs can create templates at both category and course levels
2. **Data Integrity**: Prevents duplicate templates and ensures one template per category/course
3. **Data Preservation**: Certificates are never lost when templates are deleted
4. **Better Organization**: Clear distinction between category-wide and course-specific templates

---

## API Response Examples

### Successful Template Creation (Category)
```json
{
  "template": {
    "id": 1,
    "acc_id": 5,
    "category_id": 3,
    "course_id": null,
    "name": "Fire Safety Category Template",
    "status": "active",
    "category": { ... },
    "course": null
  }
}
```

### Successful Template Creation (Course)
```json
{
  "template": {
    "id": 2,
    "acc_id": 5,
    "category_id": null,
    "course_id": 12,
    "name": "Advanced Fire Safety Course Template",
    "status": "active",
    "category": null,
    "course": { ... }
  }
}
```

### Duplicate Template Error
```json
{
  "message": "A certificate template already exists for this category",
  "errors": {
    "category_id": [
      "A certificate template already exists for this category. Please update the existing template or delete it first."
    ]
  },
  "existing_template": {
    "id": 1,
    "name": "Existing Template Name"
  }
}
```

### Successful Template Deletion
```json
{
  "message": "Template deleted successfully. 5 certificate(s) that were using this template have been preserved, but their template reference has been removed.",
  "certificates_preserved": 5
}
```

---

## Notes for Frontend Developers

1. **Template Selection**: When creating a template, provide a UI that allows selecting either a category OR a course (not both)
2. **Duplicate Prevention**: Show appropriate error messages when attempting to create duplicate templates
3. **Template Deletion**: Inform users that certificates will be preserved when deleting templates
4. **Template Display**: Show whether a template is category-level or course-specific in the UI
5. **Certificate Status**: After template deletion, certificates may show as having no template reference

---

## Backward Compatibility

- Existing templates with only `category_id` will continue to work
- Existing certificates will remain functional
- No breaking changes to existing API endpoints
- All changes are additive and optional

