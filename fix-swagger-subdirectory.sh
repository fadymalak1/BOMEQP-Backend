#!/bin/bash

echo "🔧 Fixing Swagger for Subdirectory Installation..."
echo ""

# Check if app is in subdirectory
SUBDIRECTORY=""
if grep -q "laravel" .env 2>/dev/null; then
    STORAGE_URL=$(grep '^STORAGE_URL=' .env | cut -d '=' -f2)
    if [[ $STORAGE_URL == *"/laravel/"* ]]; then
        SUBDIRECTORY="/laravel"
        echo "✅ Detected subdirectory installation: $SUBDIRECTORY"
    fi
fi

# Clear caches
echo ""
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Check current route registration
echo ""
echo "🔍 Current Swagger routes:"
php artisan route:list | grep -E "(swagger|documentation)" | head -5

# Generate docs
echo ""
echo "📝 Regenerating Swagger documentation..."
php artisan l5-swagger:generate

# Test route accessibility
echo ""
echo "🧪 Testing route generation..."
php artisan tinker --execute="
\$url = route('l5-swagger.default.api');
echo 'Route URL: ' . \$url . PHP_EOL;
echo 'Full URL: ' . url('api/documentation') . PHP_EOL;
"

# Cache for production
echo ""
echo "⚡ Caching for production..."
php artisan config:cache
php artisan route:cache

echo ""
echo "✅ Fix complete!"
echo ""
if [ -n "$SUBDIRECTORY" ]; then
    echo "📚 Try accessing Swagger at:"
    echo "   https://app.bomeqp.com$SUBDIRECTORY/api/documentation"
    echo ""
    echo "   (Note: Include the $SUBDIRECTORY prefix)"
else
    echo "📚 Try accessing Swagger at:"
    echo "   https://app.bomeqp.com/api/documentation"
fi
echo ""
echo "💡 If still getting 404, check:"
echo "   1. Server document root configuration"
echo "   2. .htaccess rewrite rules"
echo "   3. Run: bash diagnose-swagger-404.sh"

