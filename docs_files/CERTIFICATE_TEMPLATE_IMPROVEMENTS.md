# Certificate Template Improvements Documentation

## Overview
This document describes the improvements made to the certificate template system, including PDF generation fixes, text alignment options, preview functionality, and subtitle text support.

---

## 1. PDF Single Page Fix

### Problem
Previously, generated PDF certificates were appearing on two pages instead of one, causing layout issues.

### Solution
Enhanced CSS styling to prevent page breaks and ensure single-page output:

- Added `page-break-inside: avoid !important` to all certificate elements
- Set precise `width` and `height` for `html` and `body` elements
- Added `overflow: hidden` to prevent content overflow
- Improved page break prevention with `break-inside: avoid !important`

### Technical Details
- Certificate container now uses exact dimensions based on orientation:
  - Landscape: `297mm x 210mm`
  - Portrait: `210mm x 297mm`
- All child elements have `page-break-inside: avoid` to prevent splitting

---

## 2. Text Alignment Options

### New Feature
Added support for additional text alignment options: `right-center` and `left-center`.

### Supported Alignment Values
The system supports **all standard CSS text-align values**:

#### Standard CSS Values
- `left` - Aligns text to the left
- `right` - Aligns text to the right
- `center` - Centers text (default)
- `justify` - Justifies text (spreads text evenly across the width)
- `start` - Aligns text to the start (respects text direction - LTR/RTL)
- `end` - Aligns text to the end (respects text direction - LTR/RTL)
- `initial` - Sets text-align to its default value
- `inherit` - Inherits text-align from parent element

#### Legacy Support (Backward Compatibility)
- `right-center` or `right_center` - Maps to `right`
- `left-center` or `left_center` - Maps to `left`

**Note**: Any unrecognized value will default to `center`.

### Usage in Template Config
You can now specify `text_align` for any text element using any standard CSS value:

```json
{
  "title": {
    "show": true,
    "text": "Certificate of Completion",
    "font_size": "48px",
    "font_weight": "bold",
    "color": "#2c3e50",
    "text_align": "center"
  },
  "trainee_name": {
    "show": true,
    "font_size": "36px",
    "font_weight": "bold",
    "color": "#2c3e50",
    "text_align": "right"
  },
  "course_name": {
    "show": true,
    "font_size": "24px",
    "color": "#34495e",
    "text_align": "justify"
  },
  "subtitle": {
    "show": true,
    "text": "Sample Subtitle",
    "font_size": "18px",
    "color": "#7f8c8d",
    "text_align": "start"
  }
}
```

### Examples of All Alignment Values

```json
{
  "title": {
    "text_align": "center"    // Centers the text
  },
  "trainee_name": {
    "text_align": "left"      // Aligns to the left
  },
  "course_name": {
    "text_align": "right"     // Aligns to the right
  },
  "subtitle": {
    "text_align": "justify"   // Justifies text (spreads evenly)
  },
  "details": {
    "text_align": "start"     // Aligns to start (respects direction)
  },
  "footer": {
    "text_align": "end"       // Aligns to end (respects direction)
  }
}
```

### Supported Elements
All text elements support the `text_align` property with any standard CSS value:
- `title` - Certificate title
- `trainee_name` - Trainee name
- `course_name` - Course name
- `subtitle` - Subtitle text
- `subtitle_before` - Subtitle before trainee name
- `subtitle_after` - Subtitle after trainee name

**Note**: Each element can use any of the supported alignment values (`left`, `right`, `center`, `justify`, `start`, `end`, `initial`, `inherit`).

---

## 3. Preview Functionality

### Problem
The preview endpoint was incomplete and only returned a placeholder URL.

### Solution
Completed the preview functionality to return actual HTML that matches the PDF output exactly.

### API Endpoint
**POST** `/acc/certificate-templates/{id}/preview`

### Request Body
```json
{
  "sample_data": {
    "trainee_name": "John Doe",
    "trainee_id_number": "123456",
    "course_name": "Fire Safety Training",
    "course_code": "FST-001",
    "certificate_number": "CERT-123456",
    "verification_code": "VERIFY123",
    "issue_date": "2026-01-12",
    "expiry_date": "2027-01-12",
    "training_center_name": "ABC Training Center",
    "instructor_name": "Jane Smith",
    "class_name": "Fire Safety Class 2026",
    "acc_name": "Safety Accreditation Board"
  }
}
```

