# Scribe API Documentation - Server Deployment Guide

Complete guide for setting up and publishing Scribe API documentation on your production server.

## Overview

Scribe automatically generates beautiful, interactive API documentation from your Laravel routes and controller annotations. This guide will help you set it up and deploy it on your server.

## Prerequisites

- Laravel application with Scribe installed (`knuckleswtf/scribe`)
- Access to server via SSH
- PHP 8.2+ installed
- Composer installed

---

## Step 1: Install/Publish Scribe Configuration

### On Server (SSH)

```bash
# Navigate to your project directory
cd ~/public_html/v1

# Publish Scribe configuration file
php artisan vendor:publish --tag=scribe-config

# Publish Scribe assets (CSS, JS, images)
php artisan vendor:publish --tag=scribe-assets
```

This will create:
- `config/scribe.php` - Configuration file
- Assets in `public/vendor/scribe/` - CSS, JS, images

---

## Step 2: Configure Scribe

### The configuration file is already created

The `config/scribe.php` file has been created with the following key settings:

- **Base URL:** `https://aeroenix.com/v1` (configured for subdirectory deployment)
- **Routes:** All `api/*` routes are included
- **Excluded:** `api/stripe/webhook` (webhook endpoints excluded)
- **Output:** Static HTML documentation in `public/docs`

### Verify Configuration

Check that `config/scribe.php` has:

```php
'base_url' => env('SCRIBE_BASE_URL', 'https://aeroenix.com/v1'),
```

### Optional: Set in `.env`

You can also set the base URL in `.env`:

```env
SCRIBE_BASE_URL=https://aeroenix.com/v1
```

### Important Configuration for Subdirectory Deployment

Since your app is in `/v1/` subdirectory, the base URL is already configured correctly.

---

## Step 3: Add Documentation Route

### Update `routes/web.php`

Add a route to access the documentation:

```php
<?php

use Illuminate\Support\Facades\Route;

// ... existing routes ...

// API Documentation Route
Route::get('/api/doc', function () {
    return redirect('/docs');
});

// Scribe documentation (default route)
// This is automatically handled by Scribe, but you can customize it
```

**Note:** Scribe automatically creates a route at `/docs` by default. If you want to use `/api/doc`, you can add the redirect above.

---

## Step 4: Generate Documentation

### On Server (SSH)

