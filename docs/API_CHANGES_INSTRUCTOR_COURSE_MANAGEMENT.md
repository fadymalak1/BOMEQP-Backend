# API Changes: Instructor Course Management (ACC & Group Admin)

This document describes the two new endpoints that allow **ACC admins** and **Group Admins** to grant or revoke an instructor's access to specific courses.

---

## Overview

After an instructor authorization request is approved by the ACC, the ACC (or Group Admin) can at any time:

- **Add** new courses to an instructor's authorization (grant access to teach)
- **Remove** existing courses from an instructor's authorization (revoke access)

Changes are applied directly to the `instructor_course_authorization` table:
- Adding a course creates or updates a row with `status = 'active'`
- Removing a course sets the existing active row to `status = 'revoked'`

> **Prerequisite:** An approved `InstructorAccAuthorization` must exist between the instructor and the ACC before course assignments can be managed.

---

## 1. ACC — Update Instructor Courses

### Endpoint

```
PUT /api/acc/instructors/{instructorId}/courses
```

### Authentication & Authorization

- **Middleware:** `sanctum`, `role:acc_admin`, `acc.active`
- The ACC is resolved automatically from the authenticated user's email

### Path Parameter

| Parameter | Type | Required | Description |
|---|---|---|---|
| `instructorId` | integer | Yes | ID of the instructor to manage |

### Request Body

```json
{
  "add_course_ids": [1, 2, 3],
  "remove_course_ids": [4, 5]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `add_course_ids` | integer[] | Conditional | Course IDs to grant the instructor access to teach. Must belong to this ACC. |
| `remove_course_ids` | integer[] | Conditional | Course IDs to revoke from the instructor. Must belong to this ACC. |

> At least one of `add_course_ids` or `remove_course_ids` must be provided.

### Validation Rules

- All course IDs in both arrays must exist in the `courses` table
- All course IDs must belong to the authenticated ACC (`courses.acc_id`)
- The instructor must have an `approved` `InstructorAccAuthorization` under this ACC

### Response — 200 OK

```json
{
  "message": "Instructor courses updated successfully",
  "added": 2,
  "removed": 1,
  "authorized_courses": [
    {
      "course_id": 1,
      "name": "Fire Safety",
      "name_ar": "السلامة من الحرائق",
      "code": "FS-001",
      "authorized_at": "2026-02-23T22:00:00.000000Z"
    }
  ]
}
```

`authorized_courses` reflects the **full current active list** for this instructor under this ACC after the update.

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | Instructor has no approved authorization under this ACC |
| 404 | ACC or instructor not found |
| 422 | No course IDs provided, or one or more IDs are invalid / belong to another ACC |

---

## 2. Group Admin — Update Instructor Courses

### Endpoint

```
PUT /api/admin/instructors/{instructorId}/courses
```

### Authentication & Authorization

- **Middleware:** `sanctum`, `role:group_admin`
- The admin specifies the ACC in the request body (`acc_id`) because admins operate across all ACCs

### Path Parameter

| Parameter | Type | Required | Description |
|---|---|---|---|
| `instructorId` | integer | Yes | ID of the instructor to manage |

### Request Body

```json
{
  "acc_id": 1,
  "add_course_ids": [1, 2],
  "remove_course_ids": [3]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `acc_id` | integer | **Yes** | The ACC under which to manage course authorizations |
| `add_course_ids` | integer[] | Conditional | Course IDs to grant access to. Must belong to the specified ACC. |
| `remove_course_ids` | integer[] | Conditional | Course IDs to revoke. Must belong to the specified ACC. |

> At least one of `add_course_ids` or `remove_course_ids` must be provided.

### Validation Rules

- `acc_id` must exist in the `accs` table
- All course IDs must exist in the `courses` table
- All course IDs must belong to the specified ACC (`courses.acc_id`)
- An approved `InstructorAccAuthorization` must exist between the instructor and the specified ACC

### Response — 200 OK

```json
{
  "message": "Instructor courses updated successfully",
  "added": 2,
  "removed": 1,
  "authorized_courses": [
    {
      "course_id": 1,
      "name": "Fire Safety",
      "name_ar": "السلامة من الحرائق",
      "code": "FS-001",
      "authorized_at": "2026-02-23T22:00:00.000000Z"
    }
  ]
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | No approved authorization between instructor and the specified ACC |
| 404 | Instructor or ACC not found |
| 422 | No course IDs provided, missing `acc_id`, or one or more IDs invalid / belong to another ACC |

---

## Database Impact

Both endpoints write to the `instructor_course_authorization` table:

| Column | Value on add | Value on remove |
|---|---|---|
| `instructor_id` | provided `instructorId` | provided `instructorId` |
| `course_id` | each ID from `add_course_ids` | each ID from `remove_course_ids` |
| `acc_id` | ACC's ID | ACC's ID |
| `status` | `'active'` | `'revoked'` |
| `authorized_at` | `now()` | unchanged |
| `authorized_by` | authenticated user ID | unchanged |

Adding uses `updateOrCreate` — if a previously revoked row exists for the same `(instructor_id, course_id, acc_id)` triplet, it is reactivated rather than duplicated.

---

## Files Changed

| File | Change |
|---|---|
| `app/Http/Controllers/API/ACC/InstructorController.php` | Added `updateCourses()` method; added `use` imports for `Course`, `InstructorCourseAuthorization` |
| `app/Http/Controllers/API/Admin/InstructorController.php` | Added `updateCourses()` method; added `use` imports for `ACC`, `Course`, `InstructorCourseAuthorization` |
| `routes/api.php` | Added `PUT /acc/instructors/{instructorId}/courses` and `PUT /admin/instructors/{instructorId}/courses` |
