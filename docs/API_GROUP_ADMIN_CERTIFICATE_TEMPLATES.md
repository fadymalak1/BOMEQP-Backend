# API: Group Admin Certificate Templates & Multi-ACC Instructor Achievement

This document describes the certificate template management feature for **Group Admins** and the automatic achievement certificate that is sent to an instructor when they are authorized by **at least 3 ACCs**.

---

## Overview

- The **Group Admin** can create and manage a global instructor certificate template (not tied to any specific ACC).
- When an instructor completes a paid authorization with an ACC, the system checks whether the instructor has now been approved by **3 or more distinct ACCs**.
- If the threshold is met (and the achievement certificate has not already been sent), the system automatically:
  1. Generates a PDF certificate using the active group-admin template.
  2. Emails it to the instructor.
  3. Records a `Certificate` entry in the database.
  4. Sends the instructor an in-app notification.

> **Only one active group-admin instructor template can exist at a time.**

---

## Authentication

All endpoints require a valid Sanctum bearer token:

```
Authorization: Bearer {token}
```

All endpoints are restricted to users with the `group_admin` role (`middleware: role:group_admin`).

---

## Endpoints

### 1. List Certificate Templates

```
GET /api/admin/certificate-templates
```

Returns all certificate templates created by the group admin.

**Query Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `search` | string | No | Filter by template name |
| `status` | string | No | `active` or `inactive` |
| `template_type` | string | No | Currently only `instructor` |
| `per_page` | integer | No | Items per page (default: `10`) |
| `page` | integer | No | Page number (default: `1`) |

