# Certificate Fonts - Frontend Developer Guide

## Overview

This document lists all fonts that are supported for certificate generation. These fonts are available in the `storage/fonts/` directory on the backend server.

## Supported Fonts

The following fonts are available for use in the certificate designer:

| Font Name (Use in Frontend) | Font Family Value | Status | Notes |
|----------------------------|-------------------|--------|-------|
| **Arial** | `Arial` | ✅ Available | Standard sans-serif font |
| **Helvetica** | `Helvetica` | ✅ Available | Uses Arial on most systems |
| **Times New Roman** | `Times New Roman` | ✅ Available | Standard serif font |
| **Courier New** | `Courier New` | ✅ Available | Monospace font |
| **Verdana** | `Verdana` | ✅ Available | Clean sans-serif font |
| **Georgia** | `Georgia` | ✅ Available | Elegant serif font |
| **Tahoma** | `Tahoma` | ✅ Available | Compact sans-serif font |
| **Trebuchet MS** | `Trebuchet MS` | ✅ Available | Modern sans-serif font |
| **Impact** | `Impact` | ✅ Available | Bold, condensed font |

## Font Usage in Certificate Designer

When creating or editing certificate templates, you can use any of the above fonts by specifying the exact font name in the `fontFamily` property.

### Example Configuration

```javascript
const placeholderConfig = {
  variable: "{{student_name}}",
  x: 0.5,                    // X position (0.0 to 1.0)
  y: 0.4,                    // Y position (0.0 to 1.0)
  font_family: "Arial",      // Use exact font name from table above
  font_size: 48,             // Font size in pixels (8-200)
  color: "#000000",          // Hex color code
  text_align: "center"       // "left", "center", or "right"
};
```

### Font Family Dropdown Options

In your certificate designer UI, you can populate the font family dropdown with these options:

```javascript
const fontFamilies = [
  'Arial',
  'Helvetica',
  'Times New Roman',
  'Courier New',
  'Verdana',
  'Georgia',
  'Tahoma',
  'Trebuchet MS',
  'Impact'
];
```

## Font Properties

### Font Sizes
- **Minimum**: 8 pixels
- **Maximum**: 200 pixels
- **Recommended**: 24-72 pixels for most certificate text

### Text Alignment
- `left` - Align text to the left
- `center` - Center align text
- `right` - Align text to the right

### Colors
- Format: Hex color code (e.g., `#000000` for black, `#FFFFFF` for white)
- Supports full RGB color range

## Font Rendering

### How Fonts Work

1. **Frontend Selection**: Users select a font from the dropdown in the certificate designer
2. **Storage**: The font name is saved in the template configuration (`config_json`)
3. **Backend Rendering**: When a certificate is generated:
   - The backend looks for the font file in `storage/fonts/` or `resources/fonts/`
   - If found, it uses the TrueType font file (.ttf)
   - If not found, it falls back to system fonts or built-in fonts
4. **PDF Generation**: The rendered certificate (with fonts applied) is embedded in a PDF

### Font Matching

The backend will attempt to match the selected font name to available font files. The system checks for:
- Exact filename matches (case-insensitive)
- Common variations (e.g., "Arial" matches "arial.ttf", "Arial.ttf")
- System font fallbacks (Linux servers may use Liberation or DejaVu fonts)

## Important Notes

### Font Name Matching
- **Case Sensitivity**: Font names are matched case-insensitively
- **Exact Match Required**: Use the exact font names from the table above (e.g., "Times New Roman" not "TimesNewRoman")
- **Spaces**: Include spaces in font names (e.g., "Times New Roman", "Trebuchet MS")

### Fallback Behavior
- If a font file is not found on the server, the system will:
  1. Try to find a similar system font
  2. Fall back to built-in PHP fonts (limited features)
  3. Still generate the certificate, but fonts may look different

### Font Availability
- Fonts are installed on the backend server in `storage/fonts/` or `resources/fonts/`
- All listed fonts should be available, but availability depends on server configuration
- If users report font issues, check server logs for font loading warnings

