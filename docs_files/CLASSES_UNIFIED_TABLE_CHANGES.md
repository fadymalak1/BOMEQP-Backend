# Classes Unified Table - API Changes Documentation

## Overview
The classes system has been unified into a single table (`training_classes`). Training centers now create classes directly without needing to reference a separate `classes` table. The admin can only view all classes created by training centers but cannot create, update, or delete them.

---

## 1. Training Center - Create Class Endpoint

### Endpoint
`POST /v1/api/training-center/classes`

### Changes

#### **Removed Fields:**
- `class_id` (integer, required) - **REMOVED** - No longer needed

#### **Added Fields:**
- `name` (string, required) - **NEW REQUIRED FIELD** - The name of the class (e.g., "Class A - January 2024")

### Updated Request Body

**Before:**
```json
{
  "course_id": 1,
  "class_id": 5,        // ❌ REMOVED
  "instructor_id": 1,
  "start_date": "2024-01-15",
  "end_date": "2024-01-20",
  "location": "physical"
}
```

**After:**
```json
{
  "course_id": 1,
  "name": "Class A - January 2024",  // ✅ NEW REQUIRED
  "instructor_id": 1,
  "start_date": "2024-01-15",
  "end_date": "2024-01-20",
  "location": "physical"
}
```

### Required Fields
- `course_id` (integer)
- `name` (string) - **NEW**
- `instructor_id` (integer)
- `start_date` (date)
- `end_date` (date)
- `location` (enum: "physical" | "online")

### Optional Fields
- `exam_date` (date)
- `exam_score` (number)
- `schedule_json` (array)
- `location_details` (string)
- `trainee_ids` (array of integers)

### Response Changes

The response now includes:
- `name` - The class name
- `created_by` - User information who created the class
- `class_id` - May be null (kept for backward compatibility)

**Example Response:**
```json
{
  "message": "Class created successfully",
  "class": {
    "id": 1,
    "name": "Class A - January 2024",
    "course_id": 1,
    "training_center_id": 1,
    "instructor_id": 1,
    "created_by": {
      "id": 10,
      "name": "Training Center User"
    },
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "status": "scheduled",
    "location": "physical"
  }
}
```

---

## 2. Training Center - List Classes Endpoint

### Endpoint
`GET /v1/api/training-center/classes`

### Changes

#### **Response Changes:**
- Removed `classModel` relationship from response
- Added `createdBy` relationship (user who created the class)
- Added `name` field directly in the class object

**Before:**
```json
{
  "classes": [
    {
      "id": 1,
      "classModel": {          // ❌ REMOVED
        "id": 5,
        "name": "Class A"
      },
      "course": {...}
    }
  ]
}
```

**After:**
```json
{
  "classes": [
    {
      "id": 1,
      "name": "Class A - January 2024",  // ✅ NEW - Direct field
      "created_by": {                    // ✅ NEW
        "id": 10,
        "name": "Training Center User"
      },
      "course": {...}
    }
  ]
}
```

---

## 3. Training Center - Get Class Details Endpoint

### Endpoint
`GET /v1/api/training-center/classes/{id}`

### Changes

Same as List Classes - `classModel` removed, `name` and `createdBy` added directly.

---

## 4. Training Center - Update Class Endpoint

### Endpoint
`PUT /v1/api/training-center/classes/{id}`

### Changes

#### **Removed Fields:**
- `class_id` (integer, optional) - **REMOVED**

#### **Added Fields:**
- `name` (string, optional) - **NEW** - Can be updated

### Updated Request Body

**Before:**
```json
{
  "class_id": 5,        // ❌ REMOVED
  "start_date": "2024-01-16"
}
```

**After:**
```json
{
  "name": "Updated Class Name",  // ✅ NEW - Can update name
  "start_date": "2024-01-16"
}
```

---

## 5. Admin - List All Classes Endpoint

### Endpoint
`GET /v1/api/admin/classes`

### Changes

#### **Major Changes:**
- **Now returns `TrainingClass` objects** instead of `ClassModel` objects
- Admin can view all classes created by training centers
- Added filtering by `training_center_id`

#### **New Query Parameters:**
- `course_id` (integer, optional) - Filter by course
- `training_center_id` (integer, optional) - **NEW** - Filter by training center

#### **Response Structure:**
The response now includes full training class information with relationships:
- `course` - Course details
- `trainingCenter` - Training center details
- `instructor` - Instructor details
- `trainees` - Enrolled trainees
- `createdBy` - User who created the class

**Example Response:**
```json
{
  "classes": [
    {
      "id": 1,
      "name": "Class A - January 2024",
      "course": {
        "id": 1,
        "name": "Course Name"
      },
      "training_center": {
        "id": 1,
        "name": "Training Center Name"
      },
      "instructor": {
        "id": 1,
        "name": "Instructor Name"
      },
      "start_date": "2024-01-15",
      "end_date": "2024-01-20",
      "status": "scheduled",
      "created_by": {
        "id": 10,
        "name": "Training Center User"
      }
    }
  ]
}
```

