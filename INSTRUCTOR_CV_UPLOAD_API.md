# Instructor CV Upload API Documentation

## Overview
This document describes the CV file upload functionality for instructors. The CV must be uploaded as a PDF file (not a URL string) when creating or updating an instructor.

**Base URL**: `/api/training-center/instructors`

**Authentication**: All endpoints require Bearer token authentication with `training_center_admin` role.

---

## Table of Contents

1. [Create Instructor with CV](#1-create-instructor-with-cv)
2. [Update Instructor CV](#2-update-instructor-cv)
3. [File Requirements](#3-file-requirements)
4. [Response Format](#4-response-format)
5. [Error Handling](#5-error-handling)

---

## 1. Create Instructor with CV

**Endpoint:** `POST /api/training-center/instructors`

Create a new instructor with CV file upload.

### Request Headers
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | Yes | Instructor's first name |
| `last_name` | string | Yes | Instructor's last name |
| `email` | string | Yes | Instructor's email (must be unique) |
| `phone` | string | Yes | Instructor's phone number |
| `id_number` | string | Yes | Instructor's ID number (must be unique) |
| `cv` | file | No | CV file (PDF only, max 10MB) |
| `certificates_json` | array | No | Array of certificates |
| `specializations` | array | No | Array of specializations |

### Example Request (cURL)
```bash
curl -X POST "https://your-domain.com/api/training-center/instructors" \
  -H "Authorization: Bearer {token}" \
  -F "first_name=John" \
  -F "last_name=Doe" \
  -F "email=john.doe@example.com" \
  -F "phone=+1234567890" \
  -F "id_number=ID123456" \
  -F "cv=@/path/to/cv.pdf" \
  -F "specializations[]=Fire Safety" \
  -F "specializations[]=First Aid"
```

### Example Request (JavaScript - FormData)
```javascript
const formData = new FormData();
formData.append('first_name', 'John');
formData.append('last_name', 'Doe');
formData.append('email', 'john.doe@example.com');
formData.append('phone', '+1234567890');
formData.append('id_number', 'ID123456');
formData.append('cv', cvFile); // File object from input[type="file"]
formData.append('specializations[]', 'Fire Safety');
formData.append('specializations[]', 'First Aid');

const response = await fetch('/api/training-center/instructors', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
    // Don't set Content-Type header - browser will set it with boundary
  },
  body: formData
});
```

### Response (201 Created)
```json
{
  "instructor": {
    "id": 1,
    "training_center_id": 2,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "cv_url": "/storage/instructors/cv/1234567890_2_john_doe_cv.pdf",
    "certificates_json": null,
    "specializations": ["Fire Safety", "First Aid"],
    "status": "pending",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

## 2. Update Instructor CV

**Endpoint:** `PUT /api/training-center/instructors/{id}`

Update instructor details including CV file. All fields are optional - only include fields you want to update.

### Request Headers
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | No | Instructor's first name |
| `last_name` | string | No | Instructor's last name |
| `email` | string | No | Instructor's email (must be unique if changed) |
| `phone` | string | No | Instructor's phone number |
| `id_number` | string | No | Instructor's ID number (must be unique if changed) |
| `cv` | file | No | New CV file (PDF only, max 10MB). If provided, old CV will be deleted. |
| `certificates_json` | array | No | Array of certificates |
| `specializations` | array | No | Array of specializations |

### Example Request (cURL)
```bash
curl -X PUT "https://your-domain.com/api/training-center/instructors/1" \
  -H "Authorization: Bearer {token}" \
  -F "first_name=John" \
  -F "last_name=Smith" \
  -F "cv=@/path/to/new_cv.pdf"
```

### Example Request (JavaScript - FormData)
```javascript
const formData = new FormData();
formData.append('first_name', 'John');
formData.append('last_name', 'Smith');
formData.append('cv', newCvFile); // File object from input[type="file"]

const response = await fetch(`/api/training-center/instructors/${instructorId}`, {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});
```

### Response (200 OK)
```json
{
  "message": "Instructor updated successfully",
  "instructor": {
    "id": 1,
    "training_center_id": 2,
    "first_name": "John",
    "last_name": "Smith",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "cv_url": "/storage/instructors/cv/1234567891_2_john_smith_cv.pdf",
    "certificates_json": null,
    "specializations": ["Fire Safety", "First Aid"],
    "status": "pending",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

**Note:** When a new CV file is uploaded, the old CV file is automatically deleted from storage.

---

## 3. File Requirements

### CV File Specifications

- **File Type**: PDF only (`.pdf`)
- **Maximum Size**: 10MB (10,240 KB)
- **Storage Location**: `storage/app/public/instructors/cv/`
- **File Naming**: Automatically generated as `{timestamp}_{training_center_id}_{original_filename}`
- **Access URL**: Files are accessible via `/storage/instructors/cv/{filename}`

### Validation Rules

- `cv`: `nullable|file|mimes:pdf|max:10240`
  - `nullable`: CV is optional
  - `file`: Must be a file upload
  - `mimes:pdf`: Only PDF files are allowed
  - `max:10240`: Maximum file size is 10MB (in KB)

---

## 4. Response Format

### CV URL Format

The `cv_url` field in the response contains the public URL to access the CV file:

```
/storage/instructors/cv/{timestamp}_{training_center_id}_{original_filename}
```

**Full URL Example:**
```
https://your-domain.com/storage/instructors/cv/1234567890_2_john_doe_cv.pdf
```

### Accessing the CV File

To access the CV file, use the `cv_url` from the response:

```javascript
// Get full URL
const fullUrl = `${API_BASE_URL}${instructor.cv_url}`;

// Or if cv_url already contains full URL
const cvUrl = instructor.cv_url;
```

**Important:** Make sure the storage link is created:
```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public`, making uploaded files publicly accessible.

---

## 5. Error Handling

### Validation Errors (422 Unprocessable Entity)

**Invalid File Type:**
```json
{
  "message": "The cv must be a file of type: pdf.",
  "errors": {
    "cv": ["The cv must be a file of type: pdf."]
  }
}
```

**File Too Large:**
```json
{
  "message": "The cv may not be greater than 10240 kilobytes.",
  "errors": {
    "cv": ["The cv may not be greater than 10240 kilobytes."]
  }
}
```

**Missing Required Fields:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "first_name": ["The first name field is required."],
    "email": ["The email field is required."]
  }
}
```

**Duplicate Email or ID Number:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "id_number": ["The id number has already been taken."]
  }
}
```

### Other Error Responses

**401 Unauthorized:**
```json
{
  "message": "Unauthenticated."
}
```

**403 Forbidden:**
```json
{
  "message": "Training center not found"
}
```

**404 Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\Instructor] {id}"
}
```

---

## Frontend Implementation Examples

### React Example

```jsx
import React, { useState } from 'react';

function InstructorForm({ instructorId, onSuccess }) {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    id_number: '',
    cv: null,
    specializations: []
  });

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      if (file.type !== 'application/pdf') {
        alert('Only PDF files are allowed');
        return;
      }
      if (file.size > 10 * 1024 * 1024) {
        alert('File size must be less than 10MB');
        return;
      }
      setFormData({ ...formData, cv: file });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    const data = new FormData();
    data.append('first_name', formData.first_name);
    data.append('last_name', formData.last_name);
    data.append('email', formData.email);
    data.append('phone', formData.phone);
    data.append('id_number', formData.id_number);
    if (formData.cv) {
      data.append('cv', formData.cv);
    }
    formData.specializations.forEach(spec => {
      data.append('specializations[]', spec);
    });

    const url = instructorId 
      ? `/api/training-center/instructors/${instructorId}`
      : '/api/training-center/instructors';
    
    const method = instructorId ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method,
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: data
      });

      const result = await response.json();
      
      if (response.ok) {
        onSuccess(result);
      } else {
        console.error('Error:', result);
      }
    } catch (error) {
      console.error('Network error:', error);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="text"
        placeholder="First Name"
        value={formData.first_name}
        onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
        required
      />
      <input
        type="text"
        placeholder="Last Name"
        value={formData.last_name}
        onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
        required
      />
      <input
        type="email"
        placeholder="Email"
        value={formData.email}
        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        required
      />
      <input
        type="tel"
        placeholder="Phone"
        value={formData.phone}
        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        required
      />
      <input
        type="text"
        placeholder="ID Number"
        value={formData.id_number}
        onChange={(e) => setFormData({ ...formData, id_number: e.target.value })}
        required
      />
      <input
        type="file"
        accept=".pdf"
        onChange={handleFileChange}
      />
      <button type="submit">Submit</button>
    </form>
  );
}
```

### Vue.js Example

```vue
<template>
  <form @submit.prevent="submitForm">
    <input v-model="form.first_name" placeholder="First Name" required />
    <input v-model="form.last_name" placeholder="Last Name" required />
    <input v-model="form.email" type="email" placeholder="Email" required />
    <input v-model="form.phone" type="tel" placeholder="Phone" required />
    <input v-model="form.id_number" placeholder="ID Number" required />
    <input
      type="file"
      accept=".pdf"
      @change="handleFileChange"
    />
    <button type="submit">Submit</button>
  </form>
