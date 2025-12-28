# Swagger/OpenAPI API Documentation Setup

This guide will help you set up automatic API documentation that updates whenever you add new endpoints.

## Installation Steps

### 1. Install the Package

Run the following command in your terminal (make sure you're in the project root):

```bash
composer require darkaonline/l5-swagger
```

### 2. Publish Configuration

After installation, publish the configuration file:

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

### 3. Generate Initial Documentation

Generate the initial API documentation:

```bash
php artisan l5-swagger:generate
```

## Accessing the Documentation

Once set up, you can access the API documentation at:

**URL:** `http://your-domain.com/api/doc`

The Swagger UI will be available at this URL, showing all your API endpoints with interactive documentation.

## How It Works

### Automatic Updates

The documentation automatically scans your controllers for OpenAPI attributes. When you add new endpoints:

1. Add OpenAPI attributes to your controller methods (see examples below)
2. Run `php artisan l5-swagger:generate` to regenerate the documentation
3. Refresh `/api/doc` to see the updated documentation

### Auto-Generate on Request (Optional)

To automatically regenerate documentation on each request (useful for development), set this in your `.env` file:

```env
L5_SWAGGER_GENERATE_ALWAYS=true
```

**Note:** This should be set to `false` in production for performance reasons.

## Adding Documentation to Controllers

### Example: Basic Endpoint

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: "/api/notifications",
    summary: "Get all notifications",
    description: "Retrieve all notifications for the authenticated user",
    tags: ["Notifications"],
    security: [["sanctum" => []]],
    parameters: [
        new OA\Parameter(
            name: "is_read",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "boolean")
        ),
        new OA\Parameter(
            name: "type",
            in: "query",
            required: false,
            schema: new OA\Schema(type: "string")
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "Success",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "success", type: "boolean"),
                    new OA\Property(property: "notifications", type: "array", items: new OA\Items(type: "object")),
                ]
            )
        ),
        new OA\Response(response: 401, description: "Unauthenticated"),
    ]
)]
public function index(Request $request)
{
    // Your controller logic
}
```

### Example: POST Endpoint with Request Body

```php
#[OA\Post(
    path: "/api/notifications/mark-all-read",
    summary: "Mark all notifications as read",
    description: "Mark all unread notifications as read for the authenticated user",
    tags: ["Notifications"],
    security: [["sanctum" => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: "Success",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "success", type: "boolean"),
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "updated_count", type: "integer"),
                ]
            )
        ),
        new OA\Response(response: 401, description: "Unauthenticated"),
    ]
)]
public function markAllAsRead(Request $request)
{
    // Your controller logic
}
```

### Example: Endpoint with Path Parameter

```php
#[OA\Get(
    path: "/api/notifications/{id}",
    summary: "Get single notification",
    description: "Get details of a specific notification",
    tags: ["Notifications"],
    security: [["sanctum" => []]],
    parameters: [
        new OA\Parameter(
            name: "id",
            in: "path",
            required: true,
            schema: new OA\Schema(type: "integer"),
            description: "Notification ID"
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "Success",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "success", type: "boolean"),
                    new OA\Property(property: "notification", type: "object"),
                ]
            )
        ),
        new OA\Response(response: 404, description: "Not found"),
        new OA\Response(response: 401, description: "Unauthenticated"),
    ]
)]
public function show($id)
{
    // Your controller logic
}
```

## Common OpenAPI Attributes

### Request Body
```php
requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ["field1", "field2"],
        properties: [
            new OA\Property(property: "field1", type: "string", example: "value"),
            new OA\Property(property: "field2", type: "integer", example: 123),
        ]
    )
)
```

### Query Parameters
```php
parameters: [
    new OA\Parameter(
        name: "page",
        in: "query",
        required: false,
        schema: new OA\Schema(type: "integer", example: 1)
    ),
]
```

### Path Parameters
```php
parameters: [
    new OA\Parameter(
        name: "id",
        in: "path",
        required: true,
        schema: new OA\Schema(type: "integer"),
        description: "Resource ID"
    ),
]
```

### Security (Authentication)
```php
security: [["sanctum" => []]]
```

### Tags
```php
tags: ["Notifications", "Authentication"]
```

## Workflow for Adding New Endpoints

1. **Create your controller method** with the endpoint logic
2. **Add OpenAPI attributes** above the method (see examples above)
3. **Run** `php artisan l5-swagger:generate` to regenerate documentation
4. **Visit** `/api/doc` to see your new endpoint in the documentation

## Configuration

The configuration file is located at `config/l5-swagger.php`. Key settings:

- **`generate_always`**: Set to `true` in development to auto-generate on each request
- **`paths.annotations`**: Controllers directory to scan (default: `app/Http/Controllers`)
- **`defaults.routes.docs`**: Documentation URL path (default: `api/doc`)

## Troubleshooting

### Documentation not updating?
- Make sure you've run `php artisan l5-swagger:generate`
- Clear Laravel cache: `php artisan config:clear`
- Check that your OpenAPI attributes are correctly formatted

### 404 on `/api/doc`?
- Make sure you've published the vendor assets: `php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"`
- Check that the route is registered (should be automatic)

### Attributes not recognized?
- Make sure you've imported `use OpenApi\Attributes as OA;`
- Verify you're using PHP 8.0+ (attributes require PHP 8.0+)

## Next Steps

1. Add OpenAPI attributes to all your controllers
2. Set up a script or hook to auto-generate documentation on deployment
3. Consider adding more detailed descriptions and examples to your endpoints
4. Use the "Try it out" feature in Swagger UI to test your endpoints directly

## Example: Complete Controller with Documentation

See `app/Http/Controllers/API/AuthController.php` for examples of documented endpoints.

