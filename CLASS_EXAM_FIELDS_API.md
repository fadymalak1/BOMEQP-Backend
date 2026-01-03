# Class Exam Date and Exam Score API - Documentation

## Overview

The Class API endpoints now include exam date and exam score fields. These fields allow tracking when exams are scheduled and recording exam scores for training classes.

## What Changed?

### Before
- Classes only had start_date and end_date
- No exam tracking capability
- No exam score recording

### After
- Classes include `exam_date` field (optional)
- Classes include `exam_score` field (optional)
- Exam date can be set during class creation or update
- Exam score can be recorded and updated
- All class endpoints return exam_date and exam_score

## API Endpoints

### 1. Create Training Class

**Endpoint**: `POST /api/training-center/classes`

**Description**: Create a new training class. Exam date and exam score can be optionally included.

**Authentication**: Required (Training Center Admin)

**Request Body**:
```json
{
    "course_id": 1,
    "class_id": 1,
    "instructor_id": 1,
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "exam_date": "2024-01-25",
    "exam_score": null,
    "schedule_json": {
        "monday": "09:00-17:00",
        "tuesday": "09:00-17:00"
    },
    "location": "physical",
    "location_details": "Room 101"
}
```

**Request Fields**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `course_id` | integer | Yes | Course ID |
| `class_id` | integer | Yes | Class catalog ID |
| `instructor_id` | integer | Yes | Instructor ID |
| `start_date` | date | Yes | Class start date (format: YYYY-MM-DD) |
| `end_date` | date | Yes | Class end date (must be after start_date) |
| `exam_date` | date | No | ⭐ NEW - Exam date (must be after or equal to start_date) |
| `exam_score` | decimal | No | ⭐ NEW - Exam score (0-100) |
| `schedule_json` | object | No | Class schedule |
| `location` | string | Yes | Location type: `physical` or `online` |
| `location_details` | string | No | Location details/address |

**Validation Rules**:
- `exam_date`: Must be a valid date, must be after or equal to `start_date`
- `exam_score`: Must be numeric, between 0 and 100

**Response (201 Created)**:
```json
{
    "class": {
        "id": 1,
        "training_center_id": 3,
        "course_id": 5,
        "class_id": 10,
        "instructor_id": 2,
        "start_date": "2024-01-15",
        "end_date": "2024-01-20",
        "exam_date": "2024-01-25",
        "exam_score": null,
        "schedule_json": {
            "monday": "09:00-17:00",
            "tuesday": "09:00-17:00"
        },
        "enrolled_count": 0,
        "status": "scheduled",
        "location": "physical",
        "location_details": "Room 101",
        "created_at": "2024-01-10T10:00:00.000000Z",
        "updated_at": "2024-01-10T10:00:00.000000Z",
        "course": {
            "id": 5,
            "name": "Fire Safety Training"
        },
        "instructor": {
            "id": 2,
            "first_name": "John",
            "last_name": "Smith"
        }
    }
}
```

### 2. Update Training Class

**Endpoint**: `PUT /api/training-center/classes/{id}`

**Description**: Update training class information. Exam date and exam score can be updated independently.

**Authentication**: Required (Training Center Admin)

**Path Parameters**:
- `id` (required) - Class ID

**Request Body** (All fields optional):
```json
{
    "exam_date": "2024-01-25",
    "exam_score": 85.50,
    "status": "completed"
}
```

**Request Fields**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `course_id` | integer | No | Course ID |
| `class_id` | integer | No | Class catalog ID |
| `instructor_id` | integer | No | Instructor ID |
| `start_date` | date | No | Class start date |
| `end_date` | date | No | Class end date |
| `exam_date` | date | No | ⭐ NEW - Exam date |
| `exam_score` | decimal | No | ⭐ NEW - Exam score (0-100) |
| `schedule_json` | object | No | Class schedule |
| `location` | string | No | Location type |
| `location_details` | string | No | Location details |
| `status` | string | No | Class status |

**Validation Rules**:
- `exam_date`: Must be a valid date, must be after or equal to `start_date` (if start_date is provided)
- `exam_score`: Must be numeric, between 0 and 100

**Response (200 OK)**:
```json
{
    "message": "Class updated successfully",
    "class": {
        "id": 1,
        "training_center_id": 3,
        "course_id": 5,
        "class_id": 10,
        "instructor_id": 2,
        "start_date": "2024-01-15",
        "end_date": "2024-01-20",
        "exam_date": "2024-01-25",
        "exam_score": 85.50,
        "status": "completed",
        "location": "physical",
        "location_details": "Room 101"
    }
}
```