</template>

<script>
export default {
  data() {
    return {
      form: {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        id_number: '',
        cv: null
      }
    };
  },
  methods: {
    handleFileChange(event) {
      const file = event.target.files[0];
      if (file) {
        if (file.type !== 'application/pdf') {
          alert('Only PDF files are allowed');
          return;
        }
        if (file.size > 10 * 1024 * 1024) {
          alert('File size must be less than 10MB');
          return;
        }
        this.form.cv = file;
      }
    },
    async submitForm() {
      const formData = new FormData();
      Object.keys(this.form).forEach(key => {
        if (key === 'cv' && this.form.cv) {
          formData.append('cv', this.form.cv);
        } else if (key !== 'cv') {
          formData.append(key, this.form[key]);
        }
      });

      try {
        const response = await this.$http.post('/api/training-center/instructors', formData, {
          headers: {
            'Authorization': `Bearer ${this.token}`
          }
        });
        this.$emit('success', response.data);
      } catch (error) {
        console.error('Error:', error.response.data);
      }
    }
  }
};
</script>
```

---

## Summary

- ✅ CV must be uploaded as a **PDF file** (not a URL string)
- ✅ Maximum file size: **10MB**
- ✅ Use **multipart/form-data** content type
- ✅ CV is stored in `storage/app/public/instructors/cv/`
- ✅ Old CV is automatically deleted when updating
- ✅ CV URL is returned in the `cv_url` field
- ✅ Make sure to run `php artisan storage:link` to make files publicly accessible

---

## Migration Notes

**Breaking Change:** The `cv_url` field now accepts file uploads instead of URL strings. If you have existing code that sends `cv_url` as a string, you need to update it to send `cv` as a file upload using FormData.

**Before:**
```javascript
// ❌ Old way (no longer works)
{
  cv_url: "https://example.com/cv.pdf"
}
```

**After:**
```javascript
// ✅ New way
const formData = new FormData();
formData.append('cv', cvFile); // File object
```