**Response `200 OK`**

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "created_by": 1,
      "is_group_admin_template": true,
      "template_type": "instructor",
      "orientation": "landscape",
      "name": "Group Instructor Excellence Certificate",
      "template_html": "<html>...</html>",
      "background_image_url": "http://example.com/storage/certificate-templates/1/background.jpg",
      "config_json": { "elements": [] },
      "status": "active",
      "created_at": "2026-02-26T10:00:00.000000Z",
      "updated_at": "2026-02-26T10:00:00.000000Z",
      "created_by_user": {
        "id": 1,
        "name": "Group Admin",
        "email": "admin@example.com"
      }
    }
  ],
  "per_page": 10,
  "total": 1,
  "last_page": 1
}
```

---

### 2. Create Certificate Template

```
POST /api/admin/certificate-templates
```

Creates a new group-admin instructor certificate template.

**Request Body** (`application/json`)

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | **Yes** | Template display name |
| `status` | string | **Yes** | `active` or `inactive` |
| `orientation` | string | No | `landscape` (default) or `portrait` |
| `template_html` | string | No | Raw HTML with `{{variable}}` placeholders |
| `config_json` | object | No | Template designer config (see [Template Variables](#template-variables)) |

> **Note:** Creating a template with `status: "active"` will fail if another active group-admin instructor template already exists.

**Example Request**

```json
{
  "name": "Group Instructor Excellence Certificate",
  "status": "active",
  "orientation": "landscape",
  "template_html": "<html><body><h1>{{instructor_name}}</h1><p>Authorized by {{acc_name}}</p></body></html>"
}
```

**Response `201 Created`**

```json
{
  "message": "Certificate template created successfully",
  "template": {
    "id": 1,
    "is_group_admin_template": true,
    "template_type": "instructor",
    "orientation": "landscape",
    "name": "Group Instructor Excellence Certificate",
    "status": "active",
    "created_at": "2026-02-26T10:00:00.000000Z"
  }
}
```

**Response `422 Unprocessable Entity`** — when an active template already exists:

```json
{
  "message": "An active group admin instructor certificate template already exists. Please deactivate it before creating a new one.",
  "existing_template_id": 1,
  "existing_template_name": "Group Instructor Excellence Certificate"
}
```

---

### 3. Get Certificate Template

```
GET /api/admin/certificate-templates/{id}
```

**Path Parameters**

| Parameter | Type | Description |
|---|---|---|
| `id` | integer | Template ID |

**Response `200 OK`**

```json
{
  "template": {
    "id": 1,
    "is_group_admin_template": true,
    "template_type": "instructor",
    "name": "Group Instructor Excellence Certificate",
    "orientation": "landscape",
    "status": "active",
    "template_html": "<html>...</html>",
    "config_json": { "elements": [] },
    "background_image_url": null,
    "created_by_user": {
      "id": 1,
      "name": "Group Admin",
      "email": "admin@example.com"
    }
  }
}
```

**Response `404 Not Found`** — if the template does not exist or does not belong to group admin.

---

### 4. Update Certificate Template

```
PUT /api/admin/certificate-templates/{id}
```

**Path Parameters**

| Parameter | Type | Description |
|---|---|---|
| `id` | integer | Template ID |

**Request Body** (`application/json`) — all fields optional

| Field | Type | Description |
|---|---|---|
| `name` | string | Template display name |
| `status` | string | `active` or `inactive` |
| `orientation` | string | `landscape` or `portrait` |
| `template_html` | string | Raw HTML with `{{variable}}` placeholders |
| `config_json` | object | Template designer config |

> **Note:** Activating a template (`status: "active"`) will fail if a different active template already exists.

**Response `200 OK`**

```json
{
  "message": "Template updated successfully",
  "template": { ... }
}
```

---

### 5. Upload Background Image

```
POST /api/admin/certificate-templates/{id}/upload-background
```

Uploads a background image for the template. Replaces any existing background image.

**Path Parameters**

| Parameter | Type | Description |
|---|---|---|
| `id` | integer | Template ID |

**Request Body** (`multipart/form-data`)

| Field | Type | Required | Description |
|---|---|---|---|
| `background_image` | file | **Yes** | JPEG or PNG, max 10 MB |

**Response `200 OK`**

```json
{
  "message": "Background image uploaded successfully",
  "background_image_url": "http://example.com/storage/certificate-templates/1/1234567890_1_background.jpg",
  "template": { ... }
}
```

---

### 6. Update Template Config (Designer)

```
PUT /api/admin/certificate-templates/{id}/config
```

Updates the visual designer configuration, including element positions and styling.

**Path Parameters**

| Parameter | Type | Description |
|---|---|---|
| `id` | integer | Template ID |

**Request Body** (`application/json`)

```json
{
  "config_json": {
    "elements": [
      {
        "id": "elem-1",
        "type": "text",
        "variable": "instructor_name",
        "x": 0.5,
        "y": 0.4,
        "font_family": "Arial",
        "font_size": 32,
        "color": "#1a237e",
        "font_weight": "bold",
        "text_align": "center"
      },
      {
        "id": "elem-2",
        "type": "image",
        "variable": "acc_logo",
        "x": 0.1,
        "y": 0.1,
        "width": 0.15,
        "height": 0.1
      }
    ]
  }
}
```

**Element Fields**

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | string | No | Unique element identifier |
| `type` | string | **Yes** | `text` or `image` |
| `variable` | string | **Yes** | Template variable name (see [Template Variables](#template-variables)) |
| `x` | float (0–1) | **Yes** | Horizontal position as fraction of page width |
| `y` | float (0–1) | **Yes** | Vertical position as fraction of page height |
| `width` | float (0–1) | Required for `image` | Width as fraction of page width |
| `height` | float (0–1) | Required for `image` | Height as fraction of page height |
| `font_family` | string | No | CSS font family (text elements) |
| `font_size` | integer | No | Font size in px (text elements) |
| `color` | string | No | Hex color code (text elements) |
| `font_weight` | string | No | e.g. `bold`, `normal` (text elements) |
| `text_align` | string | No | `left`, `center`, `right` (text elements) |

**Response `200 OK`**

```json
{
  "message": "Template configuration updated successfully",
  "template": { ... }
}
```

---

### 7. Delete Certificate Template

```
DELETE /api/admin/certificate-templates/{id}
```

Deletes a group-admin certificate template. Existing certificates that used the template are preserved but their `template_id` reference will be cleared.

**Response `200 OK`**

```json
{
  "message": "Template deleted successfully",
  "certificates_preserved": 0
}
```

---

## Template Variables

The following `{{variable}}` placeholders can be used inside `template_html` or referenced in `config_json` elements.

| Variable | Description |
|---|---|
| `instructor_name` | Instructor's full name |
| `instructor_first_name` | Instructor's first name |
| `instructor_last_name` | Instructor's last name |
| `instructor_email` | Instructor's email address |
| `instructor_id_number` | Instructor's national ID / passport number |
| `instructor_country` | Instructor's country |
| `instructor_city` | Instructor's city |
| `acc_name` | Name of the (first) authorizing ACC |
| `acc_legal_name` | Legal name of the ACC |
| `acc_registration_number` | ACC registration number |
| `acc_country` | ACC country |
| `acc_logo` | ACC logo image |
| `training_center_logo` | Training center logo image |
| `issue_date` | Certificate issue date (`YYYY-MM-DD`) |
| `issue_date_formatted` | Formatted issue date (e.g. `February 26, 2026`) |
| `expiry_date` | Certificate expiry date (3 years from issue) |
| `verification_code` | Unique certificate verification code |
| `qr_code` | QR code image linking to the verification page |

> For the group-admin achievement certificate, `acc_name` will reflect the first ACC that authorized the instructor. All authorizing ACCs are listed in the email body itself.

---

## Automatic Achievement Certificate Logic

The achievement certificate is triggered automatically inside `InstructorManagementService::processAuthorizationPayment()` after the TC completes a successful payment for instructor authorization.

### Trigger Conditions

All of the following must be true for the certificate to be generated and sent:

1. The authorization payment is successfully processed (status becomes `paid`).
2. The instructor has **≥ 3 distinct approved ACC authorizations** (`instructor_acc_authorization.status = 'approved'`).
3. The instructor has **not already received** a group-admin achievement certificate (no `Certificate` record exists for this instructor where the template has `is_group_admin_template = true`).
4. An **active group-admin instructor template** exists (`certificate_templates.is_group_admin_template = true`, `template_type = 'instructor'`, `status = 'active'`).

### What Happens on Trigger

```
Payment succeeds
    └── generateAndSendInstructorCertificates()   ← ACC-level cert (unchanged)
    └── checkAndSendGroupAdminCertificate()        ← NEW: achievement cert check
            ├── Count approved ACC authorizations
            ├── Guard: already sent? → skip
            ├── Guard: no active template? → skip (logs warning)
            ├── Generate PDF  (CertificateGenerationService)
            ├── Send email    (InstructorGroupCertificateMail)
            ├── Save Certificate record (course_id = null, type = instructor)
            └── Send in-app notification to instructor