### Response
```json
{
  "html": "<!DOCTYPE html>...",
  "message": "Preview generated successfully"
}
```

### Frontend Implementation
The returned HTML can be displayed directly in an iframe or div:

```javascript
// Example: Display preview in iframe
fetch('/acc/certificate-templates/1/preview', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    sample_data: {
      trainee_name: 'John Doe',
      course_name: 'Fire Safety Training',
      // ... other sample data
    }
  })
})
.then(response => response.json())
.then(data => {
  const iframe = document.getElementById('preview-iframe');
  iframe.srcdoc = data.html;
});
```

### Important Notes
- The preview HTML uses the exact same CSS and structure as the PDF
- All variables are replaced with sample data
- Background images are converted to absolute URLs
- The preview will look identical to the generated PDF

---

## 4. Subtitle Text Support

### New Feature
Added support for customizable subtitle text before and after the trainee name.

### Configuration Options

#### Subtitle Before Trainee Name
```json
{
  "subtitle_before": {
    "show": true,
    "text": "This is to certify that",
    "font_size": "18px",
    "color": "#7f8c8d",
    "text_align": "center"
  }
}
```

#### Subtitle After Trainee Name
```json
{
  "subtitle_after": {
    "show": true,
    "text": "has successfully completed the course",
    "font_size": "18px",
    "color": "#7f8c8d",
    "text_align": "center"
  }
}
```

### Default Values
If `subtitle_before` or `subtitle_after` are not specified in the config:
- **subtitle_before**: Defaults to "This is to certify that"
- **subtitle_after**: Defaults to "has successfully completed the course"

### Properties
- `show` (boolean): Whether to display the subtitle (default: `true`)
- `text` (string): The subtitle text content
- `font_size` (string): Font size (default: `"18px"`)
- `color` (string): Text color (default: `"#7f8c8d"`)
- `text_align` (string): Text alignment - `left`, `right`, `center`, `right-center`, `left-center` (default: `"center"`)

---

## Complete Template Config Example

```json
{
  "layout": {
    "orientation": "landscape",
    "border_color": "#D4AF37",
    "border_width": "15px",
    "background_color": "#ffffff"
  },
  "title": {
    "show": true,
    "text": "Certificate of Completion",
    "font_size": "48px",
    "font_weight": "bold",
    "color": "#2c3e50",
    "text_align": "center"
  },
  "subtitle_before": {
    "show": true,
    "text": "This is to certify that",
    "font_size": "18px",
    "color": "#7f8c8d",
    "text_align": "center"
  },
  "trainee_name": {
    "show": true,
    "font_size": "36px",
    "font_weight": "bold",
    "color": "#2c3e50",
    "text_align": "center"
  },
  "subtitle_after": {
    "show": true,
    "text": "has successfully completed the course",
    "font_size": "18px",
    "color": "#7f8c8d",
    "text_align": "center"
  },
  "course_name": {
    "show": true,
    "font_size": "24px",
    "color": "#34495e",
    "text_align": "center"
  },
  "issue_date": {
    "show": true,
    "font_size": "14px",
    "color": "#7f8c8d"
  },
  "certificate_number": {
    "show": true,
    "font_size": "14px",
    "color": "#7f8c8d"
  },
  "verification_code": {
    "show": true,
    "font_size": "10px",
    "color": "#95a5a6"
  }
}
```

---

## API Changes Summary

### Updated Endpoints

#### 1. Preview Certificate Template
- **Endpoint**: `POST /acc/certificate-templates/{id}/preview`
- **Changes**: Now returns actual HTML instead of placeholder URL
- **Response**: Includes `html` field with complete HTML content

#### 2. Create/Update Certificate Template
- **Endpoint**: `POST /acc/certificate-templates` or `PUT /acc/certificate-templates/{id}`
- **Changes**: Supports new `text_align` options and subtitle configurations
- **New Fields**: 
  - `subtitle_before` (object)
  - `subtitle_after` (object)
  - `text_align` in all text elements (string: `left`, `right`, `center`, `right-center`, `left-center`)

---

## Frontend Implementation Guide