```bash
# Navigate to your project directory
cd ~/public_html/v1

# Generate the documentation
php artisan scribe:generate

# Clear caches to ensure new routes are picked up
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

This will:
- Scan all your API routes
- Extract annotations from controllers
- Generate HTML documentation in `resources/views/vendor/scribe/`
- Generate OpenAPI spec in `public/docs/openapi.yaml`

---

## Step 5: Verify Asset Paths

### Check Asset Route

Make sure `routes/web.php` has the asset serving route (should already exist):

```php
// Serve Scribe assets with correct path for subdirectory deployment
Route::get('/vendor/scribe/{path}', function ($path) {
    $assetPath = public_path("vendor/scribe/{$path}");
    
    if (!file_exists($assetPath)) {
        abort(404);
    }
    
    // Determine MIME type
    $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'gif' => 'image/gif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    
    return response()->file($assetPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');
```

---

## Step 6: Access Documentation

### URLs

After setup, documentation will be available at:

- **HTML Documentation:** `https://aeroenix.com/v1/docs`
- **OpenAPI Spec:** `https://aeroenix.com/v1/docs/openapi.yaml`
- **Redirect from /api/doc:** `https://aeroenix.com/v1/api/doc` → redirects to `/docs`

---

## Step 7: Adding Annotations to Controllers

### Basic Example

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExampleController extends Controller
{
    /**
     * Get user profile
     *
     * @group User Management
     * @authenticated
     * 
     * @response 200 {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "john@example.com"
     * }
     */
    public function profile(Request $request)
    {
        return $request->user();
    }
}
```

### Advanced Example with Parameters

```php
/**
 * Create a new course
 * 
 * Create a new course with optional pricing.
 * 
 * @group ACC Courses
 * @authenticated
 * 
 * @bodyParam sub_category_id integer required Sub category ID. Example: 1
 * @bodyParam name string required Course name. Example: Advanced Fire Safety
 * @bodyParam code string required Unique course code. Example: AFS-001
 * @bodyParam duration_hours integer required Course duration in hours. Example: 40
 * @bodyParam max_capacity integer required Maximum capacity. Example: 20
 * @bodyParam assessor_required boolean optional Whether assessor is required. Example: true
 * @bodyParam level string required Course level. Example: advanced
 * @bodyParam status string required Course status. Example: active
 * @bodyParam pricing array optional Pricing information.
 * @bodyParam pricing.base_price number required Base price. Example: 500.00
 * @bodyParam pricing.currency string required Currency code. Example: USD
 * 
 * @response 201 {
     *   "message": "Course created successfully",
     *   "course": {
     *     "id": 1,
     *     "name": "Advanced Fire Safety",
     *     "code": "AFS-001"
     *   }
     * }
 */
public function store(Request $request)
{
    // Controller logic
}
```

---

## Step 8: Regenerating Documentation

### When to Regenerate

Regenerate documentation after:
- Adding new API endpoints
- Updating controller annotations
- Changing route definitions
- Modifying request/response structures

### Command

```bash
php artisan scribe:generate
```

### Auto-Regeneration (Optional)

You can set up auto-regeneration on deployment by adding to your deployment script:

```bash
# In your deployment script
php artisan scribe:generate
php artisan config:clear
php artisan route:clear
```

---

## Step 9: Server-Specific Configuration

### For Subdirectory Deployment (`/v1/`)

Update `.env`:
```env
APP_URL=https://aeroenix.com/v1/api
SCRIBE_BASE_URL=https://aeroenix.com/v1
```

Or directly in `config/scribe.php`:
```php
'base_url' => 'https://aeroenix.com/v1',
```

### File Permissions

Ensure proper permissions for generated files:

```bash
# Set permissions for storage and cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Set permissions for public assets
chmod -R 755 public/vendor/scribe
```

---

## Step 10: Troubleshooting

### Issue 1: Documentation shows but no CSS/JS

**Problem:** Assets not loading (404 errors)

**Solution:**
```bash
# Re-publish assets
php artisan vendor:publish --tag=scribe-assets --force

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Verify assets exist
ls -la public/vendor/scribe/
```

### Issue 2: Routes not appearing in documentation

**Problem:** Some routes are missing

**Solution:**
1. Check `config/scribe.php` - verify route matching rules
2. Ensure routes have proper annotations
3. Clear route cache: `php artisan route:clear`
4. Regenerate: `php artisan scribe:generate`

### Issue 3: Wrong base URL in examples

**Problem:** Example URLs show incorrect domain

**Solution:**
Update `config/scribe.php`:
```php
'base_url' => 'https://aeroenix.com/v1',
```

Or set in `.env`:
```env
SCRIBE_BASE_URL=https://aeroenix.com/v1
```

### Issue 4: 404 on `/docs` route

**Problem:** Documentation page not found

**Solution:**
1. Verify Scribe is installed: `composer show knuckleswtf/scribe`
2. Check if route exists: `php artisan route:list | grep docs`
3. Clear route cache: `php artisan route:clear`
4. Regenerate docs: `php artisan scribe:generate`

---

## Complete Server Setup Script

Here's a complete script you can run on your server:

```bash
#!/bin/bash

# Navigate to project directory
cd ~/public_html/v1

# Publish Scribe configuration and assets
php artisan vendor:publish --tag=scribe-config --force
php artisan vendor:publish --tag=scribe-assets --force

# Update .env with Scribe base URL (if not already set)
if ! grep -q "SCRIBE_BASE_URL" .env; then
    echo "SCRIBE_BASE_URL=https://aeroenix.com/v1" >> .env
fi

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Generate documentation
php artisan scribe:generate

# Set proper permissions
chmod -R 755 public/vendor/scribe
chmod -R 775 storage bootstrap/cache

echo "Scribe documentation setup complete!"
echo "Access documentation at: https://aeroenix.com/v1/docs"
```

---

## Quick Reference Commands

```bash
# Publish config
php artisan vendor:publish --tag=scribe-config

# Publish assets
php artisan vendor:publish --tag=scribe-assets

# Generate documentation
php artisan scribe:generate

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# View routes (to verify)
php artisan route:list | grep api
```

---

## Documentation Annotations Reference

### Common Annotations

```php
/**
 * @group Group Name          // Groups endpoints together
 * @authenticated            // Requires authentication
 * @unauthenticated          // Public endpoint
 * 
 * @urlParam id integer required The ID. Example: 1
 * @queryParam page integer Page number. Example: 1
 * @bodyParam name string required Name. Example: John
 * 
 * @response 200 {
     *   "data": []
     * }
 * @response 404 {
     *   "message": "Not found"
     * }
 */
```

### Response Examples

```php
/**
 * @response 200 {
     *   "id": 1,
     *   "name": "Example",
     *   "created_at": "2024-01-01T00:00:00.000000Z"
     * }
 * 
 * @response 422 {
     *   "message": "Validation error",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
 */
```

---

## Testing Documentation

### 1. Access Documentation

Open in browser:
```
https://aeroenix.com/v1/docs
```

### 2. Test "Try It Out" Feature

1. Click on any endpoint
2. Click "Try It Out"
3. Fill in parameters
4. Click "Execute"
5. Verify response

### 3. Check OpenAPI Export

Download OpenAPI spec:
```
https://aeroenix.com/v1/docs/openapi.yaml
```

Import into Postman or other API tools.

---

## Maintenance

### Regular Updates

After adding new endpoints or updating existing ones:

```bash
php artisan scribe:generate
php artisan config:clear
```

### Automated Deployment

Add to your deployment pipeline:

```yaml
# Example GitHub Actions / GitLab CI
- name: Generate API Documentation
  run: |
    php artisan scribe:generate
    php artisan config:clear
    php artisan route:clear
```

---

## Summary

1. ✅ **Publish Config:** `php artisan vendor:publish --tag=scribe-config`
2. ✅ **Publish Assets:** `php artisan vendor:publish --tag=scribe-assets`
3. ✅ **Configure Base URL:** Set in `config/scribe.php` or `.env`
4. ✅ **Generate Docs:** `php artisan scribe:generate`
5. ✅ **Clear Caches:** `php artisan config:clear && php artisan route:clear`
6. ✅ **Access:** `https://aeroenix.com/v1/docs`

---

## Support

If you encounter issues:

1. Check Scribe documentation: https://scribe.knuckles.wtf/
2. Verify Laravel version compatibility
3. Check server logs: `storage/logs/laravel.log`
4. Ensure all dependencies are installed: `composer install`

---

## Next Steps

1. Add annotations to all controllers
2. Customize documentation theme (optional)
3. Set up auto-regeneration on deployment
4. Share documentation URL with frontend team

