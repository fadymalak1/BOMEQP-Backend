# ACC Profile Documents API - Documentation

## Overview

The ACC Profile API now includes document management functionality. ACC admins can upload, view, and update their profile documents through the profile endpoints.

## What Changed?

### Before
- Profile endpoint returned basic ACC information
- No document management capability
- Documents had to be managed separately

### After
- Profile endpoint includes `documents` array
- Documents can be uploaded via PUT endpoint
- Documents can be updated (replace file or update type)
- Documents include verification status

## API Endpoints

### 1. Get ACC Profile (with Documents)

**Endpoint**: `GET /api/acc/profile`

**Description**: Get the authenticated ACC's profile information including all uploaded documents.

**Authentication**: Required (ACC Admin)

**Response (200 OK)**:
```json
{
    "profile": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "legal_name": "ABC Accreditation Body LLC",
        "registration_number": "REG123456",
        "email": "info@example.com",
        "phone": "+1234567890",
        "country": "Egypt",
        "address": "123 Main St, Cairo",
        "website": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "status": "active",
        "commission_percentage": 10.00,
        "stripe_account_id": "acct_xxxxxxxxxxxxx",
        "stripe_account_configured": true,
        "documents": [
            {
                "id": 1,
                "document_type": "license",
                "document_url": "https://example.com/storage/accs/1/documents/abc123def456.pdf",
                "uploaded_at": "2024-01-15T10:00:00.000000Z",
                "verified": false,
                "verified_by": null,
                "verified_at": null,
                "created_at": "2024-01-15T10:00:00.000000Z",
                "updated_at": "2024-01-15T10:00:00.000000Z"
            },
            {
                "id": 2,
                "document_type": "registration",
                "document_url": "https://example.com/storage/accs/1/documents/xyz789ghi012.pdf",
                "uploaded_at": "2024-01-16T14:30:00.000000Z",
                "verified": true,
                "verified_by": {
                    "id": 5,
                    "name": "Admin User",
                    "email": "admin@example.com"
                },
                "verified_at": "2024-01-17T09:15:00.000000Z",
                "created_at": "2024-01-16T14:30:00.000000Z",
                "updated_at": "2024-01-17T09:15:00.000000Z"
            }
        ],
        "user": {
            "id": 10,
            "name": "ABC Accreditation Body",
            "email": "info@example.com",
            "role": "acc_admin",
            "status": "active"
        },
        "created_at": "2024-01-01T08:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
    }
}
```

### 2. Update ACC Profile (with Document Upload)

**Endpoint**: `PUT /api/acc/profile`

**Description**: Update the authenticated ACC's profile information. Supports both JSON and multipart/form-data for document uploads.

**Authentication**: Required (ACC Admin)

**Content-Type**: 
- `application/json` (for profile fields only)
- `multipart/form-data` (for profile fields + document uploads)

**Request Body (JSON)**:
```json
{
    "name": "Updated ACC Name",
    "phone": "+9876543210",
    "country": "United States",
    "address": "456 New St",
    "website": "https://newwebsite.com",
    "stripe_account_id": "acct_newaccount123"
}
```

**Request Body (Multipart/Form-Data)**:
```
name: Updated ACC Name
phone: +9876543210
documents[0][document_type]: license
documents[0][file]: [binary file data]
documents[1][id]: 2
documents[1][document_type]: certificate
documents[1][file]: [binary file data]
```

**Request Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | No | ACC name |
| `legal_name` | string | No | Legal name |
| `phone` | string | No | Phone number |
| `country` | string | No | Country |
| `address` | string | No | Address |
| `website` | string | No | Website URL |
| `logo_url` | string | No | Logo URL |
| `stripe_account_id` | string | No | Stripe Connect account ID (must start with "acct_") |
| `documents` | array | No | Array of documents to upload/update |

**Document Object Structure**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | No | Document ID (required for update) |
| `document_type` | string | Yes | Type: `license`, `registration`, `certificate`, `other` |
| `file` | file | No | File to upload (PDF, JPG, JPEG, PNG, max 10MB) |

