# API: ACC Card Template

This document describes the **Card Template** feature for ACCs. An ACC can design a card that is appended as a **second page** to any certificate PDF that has the `include_card` switch enabled.

The card visually resembles a wallet-sized ID card (see reference image) and displays key information such as the holder's name, course/certification title, training center, issue/expiry dates, and a serial number.

---

## Recent update (new)

> **Recently added:** Card templates now support **two sides** (`front` and `back`).
>
> You can choose which side to edit by sending `side: "front"` or `side: "back"` in card update endpoints.
> - If `side` is omitted, the API defaults to `front` (backward-compatible behavior).
> - Card PDF output can now contain **2 pages**: page 1 = front side, page 2 = back side (when both sides are configured).

---

## Overview

- **Single card design per ACC**: all certificate templates for an ACC share the same card design (HTML / background / config).
- The **card design** is controlled by three fields (stored on each certificate template row but updated **globally** for the ACC:
  - `card_template_html` — full custom HTML for the card page (highest priority).
  - `card_background_image_url` — background image; overlaid with elements from `card_config_json`.
  - `card_config_json` — designer configuration (elements with coordinates and styling).
- **Recently added:** each card design can now be edited per-side:
  - **Front side fields:** `card_template_html`, `card_background_image_url`, `card_config_json`
  - **Back side fields:** `card_back_template_html`, `card_back_background_image_url`, `card_back_config_json`
- The **`include_card`** boolean field on a certificate template acts as the **per-template switch**:
  - `true` → the generated output includes card pages.
  - `false` (default) → single-page PDF, card is ignored even if a card design exists.
- `include_card` can be toggled on `store`, `update`, and `PUT /card` endpoints. Changing `card_template_html`, `card_background_image_url`, or `card_config_json` via the ACC endpoints updates the design for **all** templates of that ACC.

---

## Authentication

All endpoints require a valid Sanctum bearer token:

```
Authorization: Bearer {token}
```

All card template endpoints are restricted to users with the `acc_admin` role (`middleware: role:acc_admin, acc.active`).

---

## Base URL

```
/api/acc
```

---

## Endpoints

### 1. Get ACC Card Design and Templates Using It

```
GET /api/acc/card-template
```

Returns:

- The **single shared card design** for the ACC (from the most recently updated certificate template).
- The list of certificate templates that currently have `include_card = true`.
- **Recently added:** response includes a `sides` object with `front` and `back` design payloads.

#### Response `200 OK`

```json
{
  "card_template": {
    "id": 7,
    "name": "ACC Default Card Template",
    "card_template_html": null,
    "card_background_image_url": "https://app.bomeqp.com/.../card_background.jpg",
    "card_config_json": {
      "elements": [
        { "type": "text", "variable": "{{trainee_name}}", "x": 0.38, "y": 0.20, "font_size": 18, "font_weight": "bold", "color": "#ffffff" },
        { "type": "text", "variable": "{{course_name}}", "x": 0.38, "y": 0.38, "font_size": 13, "color": "#e0e0e0" },
        { "type": "image", "variable": "{{instructor_photo}}", "x": 0.04, "y": 0.12, "width": 0.27, "height": 0.55 },
        { "type": "image", "variable": "{{trainee_photo}}", "x": 0.70, "y": 0.12, "width": 0.25, "height": 0.55 }
      ]
    },
    "card_back_template_html": null,
    "card_back_background_image_url": "https://app.bomeqp.com/.../card_background_back.jpg",
    "card_back_config_json": {
      "elements": []
    },
    "sides": {
      "front": {
        "template_html": null,
        "background_image_url": "https://app.bomeqp.com/.../card_background.jpg",
        "config_json": { "elements": [] }
      },
      "back": {
        "template_html": null,
        "background_image_url": "https://app.bomeqp.com/.../card_background_back.jpg",
        "config_json": { "elements": [] }
      }
    }
  },
  "certificate_templates": [
    {
      "id": 5,
      "name": "QHSE Course Certificate",
      "include_card": true,
      "status": "active"
    },
    {
      "id": 9,
      "name": "Safety Management Diploma",
      "include_card": true,
      "status": "active"
    }
  ]
}
```

When no card designs exist (no template has any card fields set), `card_template` will be `null` and `certificate_templates` will be an empty array:

```json
{
  "card_template": null,
  "certificate_templates": []
}
```

---

### 2. Create / Update Card Design on a Template

```
PUT /api/acc/certificate-templates/{id}/card
```

This endpoint has **two behaviors**:

- **Global card design update** (shared across all templates for this ACC):
  - Sending `card_template_html` and/or `card_config_json` **updates the shared card design for all templates** of this ACC.
- **Per-template toggle / metadata**:
  - `include_card` and `name` apply **only** to the template with the given `{id}`.

#### Path Parameters

| Parameter | Type    | Required | Description                              |
|-----------|---------|----------|------------------------------------------|
| `id`      | integer | Yes      | ID of the certificate template to attach the card to |

#### Request Body

| Field                | Type    | Required | Description |
|----------------------|---------|----------|-------------|
| `include_card`       | boolean | No       | Toggle the card page in PDF generation (`true` = 2-page PDF) |
| `side`               | string  | No       | **Recently added.** Which side to edit: `"front"` or `"back"`. Default: `"front"` |
| `card_template_html` | string  | No       | Full custom HTML for the card page. Supports the same `{{variable}}` placeholders as certificate templates. Takes priority over `card_config_json`. **Global:** updates shared card HTML for all templates of this ACC. |
| `card_config_json`   | object  | No       | Designer config — see [Card Config JSON Schema](#card-config-json-schema) below. **Global:** updates shared card config for all templates of this ACC. |
| `name`               | string  | No       | Optionally update the template name at the same time |

#### Example Request

```json
{
  "include_card": true,
  "side": "back",
  "card_config_json": {
    "elements": [
      {
        "type": "text",
        "variable": "{{instructor_name}}",
        "x": 0.38,
        "y": 0.20,
        "font_size": 18,
        "font_weight": "bold",
        "color": "#ffffff",
        "text_align": "left"
      },
      {
        "type": "image",
        "variable": "{{instructor_photo}}",
        "x": 0.04,
        "y": 0.12,
        "width": 0.27,
        "height": 0.55
      }
    ]
  }
}
```

#### Response `200 OK`

```json
{
  "message": "Card template saved successfully",
  "template": {
    "id": 5,
    "acc_id": 2,
    "name": "QHSE Course Certificate",
    "template_type": "course",
    "orientation": "landscape",
    "include_card": true,
    "card_template_html": null,
    "card_background_image_url": "https://app.bomeqp.com/.../card_background.jpg",
    "card_config_json": { "elements": [ "..." ] },
    "status": "active",
    "created_at": "2026-02-26T10:00:00.000000Z",
    "updated_at": "2026-02-26T15:30:00.000000Z"
  }
}
```

#### Error Responses

| Status | Message |
|--------|---------|
| `404`  | `"ACC not found"` or `"No query results for model [CertificateTemplate]"` |
| `422`  | Validation error |

---

### 3. Upload Card Background Image

```
POST /api/acc/certificate-templates/{id}/upload-card-background
```

Uploads a background image for the card page. Accepted formats: JPEG, PNG. Max size: 10 MB.

Uploading a card background automatically sets `include_card = true` on the template.

**Recently added:** send `side` to upload front or back background.

#### Path Parameters

| Parameter | Type    | Required | Description                              |
|-----------|---------|----------|------------------------------------------|
| `id`      | integer | Yes      | ID of the certificate template           |

#### Request Body (`multipart/form-data`)

| Field                    | Type   | Required | Description                         |
|--------------------------|--------|----------|-------------------------------------|
| `card_background_image`  | file   | Yes      | JPG or PNG image, max 10 MB         |
| `side`                   | string | No       | `"front"` or `"back"` (default `"front"`) |

#### Response `200 OK`

```json
{
  "message": "Card background image uploaded successfully",
  "side": "back",
  "card_background_image_url": "https://app.bomeqp.com/storage/certificate-templates/5/card/1740571234_5_card_background.jpg",
  "template": {
    "id": 5,
    "include_card": true,
    "card_background_image_url": "https://app.bomeqp.com/storage/certificate-templates/5/card/1740571234_5_card_background.jpg",
    "..."
  }
}
```

#### Error Responses

| Status | Message |
|--------|---------|
| `404`  | Template not found |
| `422`  | `"The card background image field is required"` / invalid file type |
| `500`  | Upload failed (server error) |

---

### 4. Update Card Designer Configuration

```
PUT /api/acc/certificate-templates/{id}/card-config
```

Updates only the `card_config_json` of the card page. This is equivalent to saving the card designer canvas state.

**Recently added:** send `side` to update front or back config.

#### Path Parameters

| Parameter | Type    | Required | Description                              |
|-----------|---------|----------|------------------------------------------|
| `id`      | integer | Yes      | ID of the certificate template           |

#### Request Body

| Field             | Type          | Required | Description |
|-------------------|---------------|----------|-------------|
| `card_config_json`| object\|array | Yes      | Object with `elements` array, or a direct array of elements |
| `side`            | string        | No       | `"front"` or `"back"` (default `"front"`) |

#### Response `200 OK`

```json
{
  "message": "Card configuration updated successfully",
  "side": "back",
  "template": {
    "id": 5,
    "card_config_json": {
      "elements": [ "..." ]
    },
    "..."
  }
}
```

#### Error Responses

| Status | Message |
|--------|---------|
| `404`  | Template not found |
| `422`  | Validation error on element structure (see below) |

---

### 5. Toggle `include_card` via Update Template

The standard certificate template `update` endpoint also accepts `include_card`:

```
PUT /api/acc/certificate-templates/{id}
```

```json
{
  "include_card": false
}
```

This disables the card page for the specified template without removing the card design data.

---

### 6. Toggle `include_card` on Template Create

The standard certificate template `store` endpoint also accepts `include_card`:

```
POST /api/acc/certificate-templates
```

```json
{
  "name": "Safety Course Certificate",
  "template_type": "course",
  "course_ids": [3],
  "status": "active",
  "include_card": true
}
```

---

## Card Config JSON Schema

The `card_config_json` and `card_template_html` fields support the same `{{variable}}` placeholder system as certificate templates.

### Structure

```json
{
  "elements": [
    { ... },
    { ... }
  ]
}
```

A direct array (without the `elements` wrapper) is also accepted and normalized internally.

### Element Object

| Field         | Type    | Required for     | Description |
|---------------|---------|------------------|-------------|
| `type`        | string  | All              | `"text"` or `"image"` |
| `variable`    | string  | All              | Variable name (e.g. `{{instructor_name}}`) or literal text |
| `x`           | float   | All              | Horizontal position as a fraction of the card width (`0.0` – `1.0`, left → right) |
| `y`           | float   | All              | Vertical position as a fraction of the card height (`0.0` – `1.0`, top → bottom) |
| `width`       | float   | `image` elements | Width as a fraction of the card width (`0.0` – `1.0`) |
| `height`      | float   | `image` elements | Height as a fraction of the card height (`0.0` – `1.0`) |
| `font_size`   | integer | `text` elements  | Font size in pixels |
| `font_family` | string  | `text` elements  | Font family name (e.g. `"Arial"`, `"Georgia"`) |
| `font_weight` | string  | `text` elements  | `"normal"` or `"bold"` |
| `color`       | string  | `text` elements  | CSS color value (e.g. `"#ffffff"`, `"#1a2b3c"`) |
| `text_align`  | string  | `text` elements  | `"left"`, `"center"`, or `"right"` |

### Supported Variables

The card shares the same variable pool as the certificate template. Common variables:

| Variable | Description |
|---|---|
| `{{instructor_name}}` | Full name of the instructor |
| `{{instructor_first_name}}` | First name |
| `{{instructor_last_name}}` | Last name |
| `{{instructor_photo}}` | Instructor profile photo (image element) |
| `{{trainee_photo}}` | Trainee/student photo for the card (image element). Supply via `trainee_id` or `trainee_photo` when issuing the certificate. |
| `{{course_name}}` | Name of the certified course |
| `{{course_code}}` | Course code |
| `{{training_center_name}}` | Name of the training center |
| `{{acc_name}}` | Name of the ACC |
| `{{acc_logo}}` | ACC logo (image element) |
| `{{training_center_logo}}` | Training center logo (image element) |
| `{{issue_date}}` | Issue date (`YYYY-MM-DD`) |
| `{{issue_date_formatted}}` | Issue date (`Month D, YYYY`) |
| `{{expiry_date}}` | Expiry date (`YYYY-MM-DD`) |
| `{{serial_number}}` | Certificate serial number / verification code |
| `{{qr_code}}` | QR code image pointing to the verification page (image element) |

---

## PDF Generation Behaviour

When a certificate is generated for a template with `include_card = true`:

- Main certificate PDF remains generated as before.
- Card PDF rendering now supports both sides:
  - **Card page 1 (front)** uses:
    1. `card_template_html` (if set), else
    2. `card_background_image_url` + `card_config_json`, else
    3. `card_background_image_url` only.
  - **Card page 2 (back)** uses:
    1. `card_back_template_html` (if set), else
    2. `card_back_background_image_url` + `card_back_config_json`, else
    3. `card_back_background_image_url` only.
- If only one side is configured, card output contains a single card page.
- If both sides are configured, card output contains **2 card pages** (front then back).

If `include_card = false` or no card content is configured, a standard single-page PDF is produced.

---

## Example Workflow

### Step 1 — Create (or pick) a certificate template

```http
POST /api/acc/certificate-templates
Content-Type: application/json

{
  "name": "QHSE Diploma",
  "template_type": "course",
  "course_ids": [7],
  "status": "active",
  "include_card": false
}
```

Response → `{ "template": { "id": 12, ... } }`

---

### Step 2 — Upload the card background image

```http
POST /api/acc/certificate-templates/12/upload-card-background
Content-Type: multipart/form-data

card_background_image: <file>
```

Response → `{ "card_background_image_url": "https://...", "template": { "include_card": true, ... } }`

---

### Step 3 — Set the card element layout

```http
PUT /api/acc/certificate-templates/12/card-config
Content-Type: application/json

{
  "card_config_json": {
    "elements": [
      { "type": "image",  "variable": "{{instructor_photo}}", "x": 0.04, "y": 0.12, "width": 0.25, "height": 0.52 },
      { "type": "image",  "variable": "{{trainee_photo}}", "x": 0.70, "y": 0.12, "width": 0.25, "height": 0.52 },
      { "type": "text",   "variable": "{{instructor_name}}",  "x": 0.34, "y": 0.20, "font_size": 17, "font_weight": "bold", "color": "#ffffff" },
      { "type": "text",   "variable": "{{course_name}}",      "x": 0.34, "y": 0.38, "font_size": 12, "color": "#e0e0e0" },
      { "type": "text",   "variable": "Training center: {{training_center_name}}", "x": 0.06, "y": 0.72, "font_size": 10, "color": "#ffffff" },
      { "type": "text",   "variable": "Issued on {{issue_date_formatted}}", "x": 0.06, "y": 0.82, "font_size": 10, "color": "#ffffff" },
      { "type": "text",   "variable": "Valid to: {{expiry_date}}", "x": 0.06, "y": 0.90, "font_size": 10, "color": "#ffffff" },
      { "type": "text",   "variable": "Serial No. {{serial_number}}", "x": 0.55, "y": 0.82, "font_size": 10, "color": "#ffffff" }
    ]
  }
}
```

---

### Step 4 — Verify the card template

```http
GET /api/acc/card-template
```

---

### Step 5 — Generate a certificate (2-page PDF)

Certificate generation is triggered via the existing generate endpoint. Because `include_card = true` on template 12, the resulting PDF will automatically contain the card on page 2.

---

## Notes

- The card image is stored at `certificate-templates/{template_id}/card/` in the `public` storage disk.
- Deleting a certificate template also removes its card background image file from storage.
- **Multiple certificate templates can each have their own card design** — there is no one-card-per-ACC restriction.
- Each template's `include_card` flag is independent; enabling it on one template does not affect others.
- Setting `include_card = false` disables the card page in PDFs but **does not delete** the card design. The card can be re-enabled at any time by setting `include_card = true` again.
- `card_template_html` takes **priority over** `card_config_json`. If both are set, only `card_template_html` is used to render the card page.
