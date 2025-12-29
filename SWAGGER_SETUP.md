# Swagger/OpenAPI Documentation Setup Guide

## 🚀 Installation Steps (Run on Server)

### Step 1: Install L5-Swagger
```bash
cd ~/public_html/v1
composer require darkaonline/l5-swagger
```

### Step 2: Publish Configuration
```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

### Step 3: Publish Assets
```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-assets
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" --tag=l5-swagger-views
```

### Step 4: Generate Documentation
```bash
php artisan l5-swagger:generate
```

### Step 5: Clear Caches
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## 📍 Access Documentation

- **Production**: `https://aeroenix.com/v1/api/documentation`
- **Local**: `http://localhost:8000/api/documentation`

## 🔄 Auto-Update After Adding Endpoints

After adding new API endpoints, run:
```bash
php artisan l5-swagger:generate
```

Or use the script:
```bash
./generate-swagger-docs.sh
```

## 📝 How to Document APIs

### Basic Example

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: "/api/auth/profile",
    summary: "Get user profile",
    tags: ["Authentication"],
    security: [["sanctum" => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: "User profile",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "user", type: "object")
                ]
            )
        )
    ]
)]
public function profile(Request $request)
{
    return response()->json(['user' => $request->user()]);
}
```

### With Request Body

```php
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
        )
    ]
)]
```

## 🎯 Key Features

- ✅ Beautiful Swagger UI
- ✅ Interactive "Try It Out" feature
- ✅ Automatic OpenAPI spec generation
- ✅ Authentication support (Sanctum)
- ✅ Request/Response examples
- ✅ Auto-updates when you regenerate

## 📚 More Information

- [L5-Swagger Documentation](https://github.com/DarkaOnLine/L5-Swagger)
- [OpenAPI Specification](https://swagger.io/specification/)
