# Certificate Validity Check & Download Fix - Frontend Update

## Overview

Two updates have been made to the certificate system:
1. **Download endpoint improvements** - Better error handling and URL parsing
2. **New validity check endpoint** - Allows training centers to check certificate validity

## New Endpoint: Check Certificate Validity

### Endpoint Details

**Endpoint**: `GET /api/training-center/certificates/{id}/validity`

**Authentication**: Required (Sanctum token)

**Description**: Checks if a certificate is valid. Returns certificate status, validity information, and certificate details. This endpoint also automatically updates the certificate status to "expired" if the expiry date has passed.

**Parameters**:
- `id` (path, required, integer): The ID of the certificate to check

**Response** (Success - 200):
```json
{
  "valid": true,
  "status": "valid",
  "message": "Certificate is valid",
  "certificate": {
    "id": 99,
    "certificate_number": "CERT-2024-ABC123",
    "verification_code": "VERIFY-XYZ789",
    "trainee_name": "John Doe",
    "issue_date": "2024-01-15",
    "expiry_date": "2026-01-15",
    "status": "valid",
    "course": {
      "id": 1,
      "name": "Fire Safety Training"
    },
    "training_center": {
      "id": 1,
      "name": "ABC Training Center"
    }
  }
}
```

**Response Fields**:
- `valid` (boolean): `true` if certificate is valid, `false` otherwise
- `status` (string): Certificate status - "valid", "expired", or "revoked"
- `message` (string): Human-readable message about certificate validity
- `certificate` (object): Certificate details including ID, numbers, dates, and related entities

**Error Responses**:
- `401`: Unauthenticated
- `403`: Certificate does not belong to this training center
- `404`: Certificate not found

**Status Values**:
- `valid`: Certificate is valid and active
- `expired`: Certificate has passed its expiry date (automatically updated)
- `revoked`: Certificate has been revoked

## Download Endpoint Improvements

The download endpoint has been improved with:
- Better URL parsing to handle multiple URL formats
- Improved error handling and logging
- More descriptive error messages

The endpoint behavior remains the same from a frontend perspective, but it should now handle edge cases better.

## Use Cases

### Check Certificate Before Download

You can check certificate validity before allowing download:

```javascript
// Check validity first
const validityResponse = await axios.get(
  `/api/training-center/certificates/${certificateId}/validity`,
  {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);

if (validityResponse.data.valid) {
  // Certificate is valid, proceed with download
  downloadCertificate(certificateId);
} else {
  // Show validity status
  alert(`Certificate is ${validityResponse.data.status}: ${validityResponse.data.message}`);
}
```

### Display Certificate Status

You can use the validity endpoint to display certificate status in a list or detail view:

```javascript
const checkCertificateStatus = async (certificateId) => {
  try {
    const response = await axios.get(
      `/api/training-center/certificates/${certificateId}/validity`
    );
    
    const { valid, status, message } = response.data;
    
    // Display status badge
    return {
      status: status, // 'valid', 'expired', 'revoked'
      isValid: valid,
      message: message,
      badgeColor: valid ? 'green' : status === 'expired' ? 'orange' : 'red'
    };
  } catch (error) {
    console.error('Error checking certificate validity:', error);
    return null;
  }
};
```

### Batch Validity Check

You can check multiple certificates in parallel:

```javascript
const checkMultipleCertificates = async (certificateIds) => {
  const validityChecks = certificateIds.map(id =>
    axios.get(`/api/training-center/certificates/${id}/validity`)
  );
  
  try {
    const responses = await Promise.all(validityChecks);
    return responses.map(response => ({
      id: response.data.certificate.id,
      valid: response.data.valid,
      status: response.data.status
    }));
  } catch (error) {
    console.error('Error checking certificates:', error);
    return [];
  }
};
```

## Implementation Example

Here's a complete example of using the validity check with download:

```javascript
const handleCertificateAction = async (certificateId, action) => {
  try {
    // Check validity first
    const validityResponse = await axios.get(
      `/api/training-center/certificates/${certificateId}/validity`,
      {
        headers: {
          'Authorization': `Bearer ${authToken}`
        }
      }
    );

    const { valid, status, message, certificate } = validityResponse.data;

    if (action === 'download') {
      if (!valid) {
        // Show status message but still allow download attempt
        console.warn(`Certificate is ${status}: ${message}`);
      }
      
      // Proceed with download
      await downloadCertificate(certificateId);
    } else if (action === 'view') {
      // Show certificate details with status
      showCertificateDetails(certificate, status, valid);
    }

  } catch (error) {
    if (error.response?.status === 404) {
      alert('Certificate not found');
    } else if (error.response?.status === 403) {
      alert('You do not have permission to access this certificate');
    } else {
      console.error('Error:', error);
      alert('An error occurred while checking certificate');
    }
  }
};

const downloadCertificate = async (certificateId) => {
  try {
    const response = await axios.get(
      `/api/training-center/certificates/${certificateId}/download`,
      {
        responseType: 'blob',
        headers: {
          'Authorization': `Bearer ${authToken}`
        }
      }
    );

    // Handle file download
    const url = window.URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `certificate-${certificateId}.png`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

  } catch (error) {
    if (error.response?.status === 404) {
      alert('Certificate file not found');
    } else {
      console.error('Download error:', error);
      alert('Failed to download certificate');
    }
  }
};
```

## Status Badge Implementation

Example of displaying certificate status badges:

```javascript
const getStatusBadge = (status, valid) => {
  const badges = {
    valid: { color: 'green', text: 'Valid', icon: '✓' },
    expired: { color: 'orange', text: 'Expired', icon: '⏰' },
    revoked: { color: 'red', text: 'Revoked', icon: '✗' }
  };
  
  const badge = badges[status] || { color: 'gray', text: status, icon: '?' };
  
  return {
    className: `badge badge-${badge.color}`,
    text: badge.text,
    icon: badge.icon
  };
};

// Usage in React component
const CertificateStatusBadge = ({ certificateId }) => {
  const [status, setStatus] = useState(null);
  
  useEffect(() => {
    axios.get(`/api/training-center/certificates/${certificateId}/validity`)
      .then(response => setStatus(response.data))
      .catch(console.error);
  }, [certificateId]);
  
  if (!status) return <span>Loading...</span>;
  
  const badge = getStatusBadge(status.status, status.valid);
  
  return (
    <span className={badge.className}>
      {badge.icon} {badge.text}
    </span>
  );
};
```

## Notes

- The validity endpoint automatically updates expired certificates if the expiry date has passed
- The endpoint checks if the certificate belongs to the requesting training center
- Status is checked based on both the database status field and the expiry_date
- The validity endpoint includes certificate details, so you can use it to display certificate information
- The download endpoint improvements are backward compatible - no changes needed to existing download code

## Testing

When implementing this feature, test:

- [ ] Validity check returns correct status for valid certificates
- [ ] Validity check returns expired status for expired certificates
- [ ] Validity check returns revoked status for revoked certificates
- [ ] Expired certificates are automatically updated in the database
- [ ] Error handling for non-existent certificates (404)
- [ ] Error handling for unauthorized access (403)
- [ ] Error handling for unauthenticated requests (401)
- [ ] Download works correctly after validity check
- [ ] Status badges display correctly
- [ ] Batch validity checks work correctly

## Changes Summary

### What's New
- **Validity Check Endpoint**: New endpoint to check certificate validity
- **Improved Download Endpoint**: Better URL parsing and error handling
- **Automatic Status Updates**: Expired certificates are automatically updated

### What Stayed the Same
- Download endpoint URL and response format
- Certificate data structure
- Authentication requirements

