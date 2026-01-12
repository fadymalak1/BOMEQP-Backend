# Certificate Generation Fixes

## Overview
This document describes the fixes applied to resolve issues with certificate template creation, PDF generation, and preview functionality.

---

## Issues Fixed

### 1. Background Image Upload Not Working

#### Problem
When trying to upload a background image during template creation, the upload was not working. Only `background_image_url` (direct URL) was working.

#### Root Cause
The code was correct, but there was no proper error handling or validation feedback when file uploads failed.

#### Solution
- Added comprehensive error handling for file uploads
- Added file validation before processing
- Added detailed error logging
- Improved error messages returned to frontend

#### Code Changes
- Enhanced `store()` method in `CertificateTemplateController` with try-catch blocks
- Enhanced `update()` method with file validation
- Added proper error responses for invalid files

#### Frontend Requirements
When uploading a background image, ensure:
1. Use `multipart/form-data` content type (not `application/json`)
2. Send `background_image` as a file field in FormData
3. Send other fields (like `template_config`) as JSON string in FormData or as separate fields

**Example:**
```javascript
const formData = new FormData();
formData.append('background_image', fileInput.files[0]);
formData.append('name', 'Template Name');
formData.append('category_id', '1');
formData.append('status', 'active');
formData.append('template_config', JSON.stringify({
  layout: { orientation: 'landscape' },
  title: { show: true, text: 'Certificate' }
}));

fetch('/acc/certificate-templates', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token
    // Don't set Content-Type - browser will set it with boundary
  },
  body: formData
});
```

---

### 2. PDF Generated on 2 Pages Instead of 1

#### Problem
Generated PDF certificates were appearing on two pages instead of one, causing layout issues and content splitting.

#### Root Cause
- CSS page-break properties were not strong enough
- Some elements didn't have page-break prevention
- HTML/body dimensions were not properly constrained

#### Solution
- Enhanced CSS with stronger page-break prevention
- Added `page-break-inside: avoid !important` to all elements
- Set exact dimensions for html and body elements
- Added `overflow: hidden` to prevent content overflow
- Improved certificate container CSS

#### CSS Improvements
```css
@page {
    size: A4 landscape;
    margin: 0;
    padding: 0;
}

* {
    page-break-inside: avoid;
    break-inside: avoid;
}

html {
    width: 297mm;
    height: 210mm;
    overflow: hidden;
}

body {
    width: 297mm;
    height: 210mm;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: avoid !important;
    page-break-before: avoid !important;
}

.certificate {
    width: 297mm;
    height: 210mm;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: avoid !important;
    page-break-before: avoid !important;
    overflow: hidden;
}
```

#### Technical Details
- All child elements now have `page-break-inside: avoid`
- Certificate container uses exact A4 dimensions based on orientation
- Added logging to detect multi-page PDFs (for debugging)

---

### 3. Preview Not Matching Generated PDF

#### Problem
The preview shown in the frontend (when creating/editing templates) did not match the actual PDF generated from certificates.

#### Root Cause
- Preview and PDF generation were using slightly different code paths
- Background image URL handling was inconsistent
- CSS differences between preview HTML and PDF HTML

#### Solution
- Unified HTML generation code between preview and PDF
- Both now use the same `generateHtmlFromConfig()` method
- Consistent background image URL handling (converted to absolute URLs)
- Same CSS applied to both preview and PDF

#### Code Unification
- `CertificateTemplateController::preview()` now uses `generateHtmlFromConfig()`
- `CertificatePdfService::prepareHtml()` uses the same `generateHtmlFromConfig()`
- Background image URLs are converted to absolute URLs in both cases

#### Result
Preview HTML now matches PDF HTML exactly, ensuring what you see in the preview is what you get in the PDF.

---

## Testing Checklist

### Background Image Upload
- [ ] Upload image file via `background_image` field works
- [ ] Error message shown for invalid file types
- [ ] Error message shown for files exceeding size limit
- [ ] Old image deleted when updating template with new image
- [ ] `background_image_url` still works for direct URLs

### PDF Single Page
- [ ] PDF generates on single page (landscape)
- [ ] PDF generates on single page (portrait)
- [ ] Content fits within page boundaries
- [ ] No content split across pages
- [ ] All text visible and properly formatted

