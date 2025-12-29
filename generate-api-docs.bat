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

REM Upgrade Scribe configuration if needed
echo 🔄 Checking Scribe configuration...
php artisan scribe:upgrade --force

REM Generate documentation
echo 📝 Generating documentation...
php artisan scribe:generate

REM Check if generation was successful
if exist "public\docs\index.html" (
    echo.
    echo ✅ Documentation generated successfully!
    echo 📄 Access your docs at: http://localhost:8000/docs
    echo.
    echo 📊 Generated files:
    echo    - HTML: public\docs\index.html
    echo    - OpenAPI: public\docs\openapi.yaml
    echo    - Postman: public\docs\postman.json
) else (
    echo.
    echo ❌ Error: Documentation generation failed!
    echo Please check the errors above and try again.
    exit /b 1
)

pause

