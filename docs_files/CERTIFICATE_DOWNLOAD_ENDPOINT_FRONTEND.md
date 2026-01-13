# Certificate Download Endpoint - Frontend Update

## Overview

A new download endpoint has been added to allow training centers to download certificate files. This endpoint provides a secure way to download certificates with proper authentication and file serving.

## New Endpoint

### Download Certificate

**Endpoint**: `GET /api/training-center/certificates/{id}/download`

**Authentication**: Required (Sanctum token)

**Description**: Downloads the certificate PDF/image file for a specific certificate. Only certificates belonging to the authenticated training center can be downloaded.

**Parameters**:
- `id` (path, required, integer): The ID of the certificate to download

**Response** (Success - 200):
- Content-Type: File content (image/png, image/jpeg, application/pdf, etc.)
- Content-Disposition: Attachment with filename
- Body: Binary file data

**Error Responses**:
- `401`: Unauthenticated
- `403`: Certificate does not belong to this training center
- `404`: Certificate or file not found
- `500`: Server error

## Implementation Notes

### Using Axios/Fetch

Since this endpoint returns a file (binary data), you need to handle it differently than JSON responses:

**With Axios:**
```javascript
// Set responseType to 'blob' for file downloads
const response = await axios.get(
  `/api/training-center/certificates/${certificateId}/download`,
  {
    responseType: 'blob', // Important: tells Axios to handle binary data
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);

// Create a blob URL and trigger download
const url = window.URL.createObjectURL(new Blob([response.data]));
const link = document.createElement('a');
link.href = url;
link.setAttribute('download', `certificate-${certificateId}.png`); // or .pdf
document.body.appendChild(link);
link.click();
link.remove();
window.URL.revokeObjectURL(url);
```

**With Fetch:**
```javascript
const response = await fetch(
  `/api/training-center/certificates/${certificateId}/download`,
  {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);

if (!response.ok) {
  throw new Error('Download failed');
}

const blob = await response.blob();
const url = window.URL.createObjectURL(blob);
const link = document.createElement('a');
link.href = url;
link.setAttribute('download', `certificate-${certificateId}.png`);
document.body.appendChild(link);
link.click();
link.remove();
window.URL.revokeObjectURL(url);
```

### Error Handling

The endpoint may return JSON error responses (for 403, 404, 500), so handle both JSON and blob responses:

```javascript
try {
  const response = await axios.get(
    `/api/training-center/certificates/${certificateId}/download`,
    {
      responseType: 'blob',
      headers: {
        'Authorization': `Bearer ${token}`
      }
    }
  );

  // Check if response is actually an error (JSON error responses)
  if (response.data.type === 'application/json') {
    const errorData = JSON.parse(await response.data.text());
    throw new Error(errorData.message || 'Download failed');
  }

  // Handle file download
  const url = window.URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', `certificate-${certificateId}.png`);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);

} catch (error) {
  if (error.response) {
    // Handle HTTP error responses
    if (error.response.data instanceof Blob) {
      const errorText = await error.response.data.text();
      const errorJson = JSON.parse(errorText);
      console.error('Download error:', errorJson.message);
    } else {
      console.error('Download error:', error.response.data.message);
    }
  } else {
    console.error('Download error:', error.message);
  }
}
```

### Alternative: Direct URL Access

Instead of using the download endpoint, you can also access the certificate file directly using the `certificate_pdf_url` from the certificate object. However, the download endpoint is recommended because it:

1. Ensures proper authentication
2. Validates certificate ownership
3. Provides better error handling
4. Allows for future features (analytics, access control, etc.)

If you choose to use the direct URL, make sure the URL is accessible and includes the authentication token if required.

## File Format

Certificates are currently generated as PNG images. The file extension should be `.png` when saving the file, unless the API response indicates otherwise.

## Changes from Previous Implementation

### What's New

- **Download Endpoint**: New dedicated endpoint for downloading certificates
- **Secure Access**: Ensures only the certificate owner (training center) can download
- **Proper File Serving**: Handles file serving with correct content types

### What Stayed the Same

- Certificate file format (PNG)
- File storage location
- Certificate URL structure (still available in `certificate_pdf_url` field)

## Testing

When implementing this feature, test the following scenarios:

- [ ] Successful download for valid certificate
- [ ] Error handling for non-existent certificate (404)
- [ ] Error handling for unauthorized access (403)
- [ ] Error handling for unauthenticated requests (401)
- [ ] File download triggers correctly in browser
- [ ] Downloaded file opens correctly (PNG viewer)
- [ ] File has correct filename
- [ ] Loading states during download
- [ ] Error messages display correctly to user

## Example Implementation

Here's a complete example function for downloading certificates:

```javascript
const downloadCertificate = async (certificateId) => {
  try {
    // Show loading state
    setLoading(true);

    const response = await axios.get(
      `/api/training-center/certificates/${certificateId}/download`,
      {
        responseType: 'blob',
        headers: {
          'Authorization': `Bearer ${authToken}`
        }
      }
    );

    // Check if response is an error (JSON error responses have type 'application/json')
    if (response.data.type === 'application/json') {
      const errorText = await new Response(response.data).text();
      const errorJson = JSON.parse(errorText);
      throw new Error(errorJson.message || 'Download failed');
    }

    // Create download link
    const blob = new Blob([response.data]);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `certificate-${certificateId}.png`);
    document.body.appendChild(link);
    link.click();
    
    // Cleanup
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Show success message
    setSuccessMessage('Certificate downloaded successfully');

  } catch (error) {
    // Handle errors
    if (error.response) {
      if (error.response.data instanceof Blob) {
        const errorText = await new Response(error.response.data).text();
        try {
          const errorJson = JSON.parse(errorText);
          setErrorMessage(errorJson.message || 'Download failed');
        } catch {
          setErrorMessage('Download failed');
        }
      } else {
        setErrorMessage(error.response.data?.message || 'Download failed');
      }
    } else {
      setErrorMessage(error.message || 'Download failed');
    }
  } finally {
    setLoading(false);
  }
};
```

## Notes

- The endpoint uses `response()->download()` which triggers a browser download
- File names are automatically generated by the server
- The endpoint validates that the certificate belongs to the requesting training center
- Certificates are stored as PNG files but the endpoint handles any file type
- For better UX, consider showing a loading spinner during download
- Consider adding a success notification after download completes

