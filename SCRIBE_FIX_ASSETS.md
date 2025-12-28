# Fix Scribe CSS/JS 404 Errors

The documentation is working, but CSS and JavaScript files are missing (404 errors). This is because Scribe's assets haven't been published to the public directory yet.

## Solution: Publish Scribe Assets

Run this command on your server:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-assets
```

This will copy Scribe's CSS, JS, and image files from the vendor directory to `public/vendor/scribe/`.

## Complete Asset Publishing

If the above doesn't work, try publishing all Scribe assets:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider"
```

This will publish:
- Configuration files (to `config/scribe.php`)
- Assets (CSS, JS, images to `public/vendor/scribe/`)
- Views (if applicable)

## After Publishing

After running the publish command:

1. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Check if files exist:**
   ```bash
   ls -la public/vendor/scribe/
   ```

   You should see directories like:
   - `css/`
   - `js/`
   - `images/`

3. **Refresh your browser** at `https://aeroenix.com/v1/docs`

## Verify Assets Are Published

Check that these files exist:
- `public/vendor/scribe/css/theme-default.style.css`
- `public/vendor/scribe/css/theme-default.print.css`
- `public/vendor/scribe/js/tryitout-5.3.0.js`
- `public/vendor/scribe/js/theme-default-5.3.0.js`
- `public/vendor/scribe/images/navbar.png`

## Alternative: Check Scribe Configuration

If assets still don't load after publishing, check `config/scribe.php`:

```php
'type' => 'static', // or 'laravel'
```

For static type, assets should be in `public/docs/vendor/scribe/`

For Laravel type, assets should be in `public/vendor/scribe/`

## Quick Fix Summary

**The issue:** CSS/JS files are looking for `/vendor/scribe/css/` but they don't exist in the public directory.

**The fix:** 
```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-assets
```

**Then refresh:** Clear cache and reload the page.

This will make the documentation display with proper styling!
