# Scribe Installation Steps - Fix 404 Error

You're getting a 404 error because Scribe hasn't been installed and configured yet. Follow these steps:

## Step 1: Install Scribe Package

On your server, run:

```bash
composer require knuckleswtf/scribe
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

## Step 3: Configure Documentation Route (Optional)

If you want to serve docs at `/api/doc` instead of `/docs`, you can either:

**Option A: Use the default `/docs` route**
- After installation, access docs at: `https://aeroenix.com/v1/docs`

**Option B: Configure custom route**

Edit `config/scribe.php` and find the `routes` section. Add this to `routes/web.php`:

```php
// In routes/web.php, add:
Route::get('/api/doc', function () {
    if (file_exists(public_path('docs/index.html'))) {
        return file_get_contents(public_path('docs/index.html'));
    }
    return redirect('/docs');
});
```

Or simply use Scribe's built-in route redirection in the config file.

Actually, Scribe v4+ serves documentation via Laravel routes. After installation, the docs will be available at `/docs` by default.

## Step 4: Generate Documentation

```bash
php artisan scribe:generate
```

This command will:
- Scan your routes and controllers
- Generate HTML documentation files
- Set up the route to serve the docs

## Step 5: Clear Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Step 6: Access Documentation

After completing the above steps, access your documentation at:

- **Default route:** `https://aeroenix.com/v1/docs`
- **Or if you configured it:** `https://aeroenix.com/v1/api/doc`

## Quick Fix: Configure Custom Route

If you want `/api/doc` to work, add this to your `routes/web.php` **after installing Scribe**:

```php
use Knuckles\Scribe\Http\Controller;

// Serve Scribe documentation at /api/doc
Route::get('/api/doc', [\Knuckles\Scribe\Http\Controllers\ScribeController::class, 'web'])->name('api.doc');
```

**Note:** The exact controller path may vary based on Scribe version. Check Scribe's documentation or use the default `/docs` route.

## Alternative: Use Default Route

The simplest solution is to use Scribe's default route:

1. Install Scribe
2. Generate docs: `php artisan scribe:generate`
3. Access at: `https://aeroenix.com/v1/docs`

## Troubleshooting

### Still getting 404?

1. **Check if Scribe is installed:**
   ```bash
   composer show knuckleswtf/scribe
   ```

2. **Check if docs are generated:**
   ```bash
   ls -la public/docs
   ```
   (or check if `storage/app/docs` exists)

3. **Check routes:**
   ```bash
   php artisan route:list | grep doc
   ```

4. **Clear all caches:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   php artisan view:clear
   ```

## Summary

The 404 error is happening because:
1. ❌ Scribe package is not installed yet
2. ❌ Documentation has not been generated
3. ❌ Routes are not registered

**To fix:**
1. ✅ Install: `composer require knuckleswtf/scribe`
2. ✅ Publish config: `php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config`
3. ✅ Generate docs: `php artisan scribe:generate`
4. ✅ Access at: `https://aeroenix.com/v1/docs` (or configure custom route)


