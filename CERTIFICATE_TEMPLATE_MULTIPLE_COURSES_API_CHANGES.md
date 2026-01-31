# Certificate Template Multiple Courses API Changes

## Overview
The certificate template system has been enhanced to support associating a single template with multiple courses. ACCs can now select one or more courses when creating or updating a certificate template, eliminating the need to select a category. This provides more flexibility in template management and allows templates to be shared across specific courses.

---

## Registration Endpoint

**POST** `/acc/certificate-templates`

**PUT** `/acc/certificate-templates/{id}`

---

## Key Changes

### 1. Template Creation - Multiple Courses Support

**Previous Behavior:**
- ACC could select either a `category_id` OR a single `course_id`
- Template was associated with one course or one category
- Category selection was required if course was not selected

**New Behavior:**
- ACC must provide an array of `course_ids` (one or more courses)
- Template can be associated with multiple courses simultaneously
- Category selection is no longer required or supported for new templates
- All courses in the array must belong to the ACC

---

## API Endpoint Changes

### Create Certificate Template

**Endpoint:** `POST /acc/certificate-templates`

#### Request Body Changes

**Removed Fields:**
- `category_id` - No longer accepted
- `course_id` - Replaced with `course_ids` array

**New Required Field:**
- `course_ids` - Array of course IDs (minimum 1 course required)

#### Request Body Structure

**Required Fields:**
- `course_ids` - Array of integers (e.g., `[1, 2, 3]`)
- `name` - Template name (string)
- `status` - Template status (`active` or `inactive`)

**Optional Fields:**
- `template_html` - HTML template content
- `config_json` - Template configuration JSON

#### Validation Rules

1. **Course IDs Validation:**
   - `course_ids` must be an array
   - Array must contain at least one course ID
   - Each course ID must exist in the courses table
   - All courses must belong to the authenticated ACC

2. **Duplicate Template Prevention:**
   - System checks if any of the provided courses already have a template
   - If any course already has a template, the request is rejected
   - Error message includes the list of conflicting course IDs

3. **ACC Ownership:**
   - All courses must belong to the ACC making the request
   - If any course belongs to a different ACC, the request is rejected

#### Success Response

Returns the created template with associated courses loaded via the `courses` relationship.

#### Error Responses

**422 Validation Error:**
- Missing `course_ids` field
- Empty `course_ids` array
- Invalid course IDs
- Courses already have templates
- Courses don't belong to ACC

**403 Forbidden:**
- One or more courses belong to a different ACC

---

### Update Certificate Template

**Endpoint:** `PUT /acc/certificate-templates/{id}`

#### Request Body Changes

**Removed Fields:**
- `category_id` - No longer accepted
- `course_id` - Replaced with `course_ids` array

**New Optional Field:**
- `course_ids` - Array of course IDs to update the template's course associations

#### Request Body Structure

**Optional Fields:**
- `course_ids` - Array of integers to update course associations
- `name` - Template name
- `template_html` - HTML template content
- `status` - Template status
- `config_json` - Template configuration JSON

#### Validation Rules

1. **Course IDs Validation (if provided):**
   - Must be an array
   - Must contain at least one course ID
   - Each course ID must exist
   - All courses must belong to the authenticated ACC

2. **Duplicate Template Prevention:**
   - Checks if any of the provided courses already have a different template
   - Excludes the current template being updated
   - If conflicts found, request is rejected with conflicting course IDs

3. **Course Association Update:**
   - If `course_ids` is provided, it replaces all existing course associations
   - Uses sync operation (removes old associations, adds new ones)
   - If not provided, existing course associations remain unchanged

#### Success Response

Returns the updated template with associated courses loaded.

#### Error Responses

**422 Validation Error:**
- Invalid course IDs
- Courses already have different templates
- Courses don't belong to ACC

**403 Forbidden:**
- One or more courses belong to a different ACC

**404 Not Found:**
- Template not found or doesn't belong to ACC

---

## Template Selection Logic

### Certificate Generation

When a training center generates a certificate, the system uses the following priority order to find the appropriate template:

1. **Course-Specific Templates (Many-to-Many):**
   - Checks templates that have the course in their `courses` relationship (pivot table)
   - Highest priority for course-specific templates

2. **Legacy Course Templates:**
   - Checks templates with `course_id` field matching the course
   - Maintains backward compatibility with old templates

3. **Category Templates:**
   - Falls back to category-level templates if no course-specific template found
   - Only templates without course associations are considered

