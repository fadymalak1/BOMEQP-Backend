# Certificate Generation System - Implementation Summary

## Overview

A complete certificate generation system has been implemented with visual template designer functionality. The system allows ACCs to create certificate templates using a drag-and-drop interface, and Training Centers to issue certificates based on those templates.

## What Was Implemented

### 1. Database Schema
- ✅ Migration created: `2026_01_20_000001_add_config_json_to_certificate_templates_table.php`
- ✅ Added `config_json` field to store template designer configuration
- ✅ Updated `CertificateTemplate` model to handle `config_json` field

### 2. ACC Module - Template Designer
- ✅ **Background Image Upload Endpoint**: `POST /api/acc/certificate-templates/{id}/upload-background`
  - Accepts JPG/PNG images (max 10MB)
  - Stores images in public storage
  - Returns image URL
  
- ✅ **Template Configuration Endpoint**: `PUT /api/acc/certificate-templates/{id}/config`
  - Accepts JSON configuration with placeholders
  - Stores coordinates as percentages (0.0-1.0)
  - Stores styling: font family, size, color, alignment

- ✅ Updated `CertificateTemplateController` with new methods
- ✅ Added image cleanup on template deletion

### 3. Training Center Module - Certificate Issuance
- ✅ **Get Available Templates**: `GET /api/training-center/certificates/templates`
  - Returns templates from authorized ACCs
  - Filters to active templates with configuration
  
- ✅ **Issue Certificate**: `POST /api/training-center/certificates`
  - Generates certificate from template
  - Creates certificate record
  - Returns certificate with PDF/image URL

- ✅ Updated `TrainingCenter\CertificateController` with issuance logic
- ✅ Added certificate number and verification code generation

### 4. Certificate Generation Service
- ✅ Created `CertificateGenerationService`
- ✅ Uses PHP GD library for image manipulation
- ✅ Supports percentage-based coordinates
- ✅ Text placement with styling (font, size, color, alignment)
- ✅ Generates high-quality PNG output
- ✅ Font support (TTF fonts with fallback)

### 5. Documentation
- ✅ System documentation: `CERTIFICATE_GENERATION_SYSTEM.md`
- ✅ Frontend examples: `FRONTEND_TEMPLATE_DESIGNER_EXAMPLE.md`
- ✅ React + Fabric.js example code
- ✅ Vue 3 + Fabric.js example code

## API Endpoints Summary

### ACC Endpoints
```
POST   /api/acc/certificate-templates/{id}/upload-background
PUT    /api/acc/certificate-templates/{id}/config
GET    /api/acc/certificate-templates
POST   /api/acc/certificate-templates
GET    /api/acc/certificate-templates/{id}
PUT    /api/acc/certificate-templates/{id}
DELETE /api/acc/certificate-templates/{id}
```

### Training Center Endpoints
```
GET    /api/training-center/certificates/templates
GET    /api/training-center/certificates
POST   /api/training-center/certificates
GET    /api/training-center/certificates/{id}
```

## Configuration Format

The `config_json` field stores an array of placeholder configurations:

```json
[
  {
    "variable": "{{student_name}}",
    "x": 0.5,
    "y": 0.4,
    "font_family": "Arial",
    "font_size": 48,
    "color": "#000000",
    "text_align": "center"
  }
]
```

## Key Features

1. **Percentage-Based Coordinates**: Ensures templates work at any resolution
2. **Font Support**: TrueType fonts with system font fallback
3. **Text Alignment**: Left, center, and right alignment support
4. **Color Support**: Hex color codes for text
5. **Image Quality**: High-quality PNG generation (quality level 9)
6. **Storage**: Uses Laravel's public storage disk

## Requirements

- PHP GD extension (typically included with PHP)
- TrueType fonts (optional, for better text rendering)
- Laravel 12+ (already installed)
- Storage disk configured (public or S3)

## Next Steps

1. **Run Migration**: `php artisan migrate`
2. **Add Fonts** (Optional): Place TTF fonts in `resources/fonts/`
3. **Test Template Creation**: Use ACC endpoints to create templates
4. **Implement Frontend**: Use provided React/Vue examples
5. **Test Certificate Generation**: Issue certificates via Training Center endpoints

## Notes

- The system uses PHP GD library by default (built into PHP)
- For production, consider using Intervention Image for better quality
- Font paths may need adjustment based on server OS
- Coordinates are stored as percentages for resolution independence
- Background images are stored in `storage/app/public/certificate-templates/`
- Generated certificates are stored in `storage/app/public/certificates/`

## Troubleshooting

- **GD Library**: Verify with `php -m | grep gd`
- **Fonts**: Check font file paths and permissions
- **Coordinates**: Ensure coordinates are 0.0-1.0 (percentages)
- **Storage**: Run `php artisan storage:link` for public access

