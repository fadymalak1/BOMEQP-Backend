# API: Categories & Subcategories Excel/CSV Import/Export

This document describes the new endpoints for downloading Excel/CSV templates and uploading bulk data for **categories** and **subcategories**. Available to **group_admin** and **acc_admin** roles.

---

## Base URL

All endpoints use the `api` prefix (e.g. `/api/admin/...`).

---

## Authentication

All endpoints require **Bearer token** authentication (Sanctum).

```
Authorization: Bearer {token}
```

---

## 1. Categories Template Download

Download an Excel or CSV template for bulk category import.

| Method | Endpoint |
|--------|----------|
| `GET` | `/api/admin/categories/template/download` |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `format` | string | No | `xlsx` | `xlsx` (Excel) or `csv` |

### Example Request

```
GET /api/admin/categories/template/download?format=xlsx
GET /api/admin/categories/template/download?format=csv
```

### Response

- **Content-Type:** `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (xlsx) or `text/csv` (csv)
- **Content-Disposition:** `attachment; filename="categories_template.xlsx"` (or `.csv`)
- **Body:** Binary file (template with headers and one sample row)

### Template Columns

| Column | Required | Description |
|--------|----------|-------------|
| `name` | Yes | Category name (English) |
| `name_ar` | No | Category name (Arabic) |
| `description` | No | Description |
| `icon_url` | No | Icon URL |
| `status` | Yes | `active` or `inactive` |

---

## 2. Categories Import

Upload an Excel or CSV file to bulk create/update categories.

| Method | Endpoint |
|--------|----------|
| `POST` | `/api/admin/categories/import` |

### Request

- **Content-Type:** `multipart/form-data`
- **Body:** `file` (Excel `.xlsx` or CSV `.csv`)

### Example Request

```
POST /api/admin/categories/import
Content-Type: multipart/form-data
Body: file = [binary file]
```

### Response (200 OK)

```json
{
  "message": "Categories imported successfully",
  "created_count": 5,
  "updated_count": 2,
  "errors": []
}
```

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Success message |
| `created_count` | integer | Number of new categories created |
| `updated_count` | integer | Number of existing categories updated |
| `errors` | array | Row-level error messages (if any) |

### Error Response (422)

```json
{
  "message": "Validation failed",
  "errors": ["Row 3: Category name is required."]
}
```

---

## 3. Subcategories Template Download

Download an Excel or CSV template for bulk subcategory import. **Excel format includes a dropdown in the category column** to select from existing categories.

| Method | Endpoint |
|--------|----------|
| `GET` | `/api/admin/sub-categories/template/download` |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `format` | string | No | `xlsx` | `xlsx` (Excel, with dropdown) or `csv` |

### Example Request

```
GET /api/admin/sub-categories/template/download?format=xlsx
GET /api/admin/sub-categories/template/download?format=csv
```

### Response

- **Content-Type:** `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (xlsx) or `text/csv` (csv)
- **Content-Disposition:** `attachment; filename="subcategories_template.xlsx"` (or `.csv`)
- **Body:** Binary file

### Template Columns

| Column | Required | Description |
|--------|----------|-------------|
| `category` | Yes | Category name. In **Excel**: use dropdown to select. In **CSV**: enter exact category name. |
| `name` | Yes | Subcategory name |
| `name_ar` | No | Subcategory name (Arabic) |
| `description` | No | Description |
| `status` | Yes | `active` or `inactive` |

### Notes for Frontend

- **Excel (.xlsx):** The category column has data validation (dropdown) listing all existing categories. User selects from the list.
- **CSV:** No dropdown. User must type the exact category name. Consider showing a list of valid category names in the UI when user chooses CSV.

---

## 4. Subcategories Import

Upload an Excel or CSV file to bulk create/update subcategories.

| Method | Endpoint |
|--------|----------|
| `POST` | `/api/admin/sub-categories/import` |

### Request

- **Content-Type:** `multipart/form-data`
- **Body:** `file` (Excel `.xlsx` or CSV `.csv`)

### Example Request

```
POST /api/admin/sub-categories/import
Content-Type: multipart/form-data
Body: file = [binary file]
```

### Response (200 OK)

```json
{
  "message": "Subcategories imported successfully",
  "created_count": 8,
  "updated_count": 1,
  "errors": []
}
```

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Success message |
| `created_count` | integer | Number of new subcategories created |
| `updated_count` | integer | Number of existing subcategories updated |
| `errors` | array | Row-level error messages (e.g. invalid category name) |

### Error Response (422)

```json
{
  "message": "Import failed",
  "errors": ["Row 5: Category 'Invalid Category' not found. Use exact name from dropdown."]
}
```

---

## Summary Table

| Action | Method | Endpoint |
|--------|--------|----------|
| Download categories template | `GET` | `/api/admin/categories/template/download?format=xlsx\|csv` |
| Import categories | `POST` | `/api/admin/categories/import` |
| Download subcategories template | `GET` | `/api/admin/sub-categories/template/download?format=xlsx\|csv` |
| Import subcategories | `POST` | `/api/admin/sub-categories/import` |

---

## Allowed Roles

- `group_admin`
- `acc_admin`

---

## File Format Support

| Format | Extension | Notes |
|--------|-----------|-------|
| Excel | `.xlsx` | Recommended for subcategories (category dropdown) |
| CSV | `.csv`, `.txt` | No dropdown; user must enter exact values |