## API Integration

### Saving Template Configuration

When saving a certificate template configuration, include the font family in your request:

```javascript
// Example: Save certificate template configuration
const configData = {
  config_json: [
    {
      variable: "{{student_name}}",
      x: 0.5,
      y: 0.4,
      font_family: "Georgia",        // Use exact font name
      font_size: 56,
      color: "#1a1a1a",
      text_align: "center"
    },
    {
      variable: "{{course_name}}",
      x: 0.5,
      y: 0.55,
      font_family: "Arial",          // Can use different fonts for different placeholders
      font_size: 42,
      color: "#333333",
      text_align: "center"
    }
  ]
};

// POST to: /api/acc/certificate-templates/{id}/config
await accAPI.updateTemplateConfig(templateId, configData);
```

## Font Recommendations by Use Case

### Headers and Titles
- **Recommended**: Georgia, Times New Roman (elegant, formal)
- **Alternative**: Arial, Verdana (clean, modern)

### Body Text
- **Recommended**: Arial, Verdana, Tahoma (readable, professional)
- **Alternative**: Georgia (for formal certificates)

### Student Names
- **Recommended**: Georgia, Times New Roman (formal, distinguished)
- **Alternative**: Trebuchet MS, Arial (modern, clean)

### Dates and IDs
- **Recommended**: Arial, Tahoma (compact, clear)
- **Alternative**: Courier New (monospace, technical look)

### Decorative/Accent Text
- **Recommended**: Impact (bold, attention-grabbing)
- **Alternative**: Georgia (elegant serif)

## Troubleshooting

### Font Not Appearing Correctly

If a selected font doesn't appear correctly in the generated certificate:

1. **Verify Font Name**: Ensure you're using the exact font name from the supported fonts table
2. **Check Server Logs**: Backend logs will indicate if fonts are found or missing
3. **Test Alternative**: Try a different font from the list to verify the system is working
4. **Contact Backend Team**: If fonts are consistently not working, the backend team may need to verify font files are installed

### Font Rendering Issues

- **Different Appearance**: Fonts may render slightly differently on different servers (Windows vs Linux)
- **Font Size Differences**: Slight variations in font metrics are normal
- **Alignment Issues**: If text alignment looks off, check that coordinates and alignment settings are correct

## Example: Complete Font Configuration

```javascript
// Complete example of a certificate template configuration
const templateConfig = {
  config_json: [
    // Certificate Title
    {
      variable: "Certificate of Completion",
      x: 0.5,
      y: 0.25,
      font_family: "Georgia",
      font_size: 64,
      color: "#1a1a1a",
      text_align: "center"
    },
    // Student Name
    {
      variable: "{{student_name}}",
      x: 0.5,
      y: 0.45,
      font_family: "Times New Roman",
      font_size: 56,
      color: "#000000",
      text_align: "center"
    },
    // Course Name
    {
      variable: "{{course_name}}",
      x: 0.5,
      y: 0.60,
      font_family: "Arial",
      font_size: 42,
      color: "#333333",
      text_align: "center"
    },
    // Date
    {
      variable: "{{date}}",
      x: 0.5,
      y: 0.75,
      font_family: "Verdana",
      font_size: 32,
      color: "#666666",
      text_align: "center"
    },
    // Certificate ID
    {
      variable: "Certificate ID: {{cert_id}}",
      x: 0.5,
      y: 0.90,
      font_family: "Courier New",
      font_size: 24,
      color: "#999999",
      text_align: "center"
    }
  ]
};
```

## Summary

- ✅ **9 fonts** are available for certificate generation
- ✅ Use **exact font names** as listed in the table
- ✅ Fonts are stored in `storage/fonts/` on the backend server
- ✅ All fonts support sizes from 8-200 pixels
- ✅ All fonts support left, center, and right alignment
- ✅ Fonts work with any hex color code

For questions or issues with fonts, contact the backend development team.

