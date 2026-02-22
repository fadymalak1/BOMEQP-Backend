# API Changes: Instructor & Training Centers

This document describes the API changes for:
- Training centers adding instructors (existing by email or new)
- Instructor data including **training centers** and **ACCs** that worked with each instructor (ACC, Admin, and Instructor dashboard)

---

## 1. Training Center – Add Instructor

### `POST /api/training-center/instructors`

**Behavior:**

| Scenario | Request | Result |
|---------|---------|--------|
| **Instructor already exists** (email in system) | Body must include `email` (other fields optional) | Instructor is **linked** to this training center via pivot. No new user, no credentials email. |
| **Instructor does not exist** | Full body as before (see below) | New instructor and user are created; credentials email is sent. |

**Add existing instructor (by email only):**

- **Validation:** `email` required and must be valid.
- **Response (201):**  
  `message`: e.g. *"Instructor added to your training center successfully."* or *"Instructor is already associated with your training center."*  
  `instructor`: instructor resource (with `training_center` and relations as applicable).

**Create new instructor (unchanged):**

- **Validation:**  
  `first_name`, `last_name`, `email` (unique in `instructors` and `users`), `date_of_birth`, `phone`, `languages`, `is_assessor`, `cv`, `passport`.
- **Response (201):**  
  `message`, `instructor` (new instructor).

---

## 2. Training Center – Instructor List & Detail

### `GET /api/training-center/instructors`

- **Scope:** Instructors whose **primary** training center is this TC **or** who are **linked** to this TC via the pivot (added by email).
- **Response:** Same structure; list now includes both primary and linked instructors.

### `GET /api/training-center/instructors/{id}`

- **Scope:** Instructor must belong to this TC (primary or linked). Otherwise 404.
- **Response:** `instructor` now includes `linked_training_centers` (id, name, email) when loaded.

### `PUT /api/training-center/instructors/{id}`  
### `POST /api/training-center/instructors/{id}/request-authorization`

- **Scope:** Same as show; instructor must be primary or linked to this TC.

### `DELETE /api/training-center/instructors/{id}`

- **Behavior:**  
  - If this TC is the instructor’s **primary** TC → instructor record is **deleted**.  
  - If instructor is only **linked** via pivot → link is **detached**; instructor record is kept.  
- **Response:**  
  - Deleted: *"Instructor deleted successfully."*  
  - Detached: *"Instructor removed from your training center."*

---

## 3. ACC – Instructors

### `GET /api/acc/instructors`

**New field on each instructor:**

| Field | Type | Description |
|-------|------|-------------|
| `training_centers` | `array` | Training centers that worked with this instructor (primary TC, linked TCs, authorizations, classes). |

**Each item in `training_centers`:**

- `id` (integer)  
- `name` (string)  
- `email` (string)

Existing fields (`training_center`, `authorizations`, `authorized_courses`, etc.) are unchanged.

---

## 4. Admin – Instructors

### `GET /api/admin/instructors`

**New fields on each instructor:**

| Field | Type | Description |
|-------|------|-------------|
| `training_centers` | `array` | Training centers that worked with this instructor (primary, linked, authorizations, classes). |
| `accs` | `array` | ACCs that worked with this instructor (from authorizations and classes). |

**Each item in `training_centers` / `accs`:**

- `id` (integer)  
- `name` (string)  
- `email` (string)

### `GET /api/admin/instructors/{id}`

- **Response:** Single `instructor` object with the same **`training_centers`** and **`accs`** arrays as above.  
- Loaded relations may include `linked_training_centers` (id, name, email).

---

## 5. Instructor Dashboard

### `GET /api/instructor/dashboard`

**Updated field:**

| Field | Change |
|-------|--------|
| `training_centers` | Now also includes training centers the instructor is **linked** to via the pivot (i.e. TCs that “added” them by email), in addition to primary TC and TCs from classes. |

Structure of each training center entry (id, name, email, phone, country, city, status, classes_count, etc.) is unchanged.

---

## 6. Database & Models

- **New table:** `instructor_training_center`  
  - `instructor_id`, `training_center_id`, unique on `(instructor_id, training_center_id)`.
- **Instructor model:**  
  - `linkedTrainingCenters()` – many-to-many with `training_centers` via `instructor_training_center`.  
  - Helpers (used internally for API responses): `getTrainingCentersWorkedWith()`, `getAccsWorkedWith()`.
- **TrainingCenter model:**  
  - `linkedInstructors()` – inverse of `linkedTrainingCenters()`.

**Migration:**

```bash
php artisan migrate
```

---

## Summary Table

| Endpoint | Change |
|----------|--------|
| `POST /api/training-center/instructors` | If email exists → link instructor to TC; else create new instructor. |
| `GET /api/training-center/instructors` | Includes instructors linked via pivot. |
| `GET /api/training-center/instructors/{id}` | Allowed if primary or linked; can include `linked_training_centers`. |
| `PUT/DELETE /api/training-center/instructors/{id}` | Same scope; DELETE detaches if only linked. |
| `GET /api/acc/instructors` | Each instructor has `training_centers[]`. |
| `GET /api/admin/instructors` | Each instructor has `training_centers[]` and `accs[]`. |
| `GET /api/admin/instructors/{id}` | Instructor object has `training_centers[]` and `accs[]`. |
| `GET /api/instructor/dashboard` | `training_centers` includes linked TCs. |
