#!/bin/bash

# Swagger/OpenAPI Setup Script for BOMEQP
# This script sets up automatic API documentation

echo "🚀 Setting up Swagger/OpenAPI API Documentation..."

# Install the package
echo "📦 Installing darkaonline/l5-swagger package..."
composer require darkaonline/l5-swagger

# Publish configuration
echo "⚙️ Publishing configuration..."
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"

# Create storage directory if it doesn't exist
echo "📁 Creating storage directory..."
mkdir -p storage/api-docs

# Generate initial documentation
echo "📝 Generating initial API documentation..."
php artisan l5-swagger:generate

# Clear config cache
echo "🧹 Clearing configuration cache..."
php artisan config:clear

echo "✅ Setup complete!"
echo ""
echo "📚 Access your API documentation at: http://your-domain.com/api/doc"
echo ""
echo "💡 To update documentation after adding new endpoints:"
echo "   php artisan l5-swagger:generate"
echo ""
echo "💡 For auto-generation on each request (development only), add to .env:"
echo "   L5_SWAGGER_GENERATE_ALWAYS=true"

