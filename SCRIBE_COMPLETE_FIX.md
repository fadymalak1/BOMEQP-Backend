# Complete Fix for Scribe Assets in Subdirectory

Your APP_URL is `https://aeroenix.com/v1/api` but docs need to work at `/v1/docs`. Here's how to fix it without changing .env.

## Solution 1: Configure Scribe Config File (Recommended)

### Step 1: Edit config/scribe.php

On your server, edit `config/scribe.php` and set the base URL directly:

```php
// Find this line and change it:
'base_url' => 'https://aeroenix.com/v1', // Set directly, don't use env('APP_URL')

// If using static type, also check:
'static' => [
    'output_path' => 'public/docs',
    // ... other settings
],
```

### Step 2: Regenerate Documentation

```bash
php artisan config:clear
php artisan scribe:generate
```

## Solution 2: Route Already Added (Backup)

I've already added a route in `routes/web.php` that serves assets from `/vendor/scribe/{path}`. This route will work as a backup if static assets don't load.

## Step 3: Clear Route Cache

After updating routes:

```bash
php artisan route:clear
php artisan config:clear
```

## Verify

1. **Check documentation URL:**
   - Access: `https://aeroenix.com/v1/docs`

2. **Check asset URLs directly:**
   - Try: `https://aeroenix.com/v1/vendor/scribe/css/theme-default.style.css`
   - This should load via the route we added

3. **Check config:**
   ```bash
   cat config/scribe.php | grep -A 2 "base_url"
   ```

## Documentation URL

Your API documentation will be available at:
**`https://aeroenix.com/v1/docs`**

The assets should now load correctly with the route we added, or by configuring the base_url in Scribe's config file.

