#!/bin/bash

# Fix Swagger Route Registration Issue

echo "🔧 Fixing Swagger Route Configuration..."

# Clear all caches FIRST (important!)
echo "🧹 Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Generate Swagger documentation
echo "📝 Generating Swagger documentation..."
php artisan l5-swagger:generate

# Verify routes are registered
echo "🔍 Verifying routes..."
php artisan route:list | grep -i "swagger\|documentation"

# Optimize for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache

echo "✅ Done!"
echo ""
echo "📚 Try accessing: https://app.bomeqp.com/api/documentation"
echo ""
echo "💡 If still getting errors, check:"
echo "   1. File permissions on storage/api-docs/"
echo "   2. Server rewrite rules (.htaccess or nginx config)"
echo "   3. Laravel logs: storage/logs/laravel.log"
echo "   4. Verify route exists: php artisan route:list | grep swagger"
echo ""

