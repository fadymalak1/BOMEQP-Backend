# Production Font Setup Guide for Certificate Generation

## Problem

On production servers (especially cPanel/Linux), the selected fonts from the frontend may not appear correctly in generated certificates because the system fonts may not be available at expected paths.

## Solution

This guide explains how to ensure fonts work correctly in production.

## Option 1: Upload Fonts to Project (Recommended)

The best solution is to upload font files directly to your project. This ensures fonts are always available regardless of the server configuration.

### Steps:

1. **Create fonts directory**:
   ```bash
   mkdir -p resources/fonts
   ```

2. **Upload font files** to `resources/fonts/` directory:
   - `arial.ttf` (for Arial)
   - `times.ttf` or `times-new-roman.ttf` (for Times New Roman)
   - `courier.ttf` or `cour.ttf` (for Courier New)
   - `verdana.ttf` (for Verdana)
   - `georgia.ttf` (for Georgia)
   - `tahoma.ttf` (for Tahoma)
   - `trebuchet.ttf` or `trebuc.ttf` (for Trebuchet MS)
   - `impact.ttf` (for Impact)

3. **Ensure proper permissions**:
   ```bash
   chmod 644 resources/fonts/*.ttf
   ```

4. **Font file naming**: The system will check multiple variations:
   - Lowercase: `arial.ttf`, `verdana.ttf`
   - Proper case: `Arial.ttf`, `Verdana.ttf`
   - With hyphens: `times-new-roman.ttf`, `trebuchet-ms.ttf`

## Option 2: Install Fonts on Server

If you have root/SSH access to your cPanel server, you can install fonts system-wide.

### For cPanel/Linux Servers:

1. **Check existing fonts**:
   ```bash
   find /usr/share/fonts -name "*.ttf" | grep -i arial
   ```

2. **Install Liberation fonts** (good alternative to Microsoft fonts):
   ```bash
   yum install liberation-fonts  # CentOS/RHEL
   # OR
   apt-get install fonts-liberation  # Debian/Ubuntu
   ```

3. **Install DejaVu fonts** (another good alternative):
   ```bash
   yum install dejavu-fonts  # CentOS/RHEL
   # OR
   apt-get install fonts-dejavu  # Debian/Ubuntu
   ```

## Option 3: Use Laravel Storage

You can also store fonts in Laravel storage:

1. **Create storage fonts directory**:
   ```bash
   mkdir -p storage/fonts
   ```

2. **Upload fonts** to `storage/fonts/`

3. The system automatically checks `storage/fonts/` directory

## Font Compatibility Mapping

The system maps frontend font names to available system fonts:

| Frontend Font | Linux Alternative | File Names Checked |
|--------------|-------------------|-------------------|
| Arial | Liberation Sans, DejaVu Sans | arial.ttf, LiberationSans-Regular.ttf |
| Helvetica | Liberation Sans, Arial | helvetica.ttf, arial.ttf |
| Times New Roman | Liberation Serif | times.ttf, LiberationSerif-Regular.ttf |
| Courier New | Liberation Mono | courier.ttf, cour.ttf, LiberationMono-Regular.ttf |
| Verdana | DejaVu Sans | verdana.ttf, DejaVuSans.ttf |
| Georgia | Liberation Serif | georgia.ttf, LiberationSerif-Regular.ttf |
| Tahoma | DejaVu Sans | tahoma.ttf, DejaVuSans.ttf |
| Trebuchet MS | DejaVu Sans | trebuchet.ttf, trebuc.ttf, DejaVuSans.ttf |
| Impact | DejaVu Sans Bold | impact.ttf, DejaVuSans-Bold.ttf |

## Troubleshooting

### Check which fonts are found:

Enable logging in your `.env`:
```
LOG_LEVEL=debug
```

The system logs font usage:
- `info` level: When a font is successfully found
- `warning` level: When a font is not found

### Test font availability:

Create a test script (`test-fonts.php`):

```php
<?php
$fonts = ['Arial', 'Times New Roman', 'Courier New', 'Verdana', 'Georgia'];
$paths = [
    resource_path('fonts'),
    '/usr/share/fonts/truetype/liberation/',
    '/usr/share/fonts/truetype/dejavu/',
];

foreach ($fonts as $font) {
    echo "Checking: $font\n";
    $found = false;
    foreach ($paths as $basePath) {
        $files = [
            strtolower($font) . '.ttf',
            ucfirst(strtolower($font)) . '.ttf',
        ];
        foreach ($files as $file) {
            $path = rtrim($basePath, '/') . '/' . $file;
            if (file_exists($path)) {
                echo "  ✓ Found: $path\n";
                $found = true;
                break 2;
            }
        }
    }
    if (!$found) {
        echo "  ✗ Not found\n";
    }
}
```

### Verify PHP GD extension:

```bash
php -m | grep gd
php -i | grep -i "gd support"
```

### Check font permissions:

```bash
ls -la resources/fonts/
# Should show readable files (644 permissions)
```

## Production Deployment Checklist

- [ ] Upload required font files to `resources/fonts/`
- [ ] Verify font file permissions (644)
- [ ] Test certificate generation with each font
- [ ] Check server logs for font warnings
- [ ] Verify PHP GD extension with FreeType support is enabled
- [ ] Test on production server before going live

## Recommended Fonts for Download

You can download free fonts from:
- **Google Fonts**: https://fonts.google.com (select "Download family")
- **Font Squirrel**: https://www.fontsquirrel.com
- **Liberation Fonts**: https://github.com/liberationfonts

**Important**: Ensure you have proper licenses for commercial fonts if using them commercially.

## Notes

- The system will automatically fall back to built-in PHP fonts if no TrueType fonts are found
- Built-in fonts have limited sizing options and may not match the frontend exactly
- For best results, always use TrueType fonts (.ttf) files
- Font files should be readable by the web server user (usually `apache`, `www-data`, or `nobody`)

