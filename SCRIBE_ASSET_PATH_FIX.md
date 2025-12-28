# Fix Scribe Asset Path Issue

Your assets are located at `public_html/v1/public/vendor/scribe` but they're not loading correctly. This is likely a path configuration issue.

## Issue

Assets are trying to load from `/vendor/scribe/` but the server structure might need a different path.

## Solution 1: Check Scribe Configuration

Edit `config/scribe.php` and check the `base_url` and asset path settings:

```php
// In config/scribe.php, look for:
'base_url' => env('APP_URL', 'https://aeroenix.com/v1'),

// And make sure the type is set correctly:
'type' => 'static', // or 'laravel'
```

For static type, assets should be in `public/docs/vendor/scribe/`
For Laravel type, assets should be in `public/vendor/scribe/`

## Solution 2: Verify Asset Location

Check if assets are in the correct location:

```bash
# On your server, check:
ls -la public_html/v1/public/vendor/scribe/
```

You should see:
- `css/` directory
- `js/` directory
- `images/` directory

## Solution 3: Check Web Server Configuration

If your Laravel app is in a subdirectory (`/v1/`), you might need to configure the asset path correctly.

Check if there's a `.htaccess` or web server configuration that needs to handle the `/v1/` prefix.

## Solution 4: Regenerate Documentation with Correct Base URL

Regenerate the documentation with the correct base URL:

```bash
# Set the base URL in .env or config
APP_URL=https://aeroenix.com/v1

# Then regenerate
php artisan config:clear
php artisan scribe:generate
```

## Solution 5: Check Documentation Type

Scribe has two modes:
- **Static** - Generates HTML files in `public/docs/` with assets in `public/docs/vendor/scribe/`
- **Laravel** - Serves via Laravel routes with assets in `public/vendor/scribe/`

Check your `config/scribe.php`:

```php
'type' => 'static', // Change this to match your setup
```

If using **static**, assets should be at:
`public/docs/vendor/scribe/`

If using **laravel**, assets should be at:
`public/vendor/scribe/`

## Solution 6: Verify Asset URLs in Generated HTML

Check the generated documentation HTML to see what paths it's using:

```bash
# If using static type:
cat public/docs/index.html | grep "vendor/scribe"

# This will show you what paths are being used
```

## Solution 7: Create Symbolic Link (If Needed)

If assets are in the wrong location, you can create a symbolic link:

```bash
# Make sure assets are in public/vendor/scribe
ln -s /path/to/vendor/scribe /path/to/public/vendor/scribe
```

## Quick Diagnostic Steps

1. **Check asset location:**
   ```bash
   ls -la public_html/v1/public/vendor/scribe/css/
   ```

2. **Check Scribe config:**
   ```bash
   cat public_html/v1/config/scribe.php | grep -A 5 "base_url\|type"
   ```

3. **Check generated docs:**
   ```bash
   # Find where docs are generated
   find public_html/v1 -name "index.html" | grep -i docs
   ```

4. **Clear cache and regenerate:**
   ```bash
   php artisan config:clear
   php artisan scribe:generate
   ```

## Most Likely Fix

Since your assets are at `public_html/v1/public/vendor/scribe`, and your app is at `https://aeroenix.com/v1`, try:

1. **Ensure Scribe type is 'laravel':**
   ```php
   // In config/scribe.php
   'type' => 'laravel',
   ```

2. **Set correct base URL:**
   ```php
   // In config/scribe.php or .env
   'base_url' => 'https://aeroenix.com/v1',
   ```

3. **Regenerate docs:**
   ```bash
   php artisan config:clear
   php artisan scribe:generate
   ```

The assets should then be accessible at: `https://aeroenix.com/v1/vendor/scribe/css/...`

