#!/bin/bash

# BOMEQP API Documentation Generator
# This script generates/updates the API documentation automatically

echo "🚀 Generating BOMEQP API Documentation..."
echo ""

# Navigate to project directory
cd "$(dirname "$0")" || exit

# Clear all caches
echo "📦 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Upgrade Scribe configuration if needed
echo "🔄 Checking Scribe configuration..."
if php artisan scribe:upgrade --dry-run &>/dev/null; then
    echo "⚠️  Scribe configuration needs upgrade..."
    php artisan scribe:upgrade --force
    echo "✅ Configuration upgraded"
else
    echo "✅ Configuration is up to date"
fi

# Generate documentation
echo "📝 Generating documentation..."
php artisan scribe:generate

# Check if generation was successful
if [ -f "public/docs/index.html" ]; then
    echo ""
    echo "✅ Documentation generated successfully!"
    echo "📄 Access your docs at: https://aeroenix.com/v1/docs"
    echo ""
    echo "📊 Generated files:"
    echo "   - HTML: public/docs/index.html"
    echo "   - OpenAPI: public/docs/openapi.yaml"
    echo "   - Postman: public/docs/postman.json"
else
    echo ""
    echo "❌ Error: Documentation generation failed!"
    echo "Please check the errors above and try again."
    exit 1
fi

