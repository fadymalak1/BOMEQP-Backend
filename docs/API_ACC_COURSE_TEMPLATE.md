## API: ACC Course Excel Template & Import

This document describes the **Course Template** feature for ACCs. An ACC can download an Excel/CSV template for bulk course management, fill in course data using guided dropdowns, and upload the file to create/update courses in bulk.

---

## Authentication

All endpoints require a valid Sanctum bearer token:

```http
Authorization: Bearer {token}
```

All course template endpoints are restricted to users with the `acc_admin` or `competency_admin` role (`middleware: role:acc_admin,competency_admin, acc.active`).

---

## Base URL

```text
/api/acc
```

---

## Endpoints

### 1. Download ACC Course Template

```http
GET /api/acc/courses/template/download
```

Downloads an Excel (`.xlsx`) or CSV (`.csv`) template file that you can use to create or update courses in bulk.

The template is **scoped to the authenticated ACC**:

- The **Sub Category** dropdown only contains sub categories under categories accessible to the ACC.
- Level, Status, and Currency are constrained to valid values.

#### Query Parameters

| Parameter | Type   | Required | Description                                                         |
|----------|--------|----------|---------------------------------------------------------------------|
| `format` | string | No       | `xlsx` (default, recommended) or `csv`. Dropdowns are only in XLSX. |

#### Columns & Dropdowns

The Excel template contains a single sheet with the following **headings**:

| Column             | Required | Type     | Description                                                                                         | Dropdown / Allowed Values                                                  |
|--------------------|----------|----------|-----------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------|
| `sub_category`     | Yes      | string   | Sub category name. Must exist in one of the categories accessible to the ACC.                      | **Dropdown**: accessible sub categories for this ACC                      |
| `name`             | Yes      | string   | Course name (English).                                                                             | Free text                                                                 |
| `code`             | Yes      | string   | Unique course code per ACC. Used to match existing courses when importing.                         | Free text                                                                 |
| `description`      | No       | string   | Optional course description.                                                                       | Free text                                                                 |
| `duration_hours`   | No       | integer  | Course duration in hours. If empty/invalid, defaults to `1`.                                       | Free number                                                                |
| `max_capacity`     | No       | integer  | Maximum number of trainees per class. If empty/invalid, defaults to `1`.                           | Free number                                                                |
| `assessor_required`| No       | boolean  | Whether an assessor is required.                                                                   | **Dropdown**: `Yes`, `No`                                                 |
| `level`            | Yes      | string   | Course difficulty level.                                                                           | **Dropdown**: `Beginner`, `Intermediate`, `Advanced`                      |
| `status`           | No       | string   | Course status. If empty, defaults to `Active`.                                                     | **Dropdown**: `Active`, `Inactive` (import also accepts `Archived`)       |
| `base_price`       | No       | decimal  | Optional course base price for certificates. Must be `>= 0` if provided.                           | Free number                                                                |
| `currency`         | No*      | string   | 3-letter currency code (e.g. `USD`, `EGP`). **Required if `base_price` is provided.**              | **Dropdown**: `USD`, `EUR`, `GBP`, `EGP`, `SAR`, `AED`, `QAR`, `KWD`, `BHD`, `OMR` |

> **Note:** Dropdowns are only available in the XLSX version. The CSV version uses the same column names and allowed values but without validation in the file.

#### Example Request

```http
GET /api/acc/courses/template/download?format=xlsx
Authorization: Bearer {token}
```

Response → Binary file download: `courses_template.xlsx`

---

### 2. Import Courses from Template

```http
POST /api/acc/courses/import
Content-Type: multipart/form-data
```

Uploads a filled Excel/CSV template file to **create or update courses in bulk** for the authenticated ACC.

- Existing courses are matched by **`code` + ACC**.
  - If a course with the same `code` exists for this ACC → it is **updated**.
  - Otherwise → a new course is **created**.
- Optional pricing information (`base_price`, `currency`) is stored in `certificate_pricings` for that course/ACC.

#### Request Body (`multipart/form-data`)

| Field  | Type | Required | Description                              |
|--------|------|----------|------------------------------------------|
| `file` | file | Yes      | Excel (`.xlsx`) or CSV file to import.  |