**Validation Rules**:
- `name`: max 255 characters
- `legal_name`: max 255 characters
- `phone`: max 255 characters
- `country`: max 255 characters
- `website`: valid URL, max 255 characters
- `logo_url`: valid URL, max 255 characters
- `stripe_account_id`: must match pattern `^acct_[a-zA-Z0-9]+$`
- `documents.*.document_type`: must be one of: `license`, `registration`, `certificate`, `other`
- `documents.*.file`: must be PDF, JPG, JPEG, or PNG, max 10MB

**Response (200 OK)**:
```json
{
    "message": "Profile updated successfully",
    "profile": {
        "id": 1,
        "name": "Updated ACC Name",
        "legal_name": "ABC Accreditation Body LLC",
        "registration_number": "REG123456",
        "email": "info@example.com",
        "phone": "+9876543210",
        "country": "United States",
        "address": "456 New St",
        "website": "https://newwebsite.com",
        "logo_url": "https://example.com/logo.png",
        "status": "active",
        "commission_percentage": 10.00,
        "stripe_account_id": "acct_newaccount123",
        "stripe_account_configured": true,
        "documents": [
            {
                "id": 1,
                "document_type": "license",
                "document_url": "https://example.com/storage/accs/1/documents/newfile123.pdf",
                "uploaded_at": "2024-01-20T10:00:00.000000Z",
                "verified": false,
                "verified_by": null,
                "verified_at": null,
                "created_at": "2024-01-15T10:00:00.000000Z",
                "updated_at": "2024-01-20T10:00:00.000000Z"
            },
            {
                "id": 2,
                "document_type": "certificate",
                "document_url": "https://example.com/storage/accs/1/documents/updatedfile456.pdf",
                "uploaded_at": "2024-01-20T10:00:00.000000Z",
                "verified": false,
                "verified_by": null,
                "verified_at": null,
                "created_at": "2024-01-16T14:30:00.000000Z",
                "updated_at": "2024-01-20T10:00:00.000000Z"
            }
        ],
        "user": {
            "id": 10,
            "name": "Updated ACC Name",
            "email": "info@example.com",
            "role": "acc_admin",
            "status": "active"
        },
        "created_at": "2024-01-01T08:00:00.000000Z",
        "updated_at": "2024-01-20T10:00:00.000000Z"
    }
}
```

## Document Management Scenarios

### 1. Upload New Document

**Request**:
```javascript
const formData = new FormData();
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', fileInput.files[0]);

fetch('/api/acc/profile', {
    method: 'PUT',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
});
```

**Result**: New document created with `verified: false`

### 2. Update Existing Document (Replace File)

**Request**:
```javascript
const formData = new FormData();
formData.append('documents[0][id]', 1); // Existing document ID
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', newFile);

fetch('/api/acc/profile', {
    method: 'PUT',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
});
```

**Result**: 
- Old file deleted
- New file uploaded
- Document verification reset to `false`
- `uploaded_at` updated

### 3. Update Document Type Only (No File Upload)

**Request**:
```json
{
    "documents": [
        {
            "id": 1,
            "document_type": "certificate"
        }
    ]
}
```

**Result**: Document type updated, file remains unchanged

### 4. Upload Multiple Documents

**Request**:
```javascript
const formData = new FormData();
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', file1);
formData.append('documents[1][document_type]', 'registration');
formData.append('documents[1][file]', file2);
formData.append('documents[2][document_type]', 'certificate');
formData.append('documents[2][file]', file3);
```

**Result**: All three documents uploaded

### 5. Mixed Operations (Update + Upload)

**Request**:
```javascript
const formData = new FormData();
// Update existing document
formData.append('documents[0][id]', 1);
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', updatedFile);
// Upload new document
formData.append('documents[1][document_type]', 'registration');
formData.append('documents[1][file]', newFile);
```

**Result**: Document 1 updated, Document 2 created

## Frontend Implementation

### React Example

