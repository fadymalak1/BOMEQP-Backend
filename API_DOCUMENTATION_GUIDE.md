# BOMEQP API Documentation Guide

## 📚 Overview

This project uses [Scribe](https://scribe.knuckles.wtf/) to automatically generate beautiful, interactive API documentation from your Laravel routes and controllers.

## 🎨 Features

- **Automatic Documentation**: Generates docs from your code annotations
- **Beautiful UI**: Modern, responsive design with "Try It Out" feature
- **Multiple Formats**: HTML, OpenAPI (Swagger), and Postman collection
- **Auto-Updates**: Regenerate docs whenever you add new endpoints

## 🔗 Access Documentation

- **Local**: `http://localhost:8000/docs` or `http://localhost:8000/api/doc`
- **Production**: `https://aeroenix.com/v1/docs` or `https://aeroenix.com/v1/api/doc`

## 🚀 Quick Start

### Generate Documentation (First Time)

**On Server:**
```bash
cd ~/public_html/v1
chmod +x generate-api-docs.sh
./generate-api-docs.sh
```

**Or manually:**
```bash
cd ~/public_html/v1
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan scribe:generate
```

### Auto-Update After Adding New Endpoints

**Option 1: Run the script**
```bash
cd ~/public_html/v1
./generate-api-docs.sh
```

**Option 2: Manual command**
```bash
php artisan scribe:generate
```

**Option 3: Add to deployment script**
Add this to your deployment script so docs auto-update on every deploy:
```bash
php artisan scribe:generate
```

## 📝 How to Document Your APIs

### Basic Endpoint Documentation

Add PHPDoc comments to your controller methods:

```php
/**
 * Get user profile
 * 
 * Get the authenticated user's profile information.
 * 
 * @group Authentication
 * @authenticated
 * 
 * @response 200 {
 *   "user": {
 *     "id": 1,
 *     "name": "John Doe",
 *     "email": "john@example.com"
 *   }
 * }
 */
public function profile(Request $request)
{
    return $request->user();
}
```

### Documenting Request Parameters

```php
/**
 * Create a new course
 * 
 * @group Courses
 * @authenticated
 * 
 * @bodyParam name string required Course name. Example: Advanced Fire Safety
 * @bodyParam code string required Course code. Example: AFS-001
 * @bodyParam duration_hours integer required Duration in hours. Example: 40
 * @bodyParam level string required Course level. Example: advanced
 * 
 * @response 201 {
 *   "message": "Course created successfully",
 *   "course": {...}
 * }
 */
public function store(Request $request)
{
    // Your code here
}
```

### Documenting URL Parameters

```php
/**
 * Get course details
 * 
 * @group Courses
 * @authenticated
 * 
 * @urlParam id integer required Course ID. Example: 1
 * 
 * @response 200 {
 *   "course": {...}
 * }
 */
public function show($id)
{
    // Your code here
}
```

### Documenting Query Parameters

```php
/**
 * List courses
 * 
 * @group Courses
 * @authenticated
 * 
 * @queryParam page integer Page number. Example: 1
 * @queryParam per_page integer Items per page. Example: 15
 * @queryParam status string Filter by status. Example: active
 * 
 * @response 200 {
 *   "courses": [...],
 *   "total": 100
 * }
 */
public function index(Request $request)
{
    // Your code here
}
```

### Grouping Endpoints

Use `@group` annotation to organize endpoints:

```php
/**
 * @group Authentication
 */
class AuthController extends Controller
{
    // All methods in this controller will be grouped under "Authentication"
}
```

Or specify per method:
```php
/**
 * @group Courses
 */
public function index() { }
```

## 🎯 Best Practices

1. **Always add descriptions**: Help developers understand what each endpoint does
2. **Include examples**: Use `Example:` in parameter descriptions
3. **Document all parameters**: Include all required and optional parameters
4. **Show responses**: Use `@response` to show example responses
5. **Group related endpoints**: Use `@group` to organize endpoints logically
6. **Mark authentication**: Use `@authenticated` for protected endpoints

## 🔄 Auto-Update Workflow

### After Adding a New Endpoint:

1. **Add your route** in `routes/api.php`
2. **Create/update controller** with PHPDoc annotations
3. **Regenerate documentation**:
   ```bash
   php artisan scribe:generate
   ```
4. **View updated docs** at `/docs`

### Recommended: Add to Git Hooks

Create a post-merge hook to auto-update docs:

```bash
# .git/hooks/post-merge
#!/bin/bash
php artisan scribe:generate
```

Or add to your deployment pipeline.

## 📂 Generated Files

After running `php artisan scribe:generate`, these files are created:

- `public/docs/index.html` - Main documentation (HTML)
- `public/docs/openapi.yaml` - OpenAPI/Swagger specification
- `public/docs/postman.json` - Postman collection
- `public/vendor/scribe/` - CSS, JS, and assets

## 🛠️ Configuration

Configuration is in `config/scribe.php`. Key settings:

- `base_url`: API base URL (currently: `https://aeroenix.com/v1`)
- `theme`: UI theme (`elements` for modern design)
- `try_it_out`: Enable interactive testing
- `example_languages`: Languages for code examples

## 🐛 Troubleshooting

### Documentation not updating?
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan scribe:generate
```

### 404 errors on `/docs`?
- Ensure routes are set up in `routes/web.php`
- Check file permissions: `chmod -R 755 public/docs`

### Missing endpoints?
- Check that routes are in `routes/api.php`
- Verify route prefixes match `config/scribe.php` settings
- Ensure routes aren't excluded in config

### Try It Out not working?
- Verify `base_url` is correct in `config/scribe.php`
- Check CORS settings if testing from different domain
- Ensure API routes are accessible

## 📖 More Information

- [Scribe Documentation](https://scribe.knuckles.wtf/)
- [Laravel Documentation](https://laravel.com/docs)

## 🎉 That's It!

Your API documentation is now set up and ready to use. Just remember to regenerate it whenever you add new endpoints!

