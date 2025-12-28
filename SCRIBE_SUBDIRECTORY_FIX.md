# Fix Scribe Assets in Subdirectory (/v1/)

Since your Laravel app is in a subdirectory (`/v1/`), Scribe needs to be configured with the correct base path for assets.

## The Problem

Assets are at `public_html/v1/public/vendor/scribe/` which should be accessible at `https://aeroenix.com/v1/vendor/scribe/`, but Scribe is generating HTML with incorrect asset paths.

## Solution: Configure Scribe Base URL

### Step 1: Check/Edit Scribe Configuration

Edit `config/scribe.php` (if it doesn't exist, publish it first):

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

### Step 2: Set Correct Base URL

In `config/scribe.php`, find and update the base URL:

```php
'base_url' => env('APP_URL', 'https://aeroenix.com/v1'),

// Or if there's a static section:
'static' => [
    'output_path' => 'public/docs',
    // ... other settings
],
```

### Step 3: Set APP_URL in .env

Make sure your `.env` file has:

```env
APP_URL=https://aeroenix.com/v1
```

### Step 4: Regenerate Documentation

After updating the config:

```bash
php artisan config:clear
php artisan scribe:generate
```

## Alternative: Check Scribe Type Configuration

Scribe has two types - check which one you're using in `config/scribe.php`:

```php
'type' => 'static', // or 'laravel'
```

### If type is 'static':

Assets should be in: `public/docs/vendor/scribe/`
Documentation at: `public/docs/index.html`

Make sure assets are published there:
```bash
php artisan vendor:publish --tag=scribe-assets
```

Then check if assets are in the right place:
```bash
ls -la public_html/v1/public/docs/vendor/scribe/
```

### If type is 'laravel':

Assets should be in: `public/vendor/scribe/`
(Which is where you have them)

## Verify Asset URLs

After regenerating, check the generated HTML to see what paths it's using:

```bash
# If static type:
grep -r "vendor/scribe" public_html/v1/public/docs/index.html

# Check what paths are being generated
```

The paths should include `/v1/` prefix if your app is in a subdirectory.

## Quick Fix: Manual Path Check

1. **Verify assets are accessible:**
   Try accessing directly: `https://aeroenix.com/v1/vendor/scribe/css/theme-default.style.css`
   
   If this works, the assets are accessible, but Scribe is generating wrong paths.

2. **Check Scribe config:**
   ```bash
   cat config/scribe.php | grep -i "base_url\|output_path\|type"
   ```

3. **Regenerate with correct base URL:**
   ```bash
   APP_URL=https://aeroenix.com/v1 php artisan scribe:generate
   ```

## Most Likely Solution

Since your assets are at `public_html/v1/public/vendor/scribe/`, and they should be accessible at `https://aeroenix.com/v1/vendor/scribe/`, you need to:

1. **Set APP_URL in .env:**
   ```env
   APP_URL=https://aeroenix.com/v1
   ```

2. **Check config/scribe.php:**
   ```php
   'base_url' => env('APP_URL'),
   ```

3. **Regenerate:**
   ```bash
   php artisan config:clear
   php artisan scribe:generate
   ```

This should generate documentation with correct asset paths including the `/v1/` prefix.

