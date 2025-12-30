# CV Update Fix Documentation

## Overview

This document describes the fixes made to the instructor CV update functionality. The CV update now works correctly for both:
- Training centers updating instructor CVs
- Instructors updating their own CVs from the profile page

## Changes Made

### 1. Enhanced Error Handling
- Added comprehensive try-catch blocks around file upload operations
- Added detailed error logging for debugging
- Returns proper error responses when file upload fails

### 2. Filename Sanitization
- Filenames are now sanitized to remove special characters that could cause storage issues
- Only alphanumeric characters, dots, underscores, and hyphens are allowed in filenames

### 3. File Storage Validation
- Added validation to ensure file is successfully stored before updating the database
- Prevents database updates if file storage fails

### 4. Model Refresh
- Instructor model is refreshed after update to ensure response contains latest data
- Ensures the updated `cv_url` is returned in the response

## API Endpoints

### 1. Update Instructor CV (Training Center)
**Endpoint:** `PUT /api/training-center/instructors/{id}`

**Authentication:** Required (Training Center Admin)

**Request Type:** `multipart/form-data`

**Request Body:**
```javascript
FormData {
  cv: File, // PDF file, max 10MB
  // Other optional fields:
  first_name: string,
  last_name: string,
  email: string,
  phone: string,
  // ... other instructor fields
}
```

**Response (200):**
```json
{
  "message": "Instructor updated successfully",
  "instructor": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "cv_url": "https://yourdomain.com/api/storage/instructors/cv/1234567890_11_cv.pdf",
    "training_center": {
      "id": 11,
      "name": "Training Center Name"
    },
    // ... other fields
  }
}
```

**Error Response (500):**
```json
{
  "message": "Failed to upload CV file",
  "error": "Detailed error message (only in debug mode)"
}
```

---

### 2. Update Instructor CV (Instructor Profile)
**Endpoint:** `PUT /api/instructor/profile`

**Authentication:** Required (Instructor)

**Request Type:** `multipart/form-data`

**Request Body:**
```javascript
FormData {
  cv: File, // PDF file, max 10MB
  // Other optional fields:
  first_name: string,
  last_name: string,
  phone: string,
  country: string,
  city: string,
  // ... other profile fields
}
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "cv_url": "https://yourdomain.com/api/storage/instructors/cv/1234567890_11_cv.pdf",
    "country": "Egypt",
    "city": "Cairo",
    // ... other fields
  }
}
```

**Error Response (500):**
```json
{
  "message": "Failed to upload CV file",
  "error": "Detailed error message (only in debug mode)"
}
```

---

## Frontend Implementation

### React Example

#### Training Center Updating Instructor CV

```jsx
import React, { useState } from 'react';
import axios from 'axios';

function UpdateInstructorCV({ instructorId }) {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile) {
      // Validate file type
      if (selectedFile.type !== 'application/pdf') {
        setError('Only PDF files are allowed');
        return;
      }
      // Validate file size (10MB = 10 * 1024 * 1024 bytes)
      if (selectedFile.size > 10 * 1024 * 1024) {
        setError('File size must be less than 10MB');
        return;
      }
      setFile(selectedFile);
      setError(null);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      setError('Please select a CV file');
      return;
    }

    setLoading(true);
    setError(null);
    setSuccess(false);

    try {
      const formData = new FormData();
      formData.append('cv', file);

      const token = localStorage.getItem('token');
      const response = await axios.put(
        `/api/training-center/instructors/${instructorId}`,
        formData,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'multipart/form-data',
          },
        }
      );

      if (response.data.instructor.cv_url) {
        setSuccess(true);
        setFile(null);
        // Reset file input
        e.target.reset();
        // Optionally update your instructor state
        console.log('Updated CV URL:', response.data.instructor.cv_url);
      }
    } catch (err) {
      if (err.response) {
        setError(err.response.data.message || 'Failed to update CV');
      } else {
        setError('Network error. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div>
        <label htmlFor="cv">Upload CV (PDF, max 10MB)</label>
        <input
          type="file"
          id="cv"
          accept=".pdf,application/pdf"
          onChange={handleFileChange}
          disabled={loading}
        />
      </div>

      {error && <div style={{ color: 'red' }}>{error}</div>}
      {success && <div style={{ color: 'green' }}>CV updated successfully!</div>}

      <button type="submit" disabled={loading || !file}>
        {loading ? 'Uploading...' : 'Update CV'}
      </button>
    </form>
  );
}

export default UpdateInstructorCV;
```

