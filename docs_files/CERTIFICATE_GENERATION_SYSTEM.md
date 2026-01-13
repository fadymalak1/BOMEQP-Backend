# Certificate Generation System Documentation

## Overview

This document describes the certificate generation system with visual template designer functionality. The system allows ACCs to create certificate templates using a drag-and-drop interface, and Training Centers to issue certificates based on those templates.

## Architecture

### 1. Visual Template Designer (ACC Module)
- **Background Image Upload**: ACCs can upload high-resolution JPG/PNG images
- **Drag-and-Drop Editor**: Canvas-based UI for positioning placeholders
- **Template Configuration**: Stores placeholder positions, styling, and metadata as JSON

### 2. Certificate Issuance (Training Center Module)
- **Template Selection**: Training centers select from available templates
- **Dynamic Form**: Generated based on template variables
- **Certificate Generation**: Backend generates PDF/Image from template and data

### 3. Backend Generation Engine
- Uses PHP GD library for image manipulation
- Places text at precise coordinates (stored as percentages)
- Generates high-quality PNG/PDF output

## Database Schema

### certificate_templates table
- `id`: Primary key
- `acc_id`: Foreign key to ACCs
- `category_id`: Foreign key to categories
- `name`: Template name
- `background_image_url`: URL to background image
- `config_json`: JSON configuration storing:
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
- `status`: active/inactive

### certificates table
- Standard certificate fields
- `template_id`: Links to certificate_templates
- `certificate_pdf_url`: Generated certificate file URL
- `verification_code`: Unique verification code

## API Endpoints

### ACC Module

#### Upload Background Image
```
POST /api/acc/certificate-templates/{id}/upload-background
Content-Type: multipart/form-data

Body:
- background_image: (file) JPG/PNG image

Response:
{
  "message": "Background image uploaded successfully",
  "background_image_url": "https://...",
  "template": { ... }
}
```

#### Update Template Configuration
```
PUT /api/acc/certificate-templates/{id}/config

Body:
{
  "config_json": [
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
}

Response:
{
  "message": "Template configuration updated successfully",
  "template": { ... }
}
```

### Training Center Module

#### Get Available Templates
```
GET /api/training-center/certificates/templates

Response:
{
  "templates": [
    {
      "id": 1,
      "name": "Fire Safety Certificate",
      "background_image_url": "https://...",
      "config_json": [ ... ],
      "acc": { ... },
      "category": { ... }
    }
  ]
}
```

#### Issue Certificate
```
POST /api/training-center/certificates

Body:
{
  "template_id": 1,
  "course_id": 1,
  "trainee_name": "John Doe",
  "issue_date": "2024-01-15",
  "expiry_date": "2026-01-15",
  "student_data": {
    "student_name": "John Doe",
    "course_name": "Fire Safety Training",
    "date": "2024-01-15",
    "cert_id": "CERT-2024-001"
  }
}

Response:
{
  "message": "Certificate issued successfully",
  "certificate": { ... }
}
```

## Technical Requirements

### PHP Extensions

The system uses PHP's GD library which is typically included with PHP. To verify:
```bash
php -m | grep gd
```

If GD is not available, install it:
- **Ubuntu/Debian**: `sudo apt-get install php-gd`
- **CentOS/RHEL**: `sudo yum install php-gd`
- **macOS (Homebrew)**: `brew install php-gd`
- **Windows**: Enable `php_gd2.dll` in php.ini

### Optional: Intervention Image (Recommended)

For better image manipulation capabilities, you can install Intervention Image:

```bash
composer require intervention/image
```

Then update `CertificateGenerationService.php` to use Intervention Image instead of GD. This provides:
- Better image quality
- More font support
- Advanced image manipulation features

### Fonts

The system requires TrueType fonts for text rendering. Place font files in `resources/fonts/` directory:
- `arial.ttf`
- `times.ttf`
- `courier.ttf`

Or use system fonts (paths vary by OS):
- **Linux**: `/usr/share/fonts/truetype/`
- **macOS**: `/System/Library/Fonts/`
- **Windows**: `C:\Windows\Fonts\`

## Coordinate System

Coordinates are stored as **percentages** (0.0 to 1.0) of the image dimensions:
- `x: 0.0` = left edge
- `x: 0.5` = center
- `x: 1.0` = right edge
- `y: 0.0` = top edge
- `y: 0.5` = middle
- `y: 1.0` = bottom edge

This ensures templates work correctly regardless of the background image resolution.

## Frontend Implementation

See `FRONTEND_TEMPLATE_DESIGNER_EXAMPLE.md` for React/Vue component examples.

## Best Practices

1. **Resolution Handling**: Always use percentage-based coordinates
2. **Font Consistency**: Use the same fonts on frontend and backend
3. **Image Quality**: Upload high-resolution images (at least 2400x1800px for A4 certificates)
4. **Template Testing**: Test templates with various data lengths
5. **Storage**: Use cloud storage (S3) for production environments

## Troubleshooting

### Text not appearing on certificate
- Check font files exist and paths are correct
- Verify GD library is installed and enabled
- Check image permissions

### Coordinates not matching
- Ensure coordinates are stored as percentages (0.0-1.0)
- Verify canvas aspect ratio matches background image
- Check text alignment settings

### Low image quality
- Use high-resolution background images
- Increase PNG quality (currently set to 9/9)
- Consider using Intervention Image for better quality

