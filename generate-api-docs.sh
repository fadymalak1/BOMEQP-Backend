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

# Generate documentation
echo "📝 Generating documentation..."
php artisan scribe:generate

# Check if generation was successful
if [ -f "public/docs/index.html" ]; then
    echo ""
    echo "✅ Documentation generated successfully!"
    echo ""
    echo "📄 Access your docs at:"
    echo "   🌐 Production: https://aeroenix.com/v1/docs"
    echo "   🔗 Alternative: https://aeroenix.com/v1/api/doc (redirects to /docs)"
    echo ""
    echo "📊 Generated files:"
    echo "   - HTML: public/docs/index.html"
    echo "   - OpenAPI: storage/app/private/scribe/openapi.yaml"
    echo "   - Postman: storage/app/private/scribe/collection.json"
    echo ""
    echo "💡 Tip: Run this script again after adding new API endpoints to update the docs!"
else
    echo ""
    echo "❌ Error: Documentation generation failed!"
    echo "Please check the errors above and try again."
    exit 1
fi