### Preview Matching PDF
- [ ] Preview HTML matches PDF output exactly
- [ ] Background images display correctly in both
- [ ] Text alignment matches between preview and PDF
- [ ] Font sizes and colors match
- [ ] Layout matches (spacing, margins, padding)

---

## API Changes

### Background Image Upload

**Endpoint:** `POST /acc/certificate-templates` or `PUT /acc/certificate-templates/{id}`

**Request Format:** `multipart/form-data`

**Fields:**
- `background_image` (file) - Image file to upload
- `background_image_url` (string) - Direct URL to image (alternative to upload)
- `template_config` (JSON string) - Template configuration
- Other template fields...

**Response (Success):**
```json
{
  "message": "Template created successfully",
  "template": {
    "id": 1,
    "background_image_url": "/storage/certificate-templates/backgrounds/abc123.jpg",
    ...
  }
}
```

**Response (Error):**
```json
{
  "message": "Failed to upload background image",
  "error": "File size exceeds maximum allowed size"
}
```

---

## Frontend Implementation Guide

### Uploading Background Image

```javascript
// Using FormData for file upload
const uploadTemplate = async (templateData, imageFile) => {
  const formData = new FormData();
  
  // Add file if provided
  if (imageFile) {
    formData.append('background_image', imageFile);
  } else if (templateData.background_image_url) {
    formData.append('background_image_url', templateData.background_image_url);
  }
  
  // Add other fields
  formData.append('name', templateData.name);
  formData.append('category_id', templateData.category_id);
  formData.append('status', templateData.status);
  formData.append('template_config', JSON.stringify(templateData.template_config));
  
  const response = await fetch('/acc/certificate-templates', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
      // Don't set Content-Type - browser sets it automatically
    },
    body: formData
  });
  
  return await response.json();
};
```

### Preview Display

```javascript
// Preview should match PDF exactly
const previewTemplate = async (templateId, sampleData) => {
  const response = await fetch(`/acc/certificate-templates/${templateId}/preview`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      sample_data: sampleData
    })
  });
  
  const data = await response.json();
  
  // Display in iframe with exact dimensions
  const iframe = document.getElementById('preview-iframe');
  iframe.style.width = '297mm'; // Landscape
  iframe.style.height = '210mm';
  iframe.srcdoc = data.html;
};
```

---

## Troubleshooting

### Background Image Not Uploading

**Check:**
1. Request uses `multipart/form-data` (not `application/json`)
2. File field name is `background_image` (exact match)
3. File size is under 5MB
4. File type is JPEG, PNG, JPG, or GIF
5. Check server logs for detailed error messages

**Common Issues:**
- Using JSON instead of FormData → Convert to FormData
- Wrong field name → Use `background_image` exactly
- File too large → Compress image or increase limit
- Invalid file type → Convert to supported format

### PDF Still on Multiple Pages

**Check:**
1. Content fits within A4 dimensions (297mm x 210mm landscape)
2. Font sizes are reasonable
3. Padding/margins are not excessive
4. Check browser console for CSS warnings

**Solutions:**
- Reduce font sizes if content is too large
- Reduce padding/margins
- Check that all elements have `page-break-inside: avoid`
- Verify certificate container has exact dimensions

### Preview Doesn't Match PDF

**Check:**
1. Both use same `template_config`
2. Background image URLs are absolute (not relative)
3. Same sample data used in preview and certificate generation
4. Browser CSS doesn't override certificate CSS

**Solutions:**
- Ensure preview uses same config as PDF
- Convert relative URLs to absolute
- Use iframe with isolated CSS for preview
- Check that no external CSS interferes

---

## Migration Notes

### For Existing Templates
- Existing templates will continue to work
- Background images uploaded before fix may need re-uploading
- PDFs generated before fix may still have 2-page issue (regenerate)

### For Frontend Developers
- Update file upload code to use FormData
- Ensure preview uses same dimensions as PDF
- Test preview matches PDF output exactly

---

## Support

For issues or questions regarding these fixes, please:
1. Check server logs for detailed error messages
2. Verify request format matches documentation
3. Test with sample data first
4. Contact development team with specific error messages