### 3. Get Training Classes List

**Endpoint**: `GET /api/training-center/classes`

**Description**: Get all training classes for the authenticated training center.

**Authentication**: Required (Training Center Admin)

**Response (200 OK)**:
```json
{
    "classes": [
        {
            "id": 1,
            "course_id": 5,
            "class_id": 10,
            "instructor_id": 2,
            "start_date": "2024-01-15",
            "end_date": "2024-01-20",
            "exam_date": "2024-01-25",
            "exam_score": 85.50,
            "status": "completed",
            "location": "physical",
            "trainees": [...],
            "course": {...},
            "instructor": {...}
        }
    ]
}
```

### 4. Get Class Details

**Endpoint**: `GET /api/training-center/classes/{id}`

**Description**: Get detailed information about a specific training class.

**Authentication**: Required (Training Center Admin)

**Path Parameters**:
- `id` (required) - Class ID

**Response (200 OK)**:
```json
{
    "class": {
        "id": 1,
        "training_center_id": 3,
        "course_id": 5,
        "class_id": 10,
        "instructor_id": 2,
        "start_date": "2024-01-15",
        "end_date": "2024-01-20",
        "exam_date": "2024-01-25",
        "exam_score": 85.50,
        "schedule_json": {...},
        "enrolled_count": 15,
        "status": "completed",
        "location": "physical",
        "location_details": "Room 101",
        "course": {...},
        "instructor": {...},
        "training_center": {...},
        "completion": {...}
    }
}
```

### 5. ACC Classes List

**Endpoint**: `GET /api/acc/classes`

**Description**: Get all classes from training centers authorized by the ACC.

**Authentication**: Required (ACC Admin)

