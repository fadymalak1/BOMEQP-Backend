# Swagger Route Fix - Route [l5-swagger.default.docs] not defined

## Problem
Error: `Route [l5-swagger.default.docs] not defined`

## Root Cause
The `config/l5-swagger.php` file was missing the required `documentations` array structure. L5-Swagger v10+ requires:
1. A `default` key specifying which documentation to use
2. A `documentations` array with proper route configuration including the `docs` route

## Solution Applied

The configuration file has been updated to include:

1. **Added `default` key:**
   ```php
   'default' => 'default',
   ```

2. **Added `documentations` array** with proper structure:
   ```php
   'documentations' => [
       'default' => [
           'routes' => [
               'api' => 'api/documentation',
               'docs' => 'api/documentation/docs',  // This was missing!
               'oauth2_callback' => 'api/oauth2-callback',
               'middleware' => [...],
           ],
           ...
       ],
   ],
   ```

## Steps to Apply on Server

### 1. Upload Updated Config File
Upload the updated `config/l5-swagger.php` file to your server.

### 2. Clear Caches and Regenerate
Run on your server:

**Windows:**
```bash
fix-swagger-route.bat
```

**Linux/Unix:**
```bash
bash fix-swagger-route.sh
```

**Or manually:**
```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Generate Swagger documentation
php artisan l5-swagger:generate

# Cache for production
php artisan config:cache
php artisan route:cache
```

### 3. Verify Routes
Check that routes are registered:
```bash
php artisan route:list | grep swagger
```

You should see routes like:
- `l5-swagger.default.api`
- `l5-swagger.default.docs`
- `l5-swagger.default.asset`

### 4. Test Access
Visit: `https://app.bomeqp.com/api/documentation`

## Expected Routes

After the fix, these routes should be available:
- `GET /api/documentation` - Swagger UI interface
- `GET /api/documentation/docs/{file}` - Documentation JSON/YAML files
- `GET /api/documentation/docs/asset/{asset}` - Swagger UI assets

## Troubleshooting

If you still get errors:

1. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verify config structure:**
   ```bash
   php artisan tinker
   >>> config('l5-swagger.documentations.default.routes.docs');
   ```
   Should return: `"api/documentation/docs"`

3. **Check file permissions:**
   ```bash
   chmod -R 775 storage/api-docs
   ```

4. **Verify service provider is loaded:**
   ```bash
   php artisan package:discover
   ```

## Notes

- The `docs` route is essential - it serves the generated `api-docs.json` file
- The route name format is: `l5-swagger.{documentation_name}.{route_type}`
- For the default documentation, routes are: `l5-swagger.default.*`

