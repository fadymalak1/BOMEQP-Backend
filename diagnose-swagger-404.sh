#!/bin/bash

echo "🔍 Diagnosing Swagger 404 Issue..."
echo ""

# Check if app is in subdirectory
echo "1. Checking application structure..."
if [ -d "../public_html" ]; then
    echo "   ⚠️  App appears to be in subdirectory structure"
    echo "   Current path: $(pwd)"
fi

# Check .env configuration
echo ""
echo "2. Checking .env configuration..."
if [ -f .env ]; then
    echo "   APP_URL: $(grep '^APP_URL=' .env | cut -d '=' -f2)"
    echo "   STORAGE_URL: $(grep '^STORAGE_URL=' .env | cut -d '=' -f2)"
    echo "   SCRIBE_BASE_URL: $(grep '^SCRIBE_BASE_URL=' .env | cut -d '=' -f2)"
fi

# Check routes
echo ""
echo "3. Checking registered routes..."
php artisan route:list | grep -E "(swagger|documentation)" | head -10

# Check if route cache exists
echo ""
echo "4. Checking route cache..."
if [ -f bootstrap/cache/routes-v7.php ]; then
    echo "   ⚠️  Route cache exists - this might be stale"
    echo "   Run: php artisan route:clear && php artisan route:cache"
else
    echo "   ✅ No route cache (routes loaded dynamically)"
fi

# Check Swagger config
echo ""
echo "5. Checking Swagger configuration..."
php artisan tinker --execute="echo 'Documentations: '; print_r(array_keys(config('l5-swagger.documentations', []))); echo PHP_EOL; echo 'Default routes: '; print_r(config('l5-swagger.documentations.default.routes', []));"

# Check if docs file exists
echo ""
echo "6. Checking documentation files..."
if [ -f "storage/api-docs/api-docs.json" ]; then
    echo "   ✅ api-docs.json exists"
    echo "   Size: $(du -h storage/api-docs/api-docs.json | cut -f1)"
else
    echo "   ❌ api-docs.json NOT FOUND"
    echo "   Run: php artisan l5-swagger:generate"
fi

# Check file permissions
echo ""
echo "7. Checking file permissions..."
ls -la storage/api-docs/ 2>/dev/null || echo "   ⚠️  Cannot check permissions"

# Check web server configuration
echo ""
echo "8. Checking public directory..."
if [ -f "public/.htaccess" ]; then
    echo "   ✅ .htaccess exists in public/"
else
    echo "   ⚠️  .htaccess missing in public/"
fi

echo ""
echo "✅ Diagnosis complete!"
echo ""
echo "💡 Based on your .env, try accessing:"
echo "   https://app.bomeqp.com/laravel/api/documentation"
echo ""
echo "   (Note the /laravel/ prefix if your app is in a subdirectory)"

