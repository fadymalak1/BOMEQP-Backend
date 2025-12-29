@echo off
REM BOMEQP Swagger/OpenAPI Documentation Generator (Windows)
REM This script generates/updates the Swagger API documentation automatically

echo 🚀 Generating BOMEQP Swagger API Documentation...
echo.

REM Navigate to project directory
cd /d "%~dp0"

REM Clear all caches
echo 📦 Clearing caches...
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

REM Generate Swagger documentation
echo 📝 Generating Swagger documentation...
php artisan l5-swagger:generate

REM Check if generation was successful
if exist "storage\api-docs\api-docs.json" (
    echo.
    echo ✅ Documentation generated successfully!
    echo.
    echo 📄 Access your docs at:
    echo    🌐 Production: https://aeroenix.com/v1/api/documentation
    echo    🔗 Local: http://localhost:8000/api/documentation
    echo.
    echo 📊 Generated files:
    echo    - JSON: storage\api-docs\api-docs.json
    echo    - UI: resources\views\vendor\l5-swagger\index.blade.php
    echo.
    echo 💡 Tip: Run this script again after adding new API endpoints to update the docs!
) else (
    echo.
    echo ❌ Error: Documentation generation failed!
    echo Please check the errors above and try again.
    exit /b 1
)

pause

