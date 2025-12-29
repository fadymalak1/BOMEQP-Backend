#!/bin/bash

# BOMEQP Swagger/OpenAPI Documentation Generator
# This script generates/updates the Swagger API documentation automatically

echo "🚀 Generating BOMEQP Swagger API Documentation..."
echo ""

# Navigate to project directory
cd "$(dirname "$0")" || exit

# Clear all caches
echo "📦 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# Generate Swagger documentation
echo "📝 Generating Swagger documentation..."
php artisan l5-swagger:generate

# Check if generation was successful
if [ -f "storage/api-docs/api-docs.json" ]; then
    echo ""
    echo "✅ Documentation generated successfully!"
    echo ""
    echo "📄 Access your docs at:"
    echo "   🌐 Production: https://aeroenix.com/v1/api/documentation"
    echo "   🔗 Alternative: https://aeroenix.com/v1/api/doc (redirects)"
    echo ""
    echo "📊 Generated files:"
    echo "   - JSON: storage/api-docs/api-docs.json"
    echo "   - UI: resources/views/vendor/l5-swagger/index.blade.php"
    echo ""
    echo "💡 Tip: Run this script again after adding new API endpoints to update the docs!"
else
    echo ""
    echo "❌ Error: Documentation generation failed!"
    echo "Please check the errors above and try again."
    exit 1
fi

