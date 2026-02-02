@echo off
REM BOMEQP API Documentation Generator (Windows)
REM This script generates/updates the API documentation automatically

echo 🚀 Generating BOMEQP API Documentation...
echo.

REM Navigate to project directory
cd /d "%~dp0"

REM Clear all caches
echo 📦 Clearing caches...
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

REM Generate documentation
echo 📝 Generating documentation...
php artisan scribe:generate

REM Check if generation was successful
if exist "public\docs\index.html" (
    echo.
    echo ✅ Documentation generated successfully!
    echo.
    echo 📄 Access your docs at:
    echo    🌐 Production: https://aeroenix.com/v1/docs
    echo    🔗 Local: http://localhost:8000/docs
    echo.
    echo 📊 Generated files:
    echo    - HTML: public\docs\index.html
    echo    - OpenAPI: storage\app\private\scribe\openapi.yaml
    echo    - Postman: storage\app\private\scribe\collection.json
    echo.
    echo 💡 Tip: Run this script again after adding new API endpoints to update the docs!
) else (
    echo.
    echo ❌ Error: Documentation generation failed!
    echo Please check the errors above and try again.
    exit /b 1
)

pause