```

### Database Changes

| Table | Change |
|---|---|
| `certificate_templates` | `acc_id` → nullable; `created_by` (FK users) added; `is_group_admin_template` (boolean) added |
| `certificates` | `course_id` → nullable (group-admin certs are not course-specific) |

---

## Email Sent to Instructor

**Subject:** `Congratulations! Your Multi-ACC Instructor Achievement Certificate - {app_name}`

The email includes:
- A congratulatory message highlighting the multi-accreditation achievement.
- A numbered list of all ACC names that authorized the instructor.
- The generated PDF certificate attached as `instructor_achievement_certificate.pdf`.

---

## Error Cases

| Scenario | Behavior |
|---|---|
| No active group-admin template | Certificate is **not** generated; a log entry is written. No error is returned to the TC. |
| Certificate already sent before | Skipped silently (idempotent). |
| PDF generation fails | Logged as a warning; no certificate record is saved; no email is sent. |
| Email send fails | Logged as an error; the process stops before saving the certificate record. |
| Instructor has < 3 approved ACCs | No action taken. |

---

## Example Workflow

1. **Group Admin** creates an instructor certificate template:
   ```
   POST /api/admin/certificate-templates
   { "name": "Multi-ACC Excellence Award", "status": "active" }
   ```

2. **Group Admin** uploads a background image:
   ```
   POST /api/admin/certificate-templates/1/upload-background
   (multipart: background_image = certificate_bg.jpg)
   ```

3. **Group Admin** positions text elements on the template:
   ```
   PUT /api/admin/certificate-templates/1/config
   { "config_json": { "elements": [ ... ] } }
   ```

4. Instructor gets authorized by ACC #1 → ACC #2 → ACC #3 (each paid by their TC).

5. After the **3rd payment** is confirmed, the system automatically:
   - Generates the PDF using the group-admin template.
   - Emails the achievement certificate to the instructor.
   - Creates a `Certificate` record with `type = instructor` and `template_id = 1`.
   - Sends the instructor an in-app notification: *"Congratulations! You have been authorized by 3 accreditation bodies. Your achievement certificate has been sent to your email."*