---

## 6. Admin - Get Class Details Endpoint

### Endpoint
`GET /v1/api/admin/classes/{id}`

### Changes

- Returns `TrainingClass` object instead of `ClassModel`
- Includes all relationships: `course`, `trainingCenter`, `instructor`, `trainees`, `createdBy`, `completion`

---

## 7. Admin - Create/Update/Delete Class Endpoints

### Endpoints Removed
- `POST /v1/api/admin/classes` - **REMOVED** ❌
- `PUT /v1/api/admin/classes/{id}` - **REMOVED** ❌
- `DELETE /v1/api/admin/classes/{id}` - **REMOVED** ❌

### Important Note
**Admin can no longer create, update, or delete classes.** Only training centers can create classes. Admin can only view all classes in the system.

---

## 8. Certificate - Create Certificate Endpoint

### Endpoint
`POST /v1/api/training-center/certificates`

### Changes

#### **Removed Fields:**
- `class_id` (integer, optional) - **REMOVED**

#### **Added Fields:**
- `training_class_id` (integer, optional) - **NEW** - Reference to training class

### Updated Request Body

**Before:**
```json
{
  "acc_id": 1,
  "course_id": 1,
  "class_id": 5,        // ❌ REMOVED
  "trainee_name": "John Doe",
  "issue_date": "2024-01-15"
}
```

**After:**
```json
{
  "acc_id": 1,
  "course_id": 1,
  "training_class_id": 1,  // ✅ NEW - Use training class ID
  "trainee_name": "John Doe",
  "issue_date": "2024-01-15"
}
```

### Important Notes
- If you want to link a certificate to a class, use `training_class_id` instead of `class_id`
- The `training_class_id` should reference a class created by the training center
- The duplicate check now uses `training_class_id` instead of `class_id`

---

## 9. Instructor - Get Class Details Endpoint

### Endpoint
`GET /v1/api/instructor/classes/{id}`

### Changes

- Removed `classModel` relationship from response
- Added `createdBy` relationship
- Added `name` field directly in the class object

---

## 10. Trainee - Get Trainee Details Endpoint

### Endpoint
`GET /v1/api/training-center/trainees/{id}`

### Changes

- In the `trainingClasses` relationship, removed `classModel`
- Added `name` and `createdBy` directly in each training class object

---

## Migration Notes

### Database Changes
The following database migrations need to be run:
1. `2026_01_21_000001_add_name_and_created_by_to_training_classes_table.php`
   - Adds `name` and `created_by` fields to `training_classes`
   - Makes `class_id` nullable

2. `2026_01_21_000002_add_training_class_id_to_certificates_table.php`
   - Adds `training_class_id` field to `certificates` table

### Backward Compatibility
- The `class_id` field in `training_classes` is kept as nullable for backward compatibility
- The `class_id` field in `certificates` is kept for backward compatibility
- Old data will continue to work, but new classes should use `name` and `training_class_id`

---

## Summary of Changes

### ✅ What Changed:
1. Training centers now create classes directly with a `name` field (no `class_id` needed)
2. Admin can only view classes (no create/update/delete)
3. Certificate creation uses `training_class_id` instead of `class_id`
4. All class responses now include `name` and `createdBy` directly
5. Removed `classModel` relationship from all responses

### ❌ What Was Removed:
1. `class_id` requirement in class creation
2. `class_id` in certificate creation (replaced with `training_class_id`)
3. Admin create/update/delete class endpoints
4. `classModel` relationship in API responses

### ✅ What Was Added:
1. `name` field (required) in class creation
2. `training_class_id` field in certificate creation
3. `createdBy` relationship in class responses
4. `training_center_id` filter in admin list classes endpoint

---

## Frontend Action Items

### 1. Update Class Creation Form
- Remove `class_id` field/selector
- Add `name` text input field (required)
- Update validation rules

### 2. Update Class List/Display Components
- Remove references to `classModel.name`
- Use `class.name` directly
- Display `createdBy` information if needed

### 3. Update Certificate Creation Form
- Replace `class_id` with `training_class_id`
- Update the class selector to use training classes instead of classes table

### 4. Update Admin Dashboard
- Remove create/edit/delete class buttons
- Update class list to show training class information
- Add filter by `training_center_id` if needed

### 5. Update API Calls
- Update all POST/PUT requests to use new field names
- Update response parsing to use new structure
- Remove any references to `classModel` relationship

### 6. Testing Checklist
- [ ] Test class creation without `class_id`
- [ ] Test class creation with `name` field
- [ ] Test admin viewing all classes
- [ ] Test certificate creation with `training_class_id`
- [ ] Verify all class lists display correctly
- [ ] Verify class details pages work correctly
- [ ] Test filtering by `training_center_id` in admin

---

## Questions or Issues?

If you encounter any issues or have questions about these changes, please contact the backend development team.

**Last Updated:** January 21, 2026

