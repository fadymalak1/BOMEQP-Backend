# Setting Up Scribe Route for /api/doc

## The Problem

You're getting a 404 because Scribe hasn't been installed yet. Scribe needs to be installed and configured before the route will work.

## Solution

### Step 1: Install Scribe (On Your Server)

```bash
composer require knuckleswtf/scribe
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

### Step 3: Configure Route in config/scribe.php

After publishing the config, edit `config/scribe.php` and find the section for routes. 

For Scribe v4+, you can configure the documentation route by editing `config/scribe.php`:

```php
'routes' => [
    [
        'match' => [
            'prefixes' => ['api/*'],
        ],
    ],
],

// And in the docs section:
'docs' => [
    'route' => [
        'prefix' => 'api',
        'uri' => 'doc',
    ],
],
```

### Step 4: Generate Documentation

```bash
php artisan scribe:generate
```

### Step 5: Add Route to routes/web.php (Alternative Method)

If the config method doesn't work, you can manually add a route in `routes/web.php`:

```php
// Serve Scribe documentation
Route::get('/api/doc', function () {
    // Check if Scribe docs exist
    $docsPath = public_path('docs/index.html');
    if (file_exists($docsPath)) {
        return response()->file($docsPath);
    }
    
    // If using Scribe's Laravel mode, redirect to default route
    return redirect('/docs');
})->name('api.doc');
```

**However**, Scribe v4+ handles routes automatically, so you may not need to add this manually.

### Step 6: Clear Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Quick Test

After installation, try accessing:

1. Default route: `https://aeroenix.com/v1/docs`
2. Custom route: `https://aeroenix.com/v1/api/doc`

## What's Happening Now

The 404 error occurs because:
- ✅ `composer.json` has been updated (we added `knuckleswtf/scribe`)
- ❌ But `composer require` hasn't been run yet on the server
- ❌ Scribe package is not installed
- ❌ Documentation hasn't been generated
- ❌ Routes are not registered

## Recommended Approach

1. **Install Scribe on your server:**
   ```bash
   composer require knuckleswtf/scribe
   ```

2. **Publish and configure:**
   ```bash
   php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
   ```

3. **Edit `config/scribe.php`** to set the route to `/api/doc` if needed

4. **Generate docs:**
   ```bash
   php artisan scribe:generate
   ```

5. **Access at:** `https://aeroenix.com/v1/api/doc` (or `/docs` if using default)

The route will work automatically once Scribe is installed and configured!