```jsx
import { useState } from 'react';

function ACCProfileDocuments() {
    const [profile, setProfile] = useState(null);
    const [files, setFiles] = useState([]);

    // Fetch profile with documents
    const fetchProfile = async () => {
        const response = await fetch('/api/acc/profile', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        setProfile(data.profile);
    };

    // Upload documents
    const uploadDocuments = async () => {
        const formData = new FormData();
        
        files.forEach((file, index) => {
            formData.append(`documents[${index}][document_type]`, file.type);
            formData.append(`documents[${index}][file]`, file.file);
        });

        const response = await fetch('/api/acc/profile', {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });

        const data = await response.json();
        setProfile(data.profile);
    };

    // Update document
    const updateDocument = async (documentId, newFile, documentType) => {
        const formData = new FormData();
        formData.append('documents[0][id]', documentId);
        formData.append('documents[0][document_type]', documentType);
        formData.append('documents[0][file]', newFile);

        const response = await fetch('/api/acc/profile', {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });

        const data = await response.json();
        setProfile(data.profile);
    };

    return (
        <div>
            <h2>ACC Profile Documents</h2>
            
            {/* Display Documents */}
            {profile?.documents?.map(document => (
                <div key={document.id} className="document-card">
                    <h3>{document.document_type}</h3>
                    <p>Uploaded: {new Date(document.uploaded_at).toLocaleDateString()}</p>
                    <p>Status: {document.verified ? 'Verified' : 'Pending Verification'}</p>
                    <a href={document.document_url} target="_blank" rel="noopener noreferrer">
                        View Document
                    </a>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(e) => {
                            if (e.target.files[0]) {
                                updateDocument(document.id, e.target.files[0], document.document_type);
                            }
                        }}
                    />
                </div>
            ))}

            {/* Upload New Document */}
            <div className="upload-section">
                <h3>Upload New Document</h3>
                <select id="docType">
                    <option value="license">License</option>
                    <option value="registration">Registration</option>
                    <option value="certificate">Certificate</option>
                    <option value="other">Other</option>
                </select>
                <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    onChange={(e) => {
                        if (e.target.files[0]) {
                            const formData = new FormData();
                            formData.append('documents[0][document_type]', document.getElementById('docType').value);
                            formData.append('documents[0][file]', e.target.files[0]);
                            
                            fetch('/api/acc/profile', {
                                method: 'PUT',
                                headers: {
                                    'Authorization': `Bearer ${token}`
                                },
                                body: formData
                            }).then(res => res.json())
                              .then(data => setProfile(data.profile));
                        }
                    }}
                />
            </div>
        </div>
    );
}
```

### Vue Example