### Template Matching Priority

The system prioritizes templates in this order:
1. Templates with course in `courses` relationship (new many-to-many)
2. Templates with matching `course_id` field (legacy)
3. Category templates (fallback)

---

## Database Changes

### New Table: `certificate_template_course`

A pivot table has been created to support the many-to-many relationship between certificate templates and courses.

**Table Structure:**
- `id` - Primary key
- `certificate_template_id` - Foreign key to certificate_templates
- `course_id` - Foreign key to courses
- `created_at` - Timestamp
- `updated_at` - Timestamp
- Unique constraint on `(certificate_template_id, course_id)`

**Relationships:**
- Cascade delete on template deletion
- Cascade delete on course deletion

---

## Backward Compatibility

### Legacy Templates

The system maintains backward compatibility with existing templates:

1. **Legacy Single Course Templates:**
   - Templates with `course_id` field still work
   - Certificate generation checks both new and old methods

2. **Category Templates:**
   - Category-based templates continue to function
   - Used as fallback when no course-specific template exists

3. **Migration Path:**
   - Existing templates can be updated to use the new `course_ids` array
   - Old templates remain functional until updated

---

## Response Format Changes

### Template Response Structure

Templates now include a `courses` relationship in addition to the legacy `course` relationship:

**New Response Fields:**
- `courses` - Array of course objects associated with the template (many-to-many)
- `course` - Single course object (legacy, may be null for new templates)

**Example Response:**
```json
{
  "template": {
    "id": 1,
    "name": "Fire Safety Template",
    "courses": [
      {"id": 1, "name": "Fire Safety Basics"},
      {"id": 2, "name": "Advanced Fire Safety"}
    ],
    "course": null
  }
}
```

---

## Use Cases

### Use Case 1: Single Course Template
ACC creates a template for one specific course:
- Provide `course_ids: [5]`
- Template applies only to course ID 5

### Use Case 2: Multiple Courses Template
ACC creates a template shared across multiple courses:
- Provide `course_ids: [1, 2, 3, 4]`
- Template applies to all four courses
- Useful for courses with similar certificate requirements

### Use Case 3: Template Update
ACC updates template to add/remove courses:
- Provide `course_ids: [1, 2, 3, 4, 5]` to add course 5
- System replaces all associations with the new list
- Previous associations are removed

---

## Validation Error Messages

### Common Errors

1. **Missing Course IDs:**
   - Message: "The course ids field is required."
   - Solution: Provide at least one course ID in the array

2. **Empty Course IDs Array:**
   - Message: "The course ids must have at least 1 items."
   - Solution: Include at least one course ID

3. **Invalid Course ID:**
   - Message: "The selected course ids.0 is invalid."
   - Solution: Ensure all course IDs exist in the system

4. **Course Already Has Template:**
   - Message: "One or more courses already have a certificate template"
   - Details: Includes list of conflicting course IDs
   - Solution: Remove conflicting courses or update existing template

5. **Course Doesn't Belong to ACC:**
   - Message: "Course ID {id} does not belong to this ACC"
   - Solution: Only select courses that belong to your ACC

---

## Benefits

1. **Flexibility:**
   - ACCs can create templates for multiple courses at once
   - Reduces need to duplicate templates for similar courses

2. **Efficiency:**
   - Single template can serve multiple courses
   - Easier template management

3. **Simplified Workflow:**
   - No need to select category
   - Direct course selection is more intuitive

4. **Scalability:**
   - Easy to add/remove courses from templates
   - Supports growing course catalogs

---

## Migration Notes

### For Existing Templates

1. **Category Templates:**
   - Continue to work as fallback templates
   - Can be updated to use course associations if needed

2. **Single Course Templates:**
   - Continue to work via legacy `course_id` field
   - Can be migrated to new `courses` relationship

3. **No Breaking Changes:**
   - Existing API calls continue to work
   - Certificate generation maintains backward compatibility

---

## Frontend Implementation Guidelines

### Form Structure

1. **Course Selection:**
   - Use multi-select component for course selection
   - Allow selecting one or more courses
   - Display course names for better UX

2. **Validation:**
   - Show error messages for invalid course selections
   - Highlight courses that already have templates
   - Prevent submission if validation fails

3. **Template Display:**
   - Show associated courses when displaying template
   - Allow editing course associations
   - Display course count in template list

### User Experience

