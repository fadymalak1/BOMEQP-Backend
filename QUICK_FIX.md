# Quick Fix for Swagger 404

## Immediate Solution

Your app is in a `/laravel/` subdirectory. Try accessing Swagger at:

```
https://app.bomeqp.com/laravel/api/documentation
```

**NOT** `https://app.bomeqp.com/api/documentation`

## Verify the Correct URL

Run this command on your server to see what URL Laravel generates:

```bash
php artisan tinker --execute="echo route('l5-swagger.default.api');"
```

Or test the test route:
```bash
curl https://app.bomeqp.com/laravel/api/test-swagger-url
```

This will show you the exact URL structure Laravel is using.

## If Subdirectory URL Works

If `https://app.bomeqp.com/laravel/api/documentation` works, then:

1. **Update your documentation/bookmarks** to use the correct URL
2. **Optionally fix server config** to make `/api/documentation` work directly (see SUBDIRECTORY_FIX.md)

## If Subdirectory URL Also Fails

Run the diagnostic:
```bash
bash diagnose-swagger-404.sh
```

Then check:
1. Server document root configuration
2. `.htaccess` rewrite rules
3. Laravel logs: `tail -f storage/logs/laravel.log`

