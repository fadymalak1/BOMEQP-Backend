# Fix Scribe Configuration Without Changing .env

Since `APP_URL=https://aeroenix.com/v1/api` but your docs are at `/v1/docs`, we need to configure Scribe's base URL separately.

## Solution: Configure Scribe Config File

### Step 1: Publish Scribe Config (if not already done)

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

### Step 2: Edit config/scribe.php

Find the `base_url` setting and override it directly (not using env):

```php
// In config/scribe.php, find and change:
'base_url' => 'https://aeroenix.com/v1', // Don't use env, set directly

// Also check for 'static' section if it exists:
'static' => [
    'output_path' => 'public/docs',
    // Make sure this is correct
],
```

### Step 3: Check Type Setting

Make sure the type is correct:

```php
'type' => 'static', // This generates static HTML files
// OR
'type' => 'laravel', // This uses Laravel routes
```

For **static** type, assets should be in: `public/docs/vendor/scribe/`
For **laravel** type, assets should be in: `public/vendor/scribe/`

### Step 4: Regenerate Documentation

```bash
php artisan config:clear
php artisan scribe:generate
```

## Alternative: Create Route to Serve Assets

If the above doesn't work, you can create a route to properly serve the assets:

Add to `routes/web.php`:

```php
// Serve Scribe assets with correct path
Route::get('/vendor/scribe/{path}', function ($path) {
    $assetPath = public_path("vendor/scribe/{$path}");
    if (file_exists($assetPath)) {
        $mimeType = match(pathinfo($assetPath, PATHINFO_EXTENSION)) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
        return response()->file($assetPath, ['Content-Type' => $mimeType]);
    }
    abort(404);
})->where('path', '.*');
```

This route will make assets accessible at `/v1/vendor/scribe/...` when accessed via the `/v1/` prefix.

## Verify Documentation URL

After fixing, your documentation should be accessible at:
- **Static type:** `https://aeroenix.com/v1/docs`
- **Laravel type:** `https://aeroenix.com/v1/docs` (via route)

The assets should then load correctly with the `/v1/` prefix included.

