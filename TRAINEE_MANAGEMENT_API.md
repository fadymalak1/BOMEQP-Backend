# Trainee Management API

This document describes the APIs for managing trainees in the BOMEQP system.

---

## Table of Contents

1. [Overview](#overview)
2. [Create Trainee](#create-trainee)
3. [List Trainees](#list-trainees)
4. [Get Trainee Details](#get-trainee-details)
5. [Update Trainee](#update-trainee)
6. [Delete Trainee](#delete-trainee)

---

## Overview

Training Centers can create and manage trainees. Each trainee can be enrolled in multiple training classes. Trainees require ID images and card images for verification.

**Base URL:** `/api/training-center/trainees`  
**Authentication:** Required (Training Center Admin)

---

## Create Trainee

**Endpoint:** `POST /api/training-center/trainees`  
**Authentication:** Required (Training Center Admin)  
**Content-Type:** `multipart/form-data`  
**Description:** Create a new trainee with file uploads for ID and card images.

### Request Body (multipart/form-data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | Yes | Trainee's first name |
| `last_name` | string | Yes | Trainee's last name |
| `email` | email | Yes | Trainee's email address (must be unique) |
| `phone` | string | Yes | Trainee's phone number |
| `id_number` | string | Yes | Trainee's ID number (must be unique) |
| `id_image` | file | Yes | ID image file (jpeg, jpg, png, pdf, max 10MB) |
| `card_image` | file | Yes | Card image file (jpeg, jpg, png, pdf, max 10MB) |
| `enrolled_classes` | array | No | Array of training class IDs to enroll the trainee in |
| `status` | enum | No | Status: `active`, `inactive`, `suspended` (default: `active`) |

### Example Request (cURL)

```bash
curl -X POST "https://aeroenix.com/v1/api/training-center/trainees" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "first_name=John" \
  -F "last_name=Doe" \
  -F "email=john.doe@example.com" \
  -F "phone=+1234567890" \
  -F "id_number=ID123456" \
  -F "id_image=@/path/to/id_image.jpg" \
  -F "card_image=@/path/to/card_image.jpg" \
  -F "enrolled_classes[]=1" \
  -F "enrolled_classes[]=2" \
  -F "status=active"
```

### Example Request (JavaScript/Fetch)

```javascript
const formData = new FormData();
formData.append('first_name', 'John');
formData.append('last_name', 'Doe');
formData.append('email', 'john.doe@example.com');
formData.append('phone', '+1234567890');
formData.append('id_number', 'ID123456');
formData.append('id_image', idImageFile); // File object
formData.append('card_image', cardImageFile); // File object
formData.append('enrolled_classes[]', '1');
formData.append('enrolled_classes[]', '2');
formData.append('status', 'active');

fetch('https://aeroenix.com/v1/api/training-center/trainees', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### Response: `201 Created`

```json
{
  "message": "Trainee created successfully",
  "trainee": {
    "id": 1,
    "training_center_id": 5,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "id_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/id_images/abc123.jpg",
    "card_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/card_images/xyz789.jpg",
    "status": "active",
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z",
    "training_classes": [
      {
        "id": 1,
        "course_id": 3,
        "instructor_id": 2,
        "start_date": "2025-01-15",
        "end_date": "2025-02-15",
        "status": "scheduled",
        "pivot": {
          "trainee_id": 1,
          "training_class_id": 1,
          "status": "enrolled",
          "enrolled_at": "2025-12-19T10:00:00.000000Z"
        }
      }
    ]
  }
}
```

### Validation Rules

- `first_name`: required, string, max:255
- `last_name`: required, string, max:255
- `email`: required, email, unique in trainees table
- `phone`: required, string, max:255
- `id_number`: required, string, unique in trainees table
- `id_image`: required, file, mimes:jpeg,jpg,png,pdf, max:10240 (10MB)
- `card_image`: required, file, mimes:jpeg,jpg,png,pdf, max:10240 (10MB)
- `enrolled_classes`: nullable, array, each item must exist in training_classes table
- `status`: optional, enum: `active`, `inactive`, `suspended`

### Error Responses

**400 Bad Request** - Validation errors
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "id_image": ["The id image must be a file of type: jpeg, jpg, png, pdf."]
  }
}
```

**404 Not Found** - Training center not found
```json
{
  "message": "Training center not found"
}
```

**500 Internal Server Error** - File upload failure
```json
{
  "message": "Failed to create trainee: [error details]"
}
```

---

## List Trainees

**Endpoint:** `GET /api/training-center/trainees`  
**Authentication:** Required (Training Center Admin)  
**Description:** Get a paginated list of trainees for the training center.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | enum | No | Filter by status: `active`, `inactive`, `suspended` |
| `search` | string | No | Search in first_name, last_name, email, phone, id_number |
| `per_page` | integer | No | Items per page (default: 15) |

### Example Request

```bash
GET /api/training-center/trainees?status=active&search=john&per_page=20
```

### Response: `200 OK`

```json
{
  "trainees": [
    {
      "id": 1,
      "training_center_id": 5,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "phone": "+1234567890",
      "id_number": "ID123456",
      "id_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/id_images/abc123.jpg",
      "card_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/card_images/xyz789.jpg",
      "status": "active",
      "created_at": "2025-12-19T10:00:00.000000Z",
      "updated_at": "2025-12-19T10:00:00.000000Z",
      "training_classes": [
        {
          "id": 1,
          "course": {
            "id": 3,
            "name": "Aviation Safety Course"
          },
          "instructor": {
            "id": 2,
            "first_name": "Jane",
            "last_name": "Smith"
          }
        }
      ]
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

---

## Get Trainee Details

**Endpoint:** `GET /api/training-center/trainees/{id}`  
**Authentication:** Required (Training Center Admin)  
**Description:** Get detailed information about a specific trainee.

### URL Parameters

- `id`: Trainee ID (integer)

### Example Request

```bash
GET /api/training-center/trainees/1
```

### Response: `200 OK`

```json
{
  "trainee": {
    "id": 1,
    "training_center_id": 5,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "id_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/id_images/abc123.jpg",
    "card_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/card_images/xyz789.jpg",
    "status": "active",
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z",
    "training_classes": [
      {
        "id": 1,
        "course_id": 3,
        "class_id": 2,
        "instructor_id": 2,
        "start_date": "2025-01-15",
        "end_date": "2025-02-15",
        "status": "scheduled",
        "course": {
          "id": 3,
          "name": "Aviation Safety Course",
          "code": "AVS001"
        },
        "instructor": {
          "id": 2,
          "first_name": "Jane",
          "last_name": "Smith"
        },
        "class_model": {
          "id": 2,
          "name": "Basic Aviation Safety"
        },
        "pivot": {
          "trainee_id": 1,
          "training_class_id": 1,
          "status": "enrolled",
          "enrolled_at": "2025-12-19T10:00:00.000000Z",
          "completed_at": null
        }
      }
    ]
  }
}
```

### Error Response: `404 Not Found`

```json
{
  "message": "No query results for model [App\\Models\\Trainee] 999"
}
```

---

## Update Trainee

**Endpoint:** `PUT /api/training-center/trainees/{id}`  
**Authentication:** Required (Training Center Admin)  
**Content-Type:** `multipart/form-data` (if uploading files) or `application/json`  
**Description:** Update trainee information. Files are optional - only include if you want to update them.

### URL Parameters

- `id`: Trainee ID (integer)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | No | Trainee's first name |
| `last_name` | string | No | Trainee's last name |
| `email` | email | No | Trainee's email address (must be unique if changed) |
| `phone` | string | No | Trainee's phone number |
| `id_number` | string | No | Trainee's ID number (must be unique if changed) |
| `id_image` | file | No | New ID image file (jpeg, jpg, png, pdf, max 10MB) |
| `card_image` | file | No | New card image file (jpeg, jpg, png, pdf, max 10MB) |
| `enrolled_classes` | array | No | Array of training class IDs (replaces existing enrollments) |
| `status` | enum | No | Status: `active`, `inactive`, `suspended` |

### Example Request (JSON - no file updates)

```json
{
  "first_name": "John",
  "last_name": "Doe Updated",
  "phone": "+1234567891",
  "status": "active",
  "enrolled_classes": [1, 3, 5]
}
```

### Example Request (multipart/form-data - with file updates)

```bash
curl -X PUT "https://aeroenix.com/v1/api/training-center/trainees/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "first_name=John" \
  -F "last_name=Doe Updated" \
  -F "id_image=@/path/to/new_id_image.jpg" \
  -F "enrolled_classes[]=1" \
  -F "enrolled_classes[]=3"
```

### Response: `200 OK`

```json
{
  "message": "Trainee updated successfully",
  "trainee": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe Updated",
    "email": "john.doe@example.com",
    "phone": "+1234567891",
    "id_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/id_images/new_abc123.jpg",
    "card_image_url": "https://aeroenix.com/v1/storage/app/public/trainees/5/card_images/xyz789.jpg",
    "status": "active",
    "training_classes": [
      {
        "id": 1,
        "pivot": {
          "status": "enrolled",
          "enrolled_at": "2025-12-19T10:00:00.000000Z"
        }
      },
      {
        "id": 3,
        "pivot": {
          "status": "enrolled",
          "enrolled_at": "2025-12-19T11:00:00.000000Z"
        }
      }
    ]
  }
}
```

### Notes

- **File Updates**: If you provide new files, the old files will be automatically deleted.
- **Enrolled Classes**: Providing `enrolled_classes` will replace all existing enrollments. Only classes belonging to the training center will be enrolled.
- **Enrollment Count**: The `enrolled_count` for training classes is automatically updated when enrollments change.

---

## Delete Trainee

**Endpoint:** `DELETE /api/training-center/trainees/{id}`  
**Authentication:** Required (Training Center Admin)  
**Description:** Delete a trainee and all associated files.

### URL Parameters

- `id`: Trainee ID (integer)

### Example Request

```bash
DELETE /api/training-center/trainees/1
```

### Response: `200 OK`

```json
{
  "message": "Trainee deleted successfully"
}
```

### Notes

- **File Cleanup**: All uploaded files (ID image and card image) are automatically deleted.
- **Enrollment Cleanup**: All class enrollments are removed and `enrolled_count` is decremented for affected classes.
- **Cascade Delete**: The trainee record and all related pivot table entries are deleted.

---

## Summary

### Endpoints

- ✅ **Create Trainee**: `POST /api/training-center/trainees`
- ✅ **List Trainees**: `GET /api/training-center/trainees`
- ✅ **Get Trainee**: `GET /api/training-center/trainees/{id}`
- ✅ **Update Trainee**: `PUT /api/training-center/trainees/{id}`
- ✅ **Delete Trainee**: `DELETE /api/training-center/trainees/{id}`

### Key Features

1. **File Uploads**: Support for ID image and card image uploads (jpeg, jpg, png, pdf, max 10MB)
2. **Class Enrollment**: Trainees can be enrolled in multiple training classes
3. **Automatic Management**: Enrollment counts are automatically maintained
4. **File Cleanup**: Old files are automatically deleted when updated or trainee is deleted
5. **Search & Filter**: List endpoint supports search and status filtering
6. **Pagination**: List endpoint includes pagination support

### File Storage

Files are stored in:
- **ID Images**: `storage/app/public/trainees/{training_center_id}/id_images/`
- **Card Images**: `storage/app/public/trainees/{training_center_id}/card_images/`

File URLs are generated using the `STORAGE_URL` configuration from `.env`.

### Database Schema

**trainees table:**
- `id` (primary key)
- `training_center_id` (foreign key)
- `first_name`, `last_name`
- `email` (unique)
- `phone`
- `id_number` (unique)
- `id_image_url`
- `card_image_url`
- `status` (enum: active, inactive, suspended)
- `created_at`, `updated_at`

**trainee_training_class pivot table:**
- `trainee_id` (foreign key)
- `training_class_id` (foreign key)
- `status` (enum: enrolled, completed, dropped, failed)
- `enrolled_at`, `completed_at`
- `created_at`, `updated_at`

---

**Last Updated:** December 19, 2025