### 1. Creating a Template with Custom Alignment

```javascript
const templateConfig = {
  layout: {
    orientation: 'landscape',
    border_color: '#D4AF37',
    border_width: '15px',
    background_color: '#ffffff'
  },
  title: {
    show: true,
    text: 'Certificate of Completion',
    font_size: '48px',
    font_weight: 'bold',
    color: '#2c3e50',
    text_align: 'center' // Options: left, right, center, right-center, left-center
  },
  subtitle_before: {
    show: true,
    text: 'This is to certify that',
    font_size: '18px',
    color: '#7f8c8d',
    text_align: 'center'
  },
  trainee_name: {
    show: true,
    font_size: '36px',
    font_weight: 'bold',
    color: '#2c3e50',
    text_align: 'right-center' // Right-aligned text
  },
  subtitle_after: {
    show: true,
    text: 'has successfully completed the course',
    font_size: '18px',
    color: '#7f8c8d',
    text_align: 'left-center' // Left-aligned text
  },
  course_name: {
    show: true,
    font_size: '24px',
    color: '#34495e',
    text_align: 'center'
  }
};

// Create template
fetch('/acc/certificate-templates', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    category_id: 1,
    name: 'Custom Certificate Template',
    template_config: templateConfig
  })
});
```

### 2. Preview Template

```javascript
async function previewTemplate(templateId, sampleData) {
  const response = await fetch(`/acc/certificate-templates/${templateId}/preview`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({
      sample_data: sampleData
    })
  });
  
  const data = await response.json();
  
  // Display in iframe
  const iframe = document.getElementById('preview-iframe');
  iframe.srcdoc = data.html;
  
  // Or display in div
  const previewDiv = document.getElementById('preview-div');
  previewDiv.innerHTML = data.html;
}

// Usage
previewTemplate(1, {
  trainee_name: 'John Doe',
  course_name: 'Fire Safety Training',
  certificate_number: 'CERT-123456',
  issue_date: '2026-01-12'
});
```

### 3. Text Alignment Selector Component

```javascript
function TextAlignSelector({ value, onChange }) {
  return (
    <select value={value} onChange={onChange}>
      <option value="center">Center</option>
      <option value="left">Left</option>
      <option value="right">Right</option>
      <option value="justify">Justify</option>
      <option value="start">Start</option>
      <option value="end">End</option>
      <option value="initial">Initial</option>
      <option value="inherit">Inherit</option>
    </select>
  );
}
```

---

## Testing Checklist

- [ ] PDF generates on single page (landscape mode)
- [ ] PDF generates on single page (portrait mode)
- [ ] Text alignment works for all options (left, right, center, right-center, left-center)
- [ ] Preview HTML matches PDF output exactly
- [ ] Subtitle before trainee name displays correctly
- [ ] Subtitle after trainee name displays correctly
- [ ] Custom subtitle text works in preview
- [ ] Background images display correctly in preview
- [ ] All variables are replaced correctly in preview
- [ ] Certificate generation works with new alignment options

---

## Migration Notes

### For Existing Templates
Existing templates using `template_html` will continue to work. However, to take advantage of the new features:

1. Convert templates to use `template_config` instead of `template_html`
2. Add `text_align` properties to text elements as needed
3. Add `subtitle_before` and `subtitle_after` configurations if custom subtitles are desired

### Backward Compatibility
- Templates without `text_align` will default to `center`
- Templates without `subtitle_before` will use default text "This is to certify that"
- Templates without `subtitle_after` will use default text "has successfully completed the course"

---

## Troubleshooting

### PDF Still Appears on Multiple Pages
- Check that certificate content fits within A4 dimensions
- Verify that `page-break-inside: avoid` is applied to all elements
- Reduce font sizes or padding if content is too large

### Preview Not Matching PDF
- Ensure background image URLs are absolute (not relative)
- Check that all variables in `sample_data` are provided
- Verify that `template_config` is properly formatted JSON

### Text Alignment Not Working
- Verify that `text_align` value is one of: `left`, `right`, `center`, `right-center`, `left-center`
- Check that the element has `show: true` in config
- Ensure CSS is properly applied (check browser console)

---

## Support

For issues or questions regarding these improvements, please contact the development team or refer to the main certificate system documentation.