**Query Parameters**:
- `status` (optional) - Filter by status
- `training_center_id` (optional) - Filter by training center
- `course_id` (optional) - Filter by course
- `date_from` (optional) - Filter by start date from
- `date_to` (optional) - Filter by start date to
- `per_page` (optional) - Items per page (default: 15)
- `page` (optional) - Page number (default: 1)

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 1,
            "course_id": 5,
            "training_center_id": 3,
            "instructor_id": 2,
            "start_date": "2024-01-15",
            "end_date": "2024-01-20",
            "exam_date": "2024-01-25",
            "exam_score": 85.50,
            "status": "completed",
            "enrolled_count": 15,
            "location": "physical",
            "course": {...},
            "training_center": {...},
            "instructor": {...},
            "trainees": [...]
        }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 50
}
```

### 6. ACC Class Details

**Endpoint**: `GET /api/acc/classes/{id}`

**Description**: Get detailed information about a specific class.

**Authentication**: Required (ACC Admin)

**Path Parameters**:
- `id` (required) - Class ID

**Response (200 OK)**:
```json
{
    "id": 1,
    "course_id": 5,
    "training_center_id": 3,
    "instructor_id": 2,
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "exam_date": "2024-01-25",
    "exam_score": 85.50,
    "status": "completed",
    "enrolled_count": 15,
    "location": "physical",
    "course": {...},
    "training_center": {...},
    "instructor": {...},
    "trainees": [...]
}
```

### 7. Instructor Classes List

**Endpoint**: `GET /api/instructor/classes`

**Description**: Get all classes assigned to the authenticated instructor.

**Authentication**: Required (Instructor)

**Query Parameters**:
- `status` (optional) - Filter by status

**Response (200 OK)**:
```json
{
    "classes": [
        {
            "id": 1,
            "course_id": 5,
            "training_center_id": 3,
            "start_date": "2024-01-15",
            "end_date": "2024-01-20",
            "exam_date": "2024-01-25",
            "exam_score": 85.50,
            "status": "completed",
            "course": {...},
            "training_center": {...}
        }
    ]
}
```

### 8. Instructor Class Details

**Endpoint**: `GET /api/instructor/classes/{id}`

**Description**: Get detailed information about a specific class assigned to the instructor.

**Authentication**: Required (Instructor)

**Path Parameters**:
- `id` (required) - Class ID

**Response (200 OK)**:
```json
{
    "class": {
        "id": 1,
        "course_id": 5,
        "training_center_id": 3,
        "start_date": "2024-01-15",
        "end_date": "2024-01-20",
        "exam_date": "2024-01-25",
        "exam_score": 85.50,
        "status": "completed",
        "course": {...},
        "training_center": {...},
        "classModel": {...},
        "completion": {...}
    }
}
```

## Response Fields

### Class Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Class ID |
| `course_id` | integer | Course ID |
| `training_center_id` | integer | Training center ID |
| `class_id` | integer | Class catalog ID |
| `instructor_id` | integer | Instructor ID |
| `start_date` | date | Class start date |
| `end_date` | date | Class end date |
| `exam_date` | date | ⭐ NEW - Exam date (nullable) |
| `exam_score` | decimal | ⭐ NEW - Exam score 0-100 (nullable) |
| `schedule_json` | object | Class schedule |
| `enrolled_count` | integer | Number of enrolled trainees |
| `status` | string | Class status: `scheduled`, `in_progress`, `completed`, `cancelled` |
| `location` | string | Location type: `physical`, `online` |
| `location_details` | string | Location details/address |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

## Field Details

### exam_date

- **Type**: Date (nullable)
- **Format**: YYYY-MM-DD
- **Required**: No
- **Validation**: 
  - Must be a valid date
  - Must be after or equal to `start_date` (if start_date is provided)
- **Description**: The date when the exam for this class is scheduled or was conducted
- **Example**: `"2024-01-25"`

### exam_score

- **Type**: Decimal (nullable)
- **Format**: Decimal with 2 decimal places (e.g., 85.50)
- **Required**: No
- **Validation**: 
  - Must be numeric
  - Minimum: 0
  - Maximum: 100
- **Description**: The exam score for this class (typically average score or class performance score)
- **Example**: `85.50`

## Use Cases

### 1. Schedule Exam During Class Creation

When creating a class, you can optionally set the exam date:

```json
{
    "course_id": 1,
    "class_id": 1,
    "instructor_id": 1,
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "exam_date": "2024-01-25",
    "location": "physical"
}
```

### 2. Update Exam Date Later

Update the exam date after class creation:

```json
PUT /api/training-center/classes/1
{
    "exam_date": "2024-01-26"
}
```

### 3. Record Exam Score

After the exam is conducted, record the score:

```json
PUT /api/training-center/classes/1
{
    "exam_score": 87.50
}
```

### 4. Update Both Exam Date and Score

Update both fields together:

```json
PUT /api/training-center/classes/1
{
    "exam_date": "2024-01-25",
    "exam_score": 90.00
}
```

### 5. Clear Exam Score

Set exam_score to null to clear it:

```json
PUT /api/training-center/classes/1
{
    "exam_score": null
}
```

## Validation Rules Summary

### exam_date
- Optional field
- Must be a valid date format (YYYY-MM-DD)
- Must be after or equal to `start_date` when both are provided
- Can be set independently of other fields

### exam_score
- Optional field
- Must be numeric
- Range: 0 to 100
- Supports decimal values (e.g., 85.50)
- Can be set to null to clear the score

## Error Responses

### 422 Validation Error - Invalid Exam Date

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "exam_date": [
            "The exam date must be a date.",
            "The exam date must be a date after or equal to start date."
        ]
    }
}
```

### 422 Validation Error - Invalid Exam Score

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "exam_score": [
            "The exam score must be a number.",
            "The exam score must be at least 0.",
            "The exam score must not be greater than 100."
        ]
    }
}
```

## Notes

1. **Optional Fields**: Both `exam_date` and `exam_score` are optional and can be omitted during class creation or update.

2. **Null Values**: Both fields can be set to `null` to clear existing values.

3. **Date Validation**: `exam_date` must be after or equal to `start_date`. If `start_date` is not provided in an update request, the validation uses the existing `start_date` from the database.

4. **Score Range**: `exam_score` is validated to be between 0 and 100, inclusive.

5. **Decimal Precision**: `exam_score` supports 2 decimal places (e.g., 85.50, 92.75).

6. **Automatic Inclusion**: All class endpoints automatically include `exam_date` and `exam_score` in their responses.

7. **Backward Compatibility**: Existing classes will have `null` values for these fields until they are updated.

8. **Filtering**: You can filter classes by `start_date` and `end_date`, but filtering by `exam_date` is not currently supported (can be added if needed).

## Summary

✅ **Exam Date Field** - Track when exams are scheduled  
✅ **Exam Score Field** - Record exam scores (0-100)  
✅ **Optional Fields** - Both fields are optional  
✅ **Validation** - Proper date and score validation  
✅ **All Endpoints** - Included in all class API responses  
✅ **Update Support** - Can be updated independently or together  

The Class API now provides complete exam tracking functionality for training classes.

