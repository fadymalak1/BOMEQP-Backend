# Swagger 404 Error - Fix Instructions

## Problem
Accessing `https://app.bomeqp.com/api/documentation` returns a 404 error.

## Root Causes
1. **Incorrect `.env` configuration**: `L5_SWAGGER_BASE_PATH=/api` should be the full URL or null
2. **Routes not registered**: L5-Swagger routes may not be properly registered
3. **Cache issues**: Laravel caches may be stale
4. **Documentation not generated**: Swagger docs may need regeneration

## Solution Steps

### Step 1: Fix `.env` Configuration

Update your `.env` file on the server. Change:

```env
L5_SWAGGER_BASE_PATH=/api
```

To either:

```env
L5_SWAGGER_BASE_PATH=https://app.bomeqp.com/api
```

OR remove the line entirely (let it use null/default):

```env
# L5_SWAGGER_BASE_PATH=/api  (comment out or remove)
```

### Step 2: Run Fix Script on Server

**For Windows Server:**
```bash
fix-swagger-route.bat
```

**For Linux/Unix Server:**
```bash
bash fix-swagger-route.sh
```

**Or manually run these commands:**
```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Generate Swagger documentation
php artisan l5-swagger:generate

# Optimize for production
php artisan config:cache
php artisan route:cache
```

### Step 3: Verify File Permissions

Ensure the storage directory is writable:

```bash
# Linux/Unix
chmod -R 775 storage/api-docs
chown -R www-data:www-data storage/api-docs  # Adjust user/group as needed

# Windows - ensure IIS_IUSRS has write permissions
```

### Step 4: Verify Route Registration

Check if the route is registered:

```bash
php artisan route:list | grep documentation
```

You should see a route like:
```
GET|HEAD  api/documentation ................. l5-swagger.default.api
```

### Step 5: Check Laravel Logs

If still not working, check:
```bash
tail -f storage/logs/laravel.log
```

## Alternative: Manual Route Registration

If routes still don't register, you can manually add to `routes/web.php`:

```php
// Add this at the end of routes/web.php
Route::get('/api/documentation', function () {
    return view('l5-swagger::index');
})->name('l5-swagger.default.api');
```

## Expected Result

After completing these steps, you should be able to access:
- `https://app.bomeqp.com/api/documentation`
- `https://app.bomeqp.com/api/doc` (redirects to documentation)
- `https://app.bomeqp.com/api/docs` (redirects to documentation)

## Troubleshooting

1. **Still getting 404?**
   - Check server rewrite rules (.htaccess for Apache, nginx config for Nginx)
   - Verify `storage/api-docs/api-docs.json` exists
   - Check file permissions

2. **Route not found?**
   - Run `php artisan route:clear` and `php artisan route:cache`
   - Check if L5-Swagger service provider is registered

3. **Blank page?**
   - Check browser console for JavaScript errors
   - Verify `storage/api-docs/api-docs.json` is valid JSON
   - Regenerate docs: `php artisan l5-swagger:generate`

## Notes

- The `L5_SWAGGER_BASE_PATH` in `.env` is used for the OpenAPI spec's server URL, not the route path
- The route path is configured in `config/l5-swagger.php` under `defaults.routes.api`
- In production, set `L5_SWAGGER_GENERATE_ALWAYS=false` to avoid regenerating on every request

