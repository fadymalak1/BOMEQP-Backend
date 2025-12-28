# API Documentation Quick Reference

## Quick Start

1. **Install the package:**
   ```bash
   composer require darkaonline/l5-swagger
   ```

2. **Publish configuration:**
   ```bash
   php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
   ```

3. **Generate documentation:**
   ```bash
   php artisan l5-swagger:generate
   ```

4. **Access documentation:**
   Visit: `http://your-domain.com/api/doc`

## Adding Documentation to Endpoints

### Minimal Example
```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: "/api/endpoint",
    summary: "Endpoint summary",
    tags: ["TagName"],
    responses: [
        new OA\Response(response: 200, description: "Success"),
    ]
)]
public function myMethod() { }
```

### With Authentication
```php
#[OA\Get(
    path: "/api/endpoint",
    summary: "Endpoint summary",
    tags: ["TagName"],
    security: [["sanctum" => []]],
    responses: [
        new OA\Response(response: 200, description: "Success"),
        new OA\Response(response: 401, description: "Unauthenticated"),
    ]
)]
```

### With Request Body
```php
#[OA\Post(
    path: "/api/endpoint",
    summary: "Create resource",
    tags: ["TagName"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Example"),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: "Created"),
    ]
)]
```

### With Query Parameters
```php
#[OA\Get(
    path: "/api/endpoint",
    summary: "List resources",
    tags: ["TagName"],
    parameters: [
        new OA\Parameter(
            name: "page",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "integer", example: 1)
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: "Success"),
    ]
)]
```

### With Path Parameters
```php
#[OA\Get(
    path: "/api/endpoint/{id}",
    summary: "Get resource",
    tags: ["TagName"],
    parameters: [
        new OA\Parameter(
            name: "id",
            in: "path",
            required: true,
            schema: new OA\Schema(type: "integer"),
            description: "Resource ID"
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: "Success"),
        new OA\Response(response: 404, description: "Not found"),
    ]
)]
```

## Updating Documentation

After adding or modifying endpoints:

```bash
php artisan l5-swagger:generate
```

Then refresh `/api/doc` in your browser.

## Auto-Generate (Development Only)

Add to `.env`:
```env
L5_SWAGGER_GENERATE_ALWAYS=true
```

**Warning:** Set to `false` in production!

## Common HTTP Methods

- `#[OA\Get(...)]` - GET requests
- `#[OA\Post(...)]` - POST requests
- `#[OA\Put(...)]` - PUT requests
- `#[OA\Patch(...)]` - PATCH requests
- `#[OA\Delete(...)]` - DELETE requests

## Response Types

### Simple Response
```php
new OA\Response(response: 200, description: "Success")
```

### Response with JSON Content
```php
new OA\Response(
    response: 200,
    description: "Success",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: "message", type: "string"),
            new OA\Property(property: "data", type: "object"),
        ]
    )
)
```

### Array Response
```php
new OA\Response(
    response: 200,
    description: "Success",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: "items",
                type: "array",
                items: new OA\Items(type: "object")
            ),
        ]
    )
)
```

## Tags

Use tags to group related endpoints:
```php
tags: ["Authentication"]
tags: ["Notifications", "User"]
```

## Examples

See `app/Http/Controllers/API/AuthController.php` for complete examples.

## Troubleshooting

- **Documentation not updating?** Run `php artisan l5-swagger:generate`
- **404 on `/api/doc`?** Make sure you've published vendor assets
- **Attributes not working?** Ensure PHP 8.0+ and correct imports

