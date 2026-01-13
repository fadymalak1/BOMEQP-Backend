# Certificate Font Compatibility - Frontend & Backend

## Overview

This document explains the font compatibility between the frontend certificate designer and the backend certificate generation system.

## Supported Fonts

The system supports the following font families (matching the frontend designer):

1. **Arial**
2. **Helvetica** (uses Arial as fallback on Windows)
3. **Times New Roman**
4. **Courier New** (also supports "Courier" as alias)
5. **Verdana**
6. **Georgia**
7. **Tahoma**
8. **Trebuchet MS**
9. **Impact**

## Font Resolution

The backend automatically resolves fonts based on the operating system:

### Windows
- Uses system fonts from `C:\Windows\Fonts\`
- All fonts are typically available on Windows systems
- Helvetica falls back to Arial

### Linux
- Uses Liberation fonts as primary fallback
- Falls back to DejaVu fonts if Liberation not available
- Font mapping:
  - Sans-serif fonts (Arial, Helvetica, Verdana, Tahoma) → Liberation Sans or DejaVu Sans
  - Serif fonts (Times New Roman, Georgia) → Liberation Serif
  - Monospace fonts (Courier New) → Liberation Mono

### macOS
- Uses system fonts from `/System/Library/Fonts/`
- Fonts are typically available in Supplemental or main Fonts directory

## Configuration Format

The frontend sends configuration in the following format (snake_case):

```json
{
  "config_json": [
    {
      "variable": "{{student_name}}",  // or static text like "Custom Text"
      "x": 0.5,                        // percentage (0.0 to 1.0)
      "y": 0.4,                        // percentage (0.0 to 1.0)
      "font_family": "Arial",          // font family name
      "font_size": 48,                 // font size (8-200)
      "color": "#000000",              // hex color (#RRGGBB)
      "text_align": "center"           // "left", "center", or "right"
    }
  ]
}
```

**Note**: The backend also accepts camelCase format (`fontFamily`, `fontSize`, `textAlign`) for backward compatibility, but the frontend converts to snake_case before sending.

## Backend Processing

The backend certificate generation service:

1. **Parses Variables**: Detects `{{variable_name}}` patterns and replaces with actual data
2. **Static Text**: Uses text as-is if no `{{}}` pattern is found
3. **Font Resolution**: Maps font names to system font files
4. **Coordinates**: Converts percentage coordinates (0.0-1.0) to absolute pixel positions
5. **Text Alignment**: Handles left, center, and right alignment

## Font Fallback Strategy

If a specific font is not found:

1. **Primary**: Tries the exact font file for the requested font family
2. **Fallback**: Uses system fallback fonts (Arial/DejaVu Sans on Linux)
3. **Built-in**: Falls back to PHP's built-in fonts (limited sizing) if no TrueType fonts available

## Frontend-Backend Alignment

### Canvas Dimensions
- **Frontend**: Uses 1200x848 pixels (A4 landscape ratio)
- **Backend**: Uses actual background image dimensions
- **Coordinates**: Both use percentage-based coordinates (0.0-1.0) for resolution independence

### Text Properties
- **Font Family**: Must match exactly (case-sensitive)
- **Font Size**: Integer values (8-200)
- **Color**: Hex format (#RRGGBB)
- **Alignment**: "left", "center", or "right"

## Notes for Developers

### Adding New Fonts

To add a new font:

1. **Frontend**: Add to `fontFamilies` array in `CertificateDesignerScreen.jsx`
2. **Backend**: Add font mapping to `getFontPath()` method in `CertificateGenerationService.php`
3. **Font Files**: Ensure font files are available on the server (system fonts or custom fonts in `resources/fonts/`)

### Font File Requirements

- **Format**: TrueType (.ttf) or OpenType (.otf)
- **Location**: System fonts directory or `resources/fonts/` directory
- **Permissions**: Server must have read access to font files

### Testing

When testing certificates:

1. Use the same fonts on frontend and backend
2. Test with different font sizes (especially large sizes)
3. Verify text alignment works correctly
4. Check that special characters render properly
5. Test with long text to ensure wrapping/overflow is handled

## Troubleshooting

### Font Not Rendering
- Check if font file exists on server
- Verify font file permissions
- Check server logs for font loading errors
- Ensure PHP GD extension with FreeType support is installed

### Text Position Mismatch
- Verify coordinates are stored as percentages (0.0-1.0)
- Check canvas dimensions match between frontend and backend
- Ensure text alignment is applied correctly

### Font Size Differences
- Frontend uses pixel sizes, backend uses point sizes (usually similar)
- Verify font size values are within valid range (8-200)
- Check if font scaling is applied correctly

## Production Recommendations

1. **Use System Fonts**: System fonts are most reliable across different servers
2. **Test on Target Server**: Test certificate generation on the production server
3. **Font Installation**: If using custom fonts, install them on all servers (dev, staging, production)
4. **Font Licensing**: Ensure you have proper licenses for any custom fonts used
5. **Font Fallbacks**: Always have fallback fonts configured for reliability

