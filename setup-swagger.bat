@echo off
REM Swagger/OpenAPI Setup Script for BOMEQP (Windows)
REM This script sets up automatic API documentation

echo 🚀 Setting up Swagger/OpenAPI API Documentation...

REM Install the package
echo 📦 Installing darkaonline/l5-swagger package...
composer require darkaonline/l5-swagger

REM Publish configuration
echo ⚙️ Publishing configuration...
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"

REM Create storage directory if it doesn't exist
echo 📁 Creating storage directory...
if not exist "storage\api-docs" mkdir storage\api-docs

REM Generate initial documentation
echo 📝 Generating initial API documentation...
php artisan l5-swagger:generate

REM Clear config cache
echo 🧹 Clearing configuration cache...
php artisan config:clear

echo ✅ Setup complete!
echo.
echo 📚 Access your API documentation at: http://your-domain.com/api/doc
echo.
echo 💡 To update documentation after adding new endpoints:
echo    php artisan l5-swagger:generate
echo.
echo 💡 For auto-generation on each request (development only), add to .env:
echo    L5_SWAGGER_GENERATE_ALWAYS=true

pause