```vue
<template>
    <div class="acc-profile-documents">
        <h2>ACC Profile Documents</h2>
        
        <!-- Display Documents -->
        <div v-for="document in profile?.documents" :key="document.id" class="document-card">
            <h3>{{ document.document_type }}</h3>
            <p>Uploaded: {{ formatDate(document.uploaded_at) }}</p>
            <p>Status: {{ document.verified ? 'Verified' : 'Pending Verification' }}</p>
            <a :href="document.document_url" target="_blank">View Document</a>
            <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                @change="updateDocument(document.id, $event, document.document_type)"
            />
        </div>

        <!-- Upload New Document -->
        <div class="upload-section">
            <h3>Upload New Document</h3>
            <select v-model="newDocumentType">
                <option value="license">License</option>
                <option value="registration">Registration</option>
                <option value="certificate">Certificate</option>
                <option value="other">Other</option>
            </select>
            <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                @change="uploadNewDocument($event)"
            />
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            profile: null,
            newDocumentType: 'license'
        };
    },
    mounted() {
        this.fetchProfile();
    },
    methods: {
        async fetchProfile() {
            const response = await fetch('/api/acc/profile', {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });
            const data = await response.json();
            this.profile = data.profile;
        },
        async updateDocument(documentId, event, documentType) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('documents[0][id]', documentId);
            formData.append('documents[0][document_type]', documentType);
            formData.append('documents[0][file]', file);

            const response = await fetch('/api/acc/profile', {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${this.token}`
                },
                body: formData
            });

            const data = await response.json();
            this.profile = data.profile;
        },
        async uploadNewDocument(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('documents[0][document_type]', this.newDocumentType);
            formData.append('documents[0][file]', file);

            const response = await fetch('/api/acc/profile', {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${this.token}`
                },
                body: formData
            });

            const data = await response.json();
            this.profile = data.profile;
        },
        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString();
        }
    }
}
</script>
```

### JavaScript/Fetch Example

```javascript
// Get profile with documents
async function getACCProfile() {
    const response = await fetch('/api/acc/profile', {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    const data = await response.json();
    return data.profile;
}

// Upload new document
async function uploadDocument(documentType, file) {
    const formData = new FormData();
    formData.append('documents[0][document_type]', documentType);
    formData.append('documents[0][file]', file);

    const response = await fetch('/api/acc/profile', {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });

    return await response.json();
}

// Update existing document
async function updateDocument(documentId, documentType, file) {
    const formData = new FormData();
    formData.append('documents[0][id]', documentId);
    formData.append('documents[0][document_type]', documentType);
    formData.append('documents[0][file]', file);

    const response = await fetch('/api/acc/profile', {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });

    return await response.json();
}

// Update document type only (no file upload)
async function updateDocumentType(documentId, documentType) {
    const response = await fetch('/api/acc/profile', {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            documents: [
                {
                    id: documentId,
                    document_type: documentType
                }
            ]
        })
    });

    return await response.json();
}
```

## TypeScript Types

```typescript
interface ACCDocument {
    id: number;
    document_type: 'license' | 'registration' | 'certificate' | 'other';
    document_url: string;
    uploaded_at: string;
    verified: boolean;
    verified_by: {
        id: number;
        name: string;
        email: string;
    } | null;
    verified_at: string | null;
    created_at: string;
    updated_at: string;
}

interface ACCProfile {
    id: number;
    name: string;
    legal_name: string;
    registration_number: string;
    email: string;
    phone: string;
    country: string;
    address: string;
    website: string | null;
    logo_url: string | null;
    status: string;
    commission_percentage: number;
    stripe_account_id: string | null;
    stripe_account_configured: boolean;
    documents: ACCDocument[];
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        status: string;
    } | null;
    created_at: string;
    updated_at: string;
}

interface ProfileResponse {
    profile: ACCProfile;
}

interface UpdateProfileResponse {
    message: string;
    profile: ACCProfile;
}
```

## File Storage

### Storage Location
Documents are stored in: `storage/app/public/accs/{acc_id}/documents/`

### File Access
Documents can be accessed via:
- Direct URL: `https://yourdomain.com/storage/accs/{acc_id}/documents/{filename}`
- API Route: `GET /api/storage/accs/{acc_id}/documents/{filename}`

### File Naming
Files are automatically renamed with a random 20-character string + original extension:
- Original: `license.pdf`
- Stored: `abc123def456ghi789.pdf`

## Document Verification

Documents uploaded by ACC admins start with `verified: false`. Verification is handled by Group Admin or ACC Admin through separate endpoints (not part of this API).

When a document is updated (file replaced):
- `verified` is reset to `false`
- `verified_by` is set to `null`
- `verified_at` is set to `null`
- `uploaded_at` is updated to current timestamp

## Error Responses

### 401 Unauthorized
```json
{
    "message": "Unauthenticated."
}
```

### 404 Not Found
```json
{
    "message": "ACC not found"
}
```

### 422 Validation Error
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "documents.0.document_type": [
            "The documents.0.document_type field is required."
        ],
        "documents.0.file": [
            "The documents.0.file must be a file.",
            "The documents.0.file must not be greater than 10240 kilobytes."
        ]
    }
}
```

## Notes

1. **File Size Limit**: Maximum file size is 10MB (10240 KB)

2. **Allowed File Types**: PDF, JPG, JPEG, PNG only

3. **Document Types**: Must be one of: `license`, `registration`, `certificate`, `other`

4. **Old File Deletion**: When updating a document with a new file, the old file is automatically deleted from storage

5. **Verification Reset**: Updating a document resets its verification status

6. **Multiple Documents**: You can upload multiple documents in a single request

7. **Mixed Operations**: You can update existing documents and upload new ones in the same request

8. **Profile Fields**: Profile fields (name, phone, etc.) can be updated alongside documents in the same request

## Summary

✅ **Documents in GET Response** - Profile includes all documents  
✅ **Document Upload** - Upload new documents via PUT endpoint  
✅ **Document Update** - Update existing documents (file or type)  
✅ **File Management** - Automatic file storage and cleanup  
✅ **Verification Status** - Track document verification state  
✅ **Multiple Documents** - Support for multiple document uploads  
✅ **Mixed Operations** - Update profile and documents together  

The ACC Profile API now provides complete document management functionality integrated with profile updates.

