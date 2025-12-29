# Restore Swagger API Documentation

I've restored Swagger/OpenAPI (l5-swagger) configuration. Here's what you need to do on your server:

## Steps to Complete Setup

### 1. Remove Scribe and Install Swagger

```bash
# Remove Scribe
composer remove knuckleswtf/scribe

# Install Swagger
composer require darkaonline/l5-swagger
```

### 2. Publish Swagger Configuration

```bash
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### 3. Publish Swagger Assets

```bash
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-assets
```

### 4. Generate Documentation

```bash
php artisan l5-swagger:generate
```

### 5. Clear Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Access Documentation

After setup, your Swagger documentation will be available at:

**`https://aeroenix.com/v1/api/doc`**

## What Has Been Changed

1. ✅ **composer.json** - Changed back to `darkaonline/l5-swagger`
2. ✅ **config/l5-swagger.php** - Created Swagger configuration
3. ✅ **app/Http/Controllers/Controller.php** - Added OpenAPI Info attributes
4. ✅ **app/Http/Controllers/API/AuthController.php** - Replaced Scribe PHPDoc with OpenAPI attributes
5. ✅ **routes/web.php** - Removed Scribe asset route

## Configuration Notes

The Swagger config is set to:
- Serve docs at: `/api/doc`
- Base URL: `https://aeroenix.com/v1` (set in config)
- Authentication: Sanctum Bearer token
- Scan controllers in: `app/Http/Controllers`

## Adding Documentation to New Endpoints

Use OpenAPI attributes like this:

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: "/api/your-endpoint",
    summary: "Endpoint summary",
    tags: ["YourTag"],
    security: [["sanctum" => []]],
    responses: [
        new OA\Response(response: 200, description: "Success"),
    ]
)]
public function yourMethod() { }
```

After adding attributes, regenerate:
```bash
php artisan l5-swagger:generate
```

## Troubleshooting

If you get 404 errors:
1. Make sure you've published assets: `php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-assets`
2. Check that docs are generated: `php artisan l5-swagger:generate`
3. Clear all caches
4. Verify route exists: `php artisan route:list | grep doc`