#### Instructor Updating Own CV

```jsx
import React, { useState } from 'react';
import axios from 'axios';

function UpdateProfileCV() {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile) {
      if (selectedFile.type !== 'application/pdf') {
        setError('Only PDF files are allowed');
        return;
      }
      if (selectedFile.size > 10 * 1024 * 1024) {
        setError('File size must be less than 10MB');
        return;
      }
      setFile(selectedFile);
      setError(null);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      setError('Please select a CV file');
      return;
    }

    setLoading(true);
    setError(null);
    setSuccess(false);

    try {
      const formData = new FormData();
      formData.append('cv', file);

      const token = localStorage.getItem('token');
      const response = await axios.put(
        '/api/instructor/profile',
        formData,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'multipart/form-data',
          },
        }
      );

      if (response.data.profile.cv_url) {
        setSuccess(true);
        setFile(null);
        e.target.reset();
        // Update profile state
        console.log('Updated CV URL:', response.data.profile.cv_url);
      }
    } catch (err) {
      if (err.response) {
        setError(err.response.data.message || 'Failed to update CV');
      } else {
        setError('Network error. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div>
        <label htmlFor="cv">Upload CV (PDF, max 10MB)</label>
        <input
          type="file"
          id="cv"
          accept=".pdf,application/pdf"
          onChange={handleFileChange}
          disabled={loading}
        />
      </div>

      {error && <div style={{ color: 'red' }}>{error}</div>}
      {success && <div style={{ color: 'green' }}>CV updated successfully!</div>}

      <button type="submit" disabled={loading || !file}>
        {loading ? 'Uploading...' : 'Update CV'}
      </button>
    </form>
  );
}

export default UpdateProfileCV;
```

### Vue.js Example