> It is recommended to always use a file downloaded from `GET /api/acc/courses/template/download` to ensure correct headings and values.

#### Row Validation Rules

For each row (starting at Excel row 2):

- **Empty `name`** → row is skipped (treated as blank).
- **`sub_category`**:
  - Must not be empty.
  - Must exist in the ACC’s accessible sub categories (use exact name from dropdown).
- **`code`**:
  - Must not be empty.
  - Used as the unique key per ACC for upserts.
- **`duration_hours`**:
  - If not a positive integer → defaults to `1`.
- **`max_capacity`**:
  - If not a positive integer → defaults to `1`.
- **`assessor_required`**:
  - Case-insensitive, accepts: `Yes`, `Y`, `True`, `1` → `true`; anything else (including `No`, empty) → `false`.
- **`level`**:
  - Allowed values (case-insensitive): `Beginner`, `Intermediate`, `Advanced`.
  - Any other value → row error.
- **`status`**:
  - If empty → `Active`.
  - Allowed values (case-insensitive): `Active`, `Inactive`, `Archived`.
  - Any other value → row error.
- **`base_price`**:
  - If present, must be `>= 0`.
  - If negative → row error.
- **`currency`**:
  - Required if `base_price` is present.
  - Must be a 3-letter code (e.g. `USD`, `EGP`).
  - If missing/invalid while `base_price` is present → row error.

If **any** validation rule fails for a row, that row is skipped and an error message is collected.

#### Pricing Behaviour

When a row passes validation:

- The course is created/updated with the provided course fields.
- If `base_price` and `currency` are both present:
  - The latest `CertificatePricing` row for this `course_id` and `acc_id` is **created or updated** with:
    - `base_price`
    - `currency`
    - `group_commission_percentage = 0`
    - `training_center_commission_percentage = 0`
    - `instructor_commission_percentage = 0`
    - `effective_from = today`
    - `effective_to = null`

#### Response `200 OK`

```json
{
  "message": "Courses imported successfully",
  "created_count": 5,
  "updated_count": 3,
  "errors": [
    "Row 4: Category 'XYZ' not found. Use exact name from dropdown.",
    "Row 7: Invalid level 'Expert'. Allowed: Beginner, Intermediate, Advanced."
  ]
}
```

- `created_count`: number of newly created courses.
- `updated_count`: number of existing courses updated.
- `errors`: array of row-level error strings. Rows with errors are skipped, other rows still process.

#### Error Responses

| Status | Message / Payload                                      |
|--------|--------------------------------------------------------|
| `400`  | `"file" field missing` (via validation failure)        |
| `401`  | `"Unauthenticated"`                                    |
| `404`  | `"ACC not found"`                                      |
| `422`  | Validation failed / invalid file format / import error |

Example `422` (import / validation failure):

```json
{
  "message": "Validation failed",
  "errors": [
    "Row 2: Category 'Fire' not found. Use exact name from dropdown.",
    "Row 3: currency (3-letter code) is required when base_price is provided."
  ]
}
```

---

## Example Workflow

### Step 1 — Download the template

```http
GET /api/acc/courses/template/download?format=xlsx
Authorization: Bearer {token}
```

Save the file `courses_template.xlsx`.

---

### Step 2 — Fill in course data

In Excel:

- For each course row:
  - Select `sub_category` from the dropdown.
  - Enter `name`, `code`, and other fields.
  - Choose `level` from `Beginner / Intermediate / Advanced`.
  - Choose `status` from `Active / Inactive`.
  - Optionally set `base_price` and `currency` (from the currency dropdown).

You can leave unused rows blank.

---

### Step 3 — Upload the filled template

```http
POST /api/acc/courses/import
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: <courses_template.xlsx>
```

Response example:

```json
{
  "message": "Courses imported successfully",
  "created_count": 5,
  "updated_count": 2,
  "errors": []
}
```

---

## Notes

- Matching is done by **`code` per ACC**:
  - Changing other fields while keeping the same `code` will update that course.
  - Using a new `code` will create a new course.
- For safety, the import is designed to **skip invalid rows** and continue processing the rest.
- Use the **XLSX** format whenever possible to benefit from the dropdown validations in Excel.

