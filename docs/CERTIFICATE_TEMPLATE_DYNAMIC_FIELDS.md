# Certificate template dynamic fields

This document describes the shared **training provider** and related placeholders for **course** and **instructor** certificate templates.

## Overview

ACC users design certificate HTML using placeholders such as `{{training_provider_name}}`. At PDF generation time, the backend replaces each `{{key}}` with data from the training center, optional training class, instructor, and related records.

### Shared placeholders (course and instructor templates)

These fields are merged for both course-completion and instructor-authorization PDFs (via `appendCourseCertificateDynamicFields` where a training center exists):

| Placeholder | Description |
|-------------|-------------|
| `training_provider_name` | Training provider name (training center `name`). |
| `training_provider_phone` | Training center phone. |
| `training_provider_id_number` | Training provider government / company registry ID (`TrainingCenter.company_gov_registry_number`). |
| `instructor_name` | **Course certificates:** instructor selected when issuing (if any). **Instructor certificates:** authorized instructor full name (set before provider fields are merged). |
| `delivery_method` | **Course certificates:** from linked training class `location` and `location_details` (joined with ` — `). **Instructor certificates:** usually empty (no class in this flow). |

`instructor_name` is listed in the shared placeholder API for designers; values come from issue-time data (`instructor_name` in the generation payload), not from `appendCourseCertificateDynamicFields` (that method only adds provider + delivery fields).

## Backend implementation

### Placeholder definitions

- **File:** `app/Support/CertificateCoursePlaceholders.php`
- **Methods:**
  - `sharedTrainingProviderPlaceholders()` — shared fields above (used in both template types).
  - `definitions()` — full list for **course** templates (shared + trainee, course, logos, QR, etc.).
  - `instructorDefinitions()` — full list for **instructor** templates (shared + `instructor_first_name`, …, ACC, expiry, logos, QR; `instructor_name` appears once via shared).

### PDF generation

- **File:** `app/Services/CertificateGenerationService.php`
- **`appendCourseCertificateDynamicFields(TrainingCenter $trainingCenter, ?TrainingClass $trainingClass, array $data)`**  
  Merges: `training_provider_name`, `training_provider_phone`, `training_provider_id_number`, `delivery_method`.  
  Does **not** set `instructor_name` (must already be present for instructor certs; course issuance sets it when an instructor is selected).
- **`normalizeTemplateData()`** maps:
  - `training_provider_name` ← `training_center_name` if needed  
  - `training_provider_phone` ← `training_center_phone` if present  
  - `training_provider_id_number` ← `company_gov_registry_number` if present  

**Course certificates (training center issuance)**  
- **File:** `app/Http/Controllers/API/TrainingCenter/CertificateController.php`  
- Base payload includes `instructor_name` when an instructor is linked; then `appendCourseCertificateDynamicFields($trainingCenter, $trainingClass, $certificateData)` runs.

**Instructor certificates**  
- **`generateInstructorCertificate()`** builds `instructor_name` (and related fields), then calls `appendCourseCertificateDynamicFields($trainingCenter, null, $data)` when a training center exists.  
- If there is no training center, provider fields are set to empty strings (including `training_provider_id_number`).

## ACC API

### List placeholders (for designers / front-end)

```http
GET /api/acc/certificate-templates/placeholders?template_type=course
GET /api/acc/certificate-templates/placeholders?template_type=instructor
```

**Response (shape):**

```json
{
  "template_type": "course",
  "placeholders": [
    { "key": "training_provider_name", "label": "...", "description": "..." }
  ]
}
```

- `template_type` defaults to `course` if omitted.
- Only `course` and `instructor` are supported.

### Template detail (includes placeholders)

```http
GET /api/acc/certificate-templates/{id}
```

When the template belongs to the authenticated ACC:

- If `template_type` is `course`, the response includes `available_placeholders` (same as `definitions()`).
- If `template_type` is `instructor`, the response includes `available_placeholders` (same as `instructorDefinitions()`).

**Note:** `GET /acc/certificate-templates/{id}` is scoped to the current ACC’s templates.

## Using placeholders in HTML

Use double curly braces in `template_html` (and card HTML if applicable):

```html
<p>Provider: {{training_provider_name}} — {{training_provider_phone}}</p>
<p>Registry ID: {{training_provider_id_number}}</p>
<p>Instructor: {{instructor_name}}</p>
<p>Delivery: {{delivery_method}}</p>
```

Images (logos, QR) must use the existing patterns, e.g. `<img src="{{training_center_logo}}">`.

## Related files

| Area | Path |
|------|------|
| Placeholder lists | `app/Support/CertificateCoursePlaceholders.php` |
| Generation & merging | `app/Services/CertificateGenerationService.php` |
| Training center issue | `app/Http/Controllers/API/TrainingCenter/CertificateController.php` |
| ACC template API + placeholders route | `app/Http/Controllers/API/ACC/CertificateTemplateController.php` |
| Routes | `routes/api.php` (`certificate-templates/placeholders` before `certificate-templates` resource) |

## Future improvements (optional)

- Add a dedicated `delivery_method` (or mode) column on `training_classes` if you need explicit values (e.g. Online / Classroom) instead of deriving from location text.
- Rename `CertificateCoursePlaceholders` to a neutral name (e.g. `CertificateTemplatePlaceholders`) if you want the class name to reflect instructor templates too; current name is kept for backward compatibility with existing imports.