```vue
<template>
  <form @submit.prevent="handleSubmit">
    <div>
      <label for="cv">Upload CV (PDF, max 10MB)</label>
      <input
        type="file"
        id="cv"
        accept=".pdf,application/pdf"
        @change="handleFileChange"
        :disabled="loading"
      />
    </div>

    <div v-if="error" style="color: red">{{ error }}</div>
    <div v-if="success" style="color: green">CV updated successfully!</div>

    <button type="submit" :disabled="loading || !file">
      {{ loading ? 'Uploading...' : 'Update CV' }}
    </button>
  </form>
</template>

<script>
import axios from 'axios';

export default {
  name: 'UpdateCV',
  props: {
    instructorId: Number, // Only for training center update
    isProfile: Boolean, // true for instructor profile, false for training center
  },
  data() {
    return {
      file: null,
      loading: false,
      error: null,
      success: false,
    };
  },
  methods: {
    handleFileChange(e) {
      const selectedFile = e.target.files[0];
      if (selectedFile) {
        if (selectedFile.type !== 'application/pdf') {
          this.error = 'Only PDF files are allowed';
          return;
        }
        if (selectedFile.size > 10 * 1024 * 1024) {
          this.error = 'File size must be less than 10MB';
          return;
        }
        this.file = selectedFile;
        this.error = null;
      }
    },
    async handleSubmit() {
      if (!this.file) {
        this.error = 'Please select a CV file';
        return;
      }

      this.loading = true;
      this.error = null;
      this.success = false;

      try {
        const formData = new FormData();
        formData.append('cv', this.file);

        const token = localStorage.getItem('token');
        const endpoint = this.isProfile
          ? '/api/instructor/profile'
          : `/api/training-center/instructors/${this.instructorId}`;

        const response = await axios.put(endpoint, formData, {
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'multipart/form-data',
          },
        });

        const cvUrl = this.isProfile
          ? response.data.profile.cv_url
          : response.data.instructor.cv_url;

        if (cvUrl) {
          this.success = true;
          this.file = null;
          this.$emit('cv-updated', cvUrl);
        }
      } catch (err) {
        if (err.response) {
          this.error = err.response.data.message || 'Failed to update CV';
        } else {
          this.error = 'Network error. Please try again.';
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

### Vanilla JavaScript Example

```javascript
async function updateInstructorCV(instructorId, file, isProfile = false) {
  // Validate file
  if (!file) {
    throw new Error('Please select a CV file');
  }
  if (file.type !== 'application/pdf') {
    throw new Error('Only PDF files are allowed');
  }
  if (file.size > 10 * 1024 * 1024) {
    throw new Error('File size must be less than 10MB');
  }

  const formData = new FormData();
  formData.append('cv', file);

  const token = localStorage.getItem('token');
  const endpoint = isProfile
    ? '/api/instructor/profile'
    : `/api/training-center/instructors/${instructorId}`;

  try {
    const response = await fetch(endpoint, {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${token}`,
        // Don't set Content-Type header - browser will set it with boundary
      },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Failed to update CV');
    }

    const cvUrl = isProfile
      ? data.profile.cv_url
      : data.instructor.cv_url;

    return { success: true, cvUrl, data };
  } catch (error) {
    throw error;
  }
}

// Usage
const fileInput = document.getElementById('cv-input');
fileInput.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (file) {
    try {
      const result = await updateInstructorCV(1, file, false);
      console.log('CV updated:', result.cvUrl);
      alert('CV updated successfully!');
    } catch (error) {
      console.error('Error:', error);
      alert(error.message);
    }
  }
});
```

## Important Notes

### 1. File Requirements
- **File Type:** PDF only (`application/pdf`)
- **Max Size:** 10MB (10,240 KB)
- **Validation:** Frontend should validate before upload, but backend also validates

### 2. Request Format
- **Content-Type:** `multipart/form-data` (automatically set by browser/axios)
- **Field Name:** `cv` (must match exactly)
- **Method:** `PUT`

### 3. Response Handling
- Always check for `cv_url` in the response to confirm successful update
- The old CV file is automatically deleted when a new one is uploaded
- The `cv_url` in the response is the full URL to access the CV file

### 4. Error Handling
- Network errors should be handled gracefully
- Display user-friendly error messages
- In production, detailed error messages are hidden (only shown in debug mode)

### 5. CV File Access
- The CV file can be accessed via the URL returned in `cv_url`
- URL format: `https://yourdomain.com/api/storage/instructors/cv/{filename}`
- This is a public endpoint (no authentication required to view)

### 6. File Naming
- Files are automatically renamed with timestamp and training center ID
- Format: `{timestamp}_{training_center_id}_{original_filename}`
- Special characters in original filename are sanitized

## Testing Checklist

- [ ] Upload PDF file (should succeed)
- [ ] Upload non-PDF file (should fail with validation error)
- [ ] Upload file larger than 10MB (should fail with validation error)
- [ ] Update CV from training center (should work)
- [ ] Update CV from instructor profile (should work)
- [ ] Verify old CV is deleted when new one is uploaded
- [ ] Verify `cv_url` is returned in response
- [ ] Verify CV file is accessible via returned URL
- [ ] Test error handling (network errors, server errors)
- [ ] Test with special characters in filename (should be sanitized)

## Migration Notes

No database migrations are required. This is a code fix only.

## Support

If you encounter any issues:
1. Check browser console for errors
2. Check network tab for request/response details
3. Verify authentication token is valid
4. Verify file meets requirements (PDF, < 10MB)
5. Check server logs for detailed error messages

