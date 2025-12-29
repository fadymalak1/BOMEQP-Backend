# Swagger/OpenAPI (L5-Swagger) Installation Guide

## 📋 Prerequisites

- Laravel 11+
- PHP 8.2+
- Composer installed

## 🚀 Complete Installation Steps

### Step 1: Install L5-Swagger Package

```bash
cd ~/public_html/v1
composer require darkaonline/l5-swagger
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

This creates `config/l5-swagger.php`

### Step 3: Publish Assets and Views

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-assets
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-views
```

### Step 4: Configure L5-Swagger

Edit `config/l5-swagger.php` and set:

```php
'default' => 'default',
'defaults' => [
    'routes' => [
        'api' => 'api/documentation',
    ],
    'paths' => [
        'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', false),
        'docs_json' => 'api-docs.json',
        'docs_yaml' => 'api-docs.yaml',
        'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
        'annotations' => [
            app_path(),
        ],
    ],
],
```

### Step 5: Generate Documentation

```bash
php artisan l5-swagger:generate
```

### Step 6: Clear Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### Step 7: Access Documentation

Visit: `https://aeroenix.com/v1/api/documentation`

## 🔧 Configuration for Subdirectory Deployment

If your Laravel app is in a subdirectory (`/v1/`), update `config/l5-swagger.php`:

```php
'routes' => [
    'api' => 'api/documentation',
    'docs' => 'docs',
],
'paths' => [
    'base' => env('L5_SWAGGER_BASE_PATH', null),
    'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
    'docs' => storage_path('api-docs'),
    'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
    'annotations' => [
        app_path(),
    ],
],
```

## 📝 Adding Swagger Annotations

### Example: Login Endpoint

```php
use OpenApi\Attributes as OA;

#[OA\Post(
    path: "/api/auth/login",
    summary: "User login",
    tags: ["Authentication"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "password123")
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: "Login successful",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Login successful"),
                    new OA\Property(property: "user", type: "object"),
                    new OA\Property(property: "token", type: "string")
                ]
            )
        ),
        new OA\Response(response: 422, description: "Invalid credentials")
    ]
)]
public function login(Request $request)
{
    // Your code
}
```

## 🔄 Auto-Update Workflow

After adding new endpoints:

1. Add Swagger annotations to your controller methods
2. Run: `php artisan l5-swagger:generate`
3. Documentation updates automatically

Or use the script:
```bash
./generate-swagger-docs.sh
```

## 🎨 Features

- ✅ Beautiful Swagger UI
- ✅ Interactive "Try It Out" feature
- ✅ Authentication support (Sanctum Bearer tokens)
- ✅ Request/Response examples
- ✅ OpenAPI 3.0 specification
- ✅ Export to JSON/YAML
- ✅ Auto-discovery of endpoints

## 🐛 Troubleshooting

### Documentation not generating?

```bash
# Check if annotations are being scanned
php artisan l5-swagger:generate --verbose

# Ensure app_path() is in annotations array
# Check config/l5-swagger.php
```

### 404 on `/api/documentation`?

```bash
# Clear route cache
php artisan route:clear

# Check routes are registered
php artisan route:list | grep documentation
```

### Assets not loading?

```bash
# Re-publish assets
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-assets --force
```

## 📚 Resources

- [L5-Swagger GitHub](https://github.com/DarkaOnLine/L5-Swagger)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)

## ✅ Verification Checklist

- [ ] L5-Swagger installed via Composer
- [ ] Configuration published
- [ ] Assets published
- [ ] Base Controller has Swagger annotations
- [ ] Documentation generated successfully
- [ ] Accessible at `/api/documentation`
- [ ] "Try It Out" feature works
- [ ] Authentication works in Swagger UI

---

**Quick Reference**: See `SWAGGER_QUICK_REFERENCE.md`

