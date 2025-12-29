# Swagger/OpenAPI - Quick Reference

## 🚀 Generate Documentation

### On Server (Production)
```bash
cd ~/public_html/v1
php artisan l5-swagger:generate
```

### Using Script (Linux/Mac)
```bash
./generate-swagger-docs.sh
```

### Using Script (Windows)
```batch
generate-swagger-docs.bat
```

### Using Composer
```bash
composer docs          # Full generation with cache clear
composer docs:generate # Quick generation only
```

## 📍 Access Documentation

- **Production**: https://aeroenix.com/v1/api/documentation
- **Local**: http://localhost:8000/api/documentation
- **Alternative URLs**: `/api/doc` or `/api/docs` (redirects to `/api/documentation`)

## 🔄 Auto-Update After Adding Endpoints

**Just run:**
```bash
php artisan l5-swagger:generate
```

**Or use the script:**
```bash
./generate-swagger-docs.sh  # Linux/Mac
generate-swagger-docs.bat   # Windows
```

## 📝 Quick Annotation Template

### Basic GET Endpoint
```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: "/api/endpoint",
    summary: "Endpoint description",
    tags: ["Tag Name"],
    security: [["sanctum" => []]], // If authenticated
    responses: [
        new OA\Response(response: 200, description: "Success")
    ]
)]
public function index() { }
```

### POST with Request Body
```php
#[OA\Post(
    path: "/api/endpoint",
    summary: "Create resource",
    tags: ["Tag Name"],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["field1", "field2"],
            properties: [
                new OA\Property(property: "field1", type: "string", example: "value"),
                new OA\Property(property: "field2", type: "integer", example: 123)
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: "Created"),
        new OA\Response(response: 422, description: "Validation error")
    ]
)]
public function store(Request $request) { }
```

### With URL Parameters
```php
#[OA\Get(
    path: "/api/endpoint/{id}",
    summary: "Get resource by ID",
    tags: ["Tag Name"],
    parameters: [
        new OA\Parameter(
            name: "id",
            in: "path",
            required: true,
            schema: new OA\Schema(type: "integer"),
            example: 1
        )
    ],
    responses: [
        new OA\Response(response: 200, description: "Success"),
        new OA\Response(response: 404, description: "Not found")
    ]
)]
public function show($id) { }
```

## 🎯 Common Tags

- `"Authentication"`
- `"Admin"`
- `"ACC"`
- `"Training Center"`
- `"Instructor"`
- `"Courses"`
- `"Classes"`
- `"Certificates"`
- `"Financial"`

## ✅ Checklist After Adding New Endpoint

- [ ] Add route in `routes/api.php`
- [ ] Create/update controller method
- [ ] Add Swagger annotation (`#[OA\...]`)
- [ ] Document request body (if POST/PUT)
- [ ] Document URL parameters (if any)
- [ ] Document query parameters (if any)
- [ ] Add response examples
- [ ] Run `php artisan l5-swagger:generate`
- [ ] Verify in browser at `/api/documentation`

---

**Full Guide**: See `SWAGGER_SETUP.md`

