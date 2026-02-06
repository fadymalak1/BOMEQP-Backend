# Swagger 404 Fix - Subdirectory Installation

## Problem
Routes are registered correctly, but accessing `https://app.bomeqp.com/api/documentation` returns 404.

## Root Cause
Your Laravel application is installed in a `/laravel/` subdirectory, as evidenced by:
- `STORAGE_URL=https://app.bomeqp.com/laravel/storage/app/public`
- `SCRIBE_BASE_URL=https://app.bomeqp.com/laravel`

## Solution

### Option 1: Use Correct URL (Quick Fix)
Access Swagger using the subdirectory path:
```
https://app.bomeqp.com/laravel/api/documentation
```

### Option 2: Fix Server Configuration (Recommended)

#### For Apache (.htaccess)
If your `public/` folder is the document root, ensure `.htaccess` is correct. If Laravel is in a subdirectory, you may need a `.htaccess` in the parent directory:

**Create `/laravel/.htaccess`** (if it doesn't exist):
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**Or update your server's document root** to point to `/laravel/public/` instead of `/laravel/`

#### For Nginx
Update your Nginx configuration to set the document root correctly:
```nginx
root /home/bomeqpuser/public_html/laravel/public;
```

### Option 3: Update Route Configuration

If you want routes to work without the `/laravel/` prefix, you need to configure your web server to point the document root to `/laravel/public/` and update your domain's document root setting.

## Diagnostic Steps

1. **Run diagnostic script:**
   ```bash
   bash diagnose-swagger-404.sh
   ```

2. **Test route generation:**
   ```bash
   php artisan tinker
   >>> route('l5-swagger.default.api');
   ```
   This will show you the actual URL Laravel generates.

3. **Check server logs:**
   ```bash
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

4. **Test direct access:**
   ```bash
   curl -I https://app.bomeqp.com/laravel/api/documentation
   ```

## Quick Test

Try accessing these URLs to determine the correct path:

1. `https://app.bomeqp.com/laravel/api/documentation` ← Most likely
2. `https://app.bomeqp.com/api/documentation` ← If document root is `/laravel/public/`
3. `https://app.bomeqp.com/laravel/public/api/documentation` ← If no rewrite rules

## Verify Document Root

Check where your server's document root is pointing:

```bash
# Check Apache config
grep -r "DocumentRoot" /etc/apache2/sites-enabled/

# Check Nginx config  
grep -r "root" /etc/nginx/sites-enabled/
```

The document root should be:
- `/home/bomeqpuser/public_html/laravel/public/` (for subdirectory install)
- OR `/home/bomeqpuser/public_html/public/` (if Laravel root is document root)

## After Fix

Once you've determined the correct URL structure:

1. Update `.env` if needed:
   ```env
   APP_URL=https://app.bomeqp.com/laravel
   ```

2. Clear caches:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan config:cache
   php artisan route:cache
   ```

3. Test the Swagger URL