1. **Course Selection UI:**
   - Multi-select dropdown or checkbox list
   - Search/filter functionality for large course lists
   - Visual indication of selected courses

2. **Template Management:**
   - Show all associated courses in template details
   - Easy way to add/remove courses from template
   - Clear indication of template scope

3. **Error Handling:**
   - Clear error messages for validation failures
   - Highlight conflicting courses
   - Suggest solutions for common errors

---

## Important Notes

1. **Template Uniqueness:**
   - Each course can only have one template
   - If a course is already associated with a template, it cannot be added to another template
   - Update existing template to change course associations

2. **Template Scope:**
   - Templates with course associations take priority over category templates
   - Multiple templates can exist, but each course can only be in one template

3. **Certificate Generation:**
   - System automatically selects the correct template based on course
   - Priority: Course-specific templates > Category templates
   - No manual template selection needed during certificate generation

4. **ACC Ownership:**
   - ACCs can only associate their own courses with templates
   - System validates course ownership before allowing association

5. **Template Updates:**
   - Updating `course_ids` replaces all existing associations
   - To add a course, include it in the array with existing courses
   - To remove a course, exclude it from the array

---

## Example Requests

### Create Template with Multiple Courses

**Request:**
```json
{
  "course_ids": [1, 2, 3],
  "name": "Safety Training Certificate",
  "status": "active",
  "template_html": "<html>...</html>"
}
```

**Response:**
```json
{
  "template": {
    "id": 10,
    "name": "Safety Training Certificate",
    "courses": [
      {"id": 1, "name": "Basic Safety"},
      {"id": 2, "name": "Advanced Safety"},
      {"id": 3, "name": "Safety Management"}
    ]
  }
}
```

### Update Template Courses

**Request:**
```json
{
  "course_ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
  "message": "Template updated successfully",
  "template": {
    "id": 10,
    "name": "Safety Training Certificate",
    "courses": [
      {"id": 1, "name": "Basic Safety"},
      {"id": 2, "name": "Advanced Safety"},
      {"id": 3, "name": "Safety Management"},
      {"id": 4, "name": "Safety Inspection"},
      {"id": 5, "name": "Safety Compliance"}
    ]
  }
}
```

---

## Summary of Changes

### API Changes
- ✅ `course_id` replaced with `course_ids` array
- ✅ `category_id` no longer required or accepted
- ✅ Multiple courses can be associated with one template
- ✅ Template update supports course association changes

### Database Changes
- ✅ New pivot table for many-to-many relationship
- ✅ Backward compatibility maintained

### Behavior Changes
- ✅ Template selection prioritizes course-specific templates
- ✅ Category templates used as fallback only
- ✅ Each course can only be in one template

### Response Changes
- ✅ Templates include `courses` array
- ✅ Legacy `course` field maintained for compatibility

---

## Testing Checklist

When testing the new functionality, verify:

- [ ] Can create template with single course
- [ ] Can create template with multiple courses
- [ ] Cannot create template without course_ids
- [ ] Cannot add course that already has a template
- [ ] Cannot add course from different ACC
- [ ] Can update template to change course associations
- [ ] Can update template without changing courses
- [ ] Certificate generation uses correct template
- [ ] Category templates still work as fallback
- [ ] Legacy templates continue to function
- [ ] Error messages are clear and helpful
- [ ] Response includes all associated courses

---

## Support and Troubleshooting

### Common Issues

1. **Template Not Found for Course:**
   - Check if course is associated with any template
   - Verify template status is `active`
   - Check if category template exists as fallback

2. **Cannot Add Course to Template:**
   - Verify course doesn't already have a template
   - Check if course belongs to the ACC
   - Ensure course ID is valid

3. **Template Update Not Working:**
   - Verify all course IDs are valid
   - Check for conflicts with other templates
   - Ensure ACC owns all courses

### Getting Help

- Check validation error messages for specific issues
- Verify course ownership and template associations
- Review template selection priority logic
- Check database for template-course associations

---

## Notes for Frontend Developers

1. **Course Selection:**
   - Implement multi-select for course selection
   - Validate selections before submission
   - Show associated courses in template details

2. **Error Handling:**
   - Display clear error messages
   - Highlight conflicting courses
   - Provide actionable feedback

3. **Template Display:**
   - Show all associated courses
   - Allow easy course management
   - Display template scope clearly

4. **User Experience:**
   - Make course selection intuitive
   - Provide search/filter for courses
   - Show template associations visually

