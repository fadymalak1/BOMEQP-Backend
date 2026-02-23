# Certificate Designer - Backend Implementation Summary

This document summarizes the backend changes implemented per the Certificate Designer Backend Implementation Guide.

## 1. Orientation Support ✅

- **Migration:** `2026_02_23_000001_add_orientation_to_certificate_templates_table.php`
  - Added `orientation` column (VARCHAR, default: `'landscape'`)
- **Model:** `CertificateTemplate` - added `orientation` to `$fillable`
- **API:** `orientation` accepted in template create/update (`landscape` | `portrait`)
- **Page dimensions:**
  - Landscape: 1200×848 px
  - Portrait: 848×1200 px

## 2. Dynamic Template Variables ✅

### Variable mapping (API → template)
- `training_center_logo_url` → `{{training_center_logo}}`
- `acc_logo_url` → `{{acc_logo}}`
- `qr_code_url` → `{{qr_code}}`

### Template type variables
- **Course:** `{{training_center_logo}}`, `{{acc_logo}}`, `{{qr_code}}`
- **Training Center:** same + `{{verification_code}}`
- **Instructor:** same + `{{expiry_date}}`, `{{verification_code}}`

## 3. Image Placeholder Elements ✅

- Image variables (`{{training_center_logo}}`, `{{acc_logo}}`, `{{qr_code}}`) render as `<img>` elements
- `config_json` supports `type: "image"` with `width`, `height` (normalized 0–1)
- PNG generation overlays images via GD
- PDF generation uses variable replacement in `template_html` (e.g. `<img src="{{training_center_logo}}">`)

## 4. config_json Structure ✅

- Supports `{ "elements": [...] }` or direct array
- Element properties: `id`, `type` (text|image), `variable`, `x`, `y`, `width`, `height`
- Validation: image elements require `width` and `height` (0–1)
- Stored as `{ elements: [...] }` for consistency

## 5. Certificate Generation Updates ✅

- **Orientation:** PDF uses template orientation for page size
- **Logos:** `training_center_logo`, `acc_logo` from TrainingCenter/ACC `logo_url`
- **QR code:** Generated via `https://api.qrserver.com/v1/create-qr-code/` with verification URL
- **Instructor expiry:** Default 3 years from issue date
- **Data normalization:** API keys (`training_center_logo_url`, etc.) mapped to template variables

## 6. API Endpoints ✅

### Update Template
- `PUT /acc/certificate-templates/{id}` – accepts `orientation`, `config_json`, `template_html`

### Generate Certificate
- `POST /acc/certificates/generate`
- Body: `{ "template_id": 123, "data": { ... } }`
- Response: `{ "certificate_id", "pdf_url", "preview_url" }`

## 7. Validation Rules ✅

- **Orientation:** `landscape` | `portrait`
- **config_json:** Valid elements array; image elements require `width`, `height` (0–1)
- **Elements:** `id`, `type`, `variable`, `x`, `y` required; `x`, `y` in 0–1 range

## 8. Migration

Run:
```bash
php artisan migrate
```

## 9. Testing Checklist

- [ ] Template saves with correct orientation
- [ ] Orientation change updates page dimensions
- [ ] Image placeholders render as `<img>` elements
- [ ] Text variables render as text elements
- [ ] QR code images display correctly
- [ ] Logo images display correctly
- [ ] Normalized coordinates work for both orientations
- [ ] Certificate generation produces correct PDF output
- [ ] All template types (course, training_center, instructor) work correctly
