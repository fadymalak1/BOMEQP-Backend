# Scribe API Documentation Setup

This guide will help you set up Scribe for automatic API documentation that updates whenever you add new endpoints.

## Installation Steps

### 1. Install the Package

Run the following command in your terminal:

```bash
composer require knuckleswtf/scribe
```

### 2. Publish Configuration

After installation, publish the configuration file:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

### 3. Generate Documentation

Generate the initial API documentation:

```bash
php artisan scribe:generate
```

## Accessing the Documentation

Once set up, you can access the API documentation at:

**URL:** `http://your-domain.com/api/doc`

The documentation will be available as a beautiful HTML page with interactive examples.

## How It Works

### Automatic Updates

Scribe automatically scans your routes and controllers. When you add new endpoints:

1. Add PHPDoc comments to your controller methods (see examples below)
2. Run `php artisan scribe:generate` to regenerate the documentation
3. Refresh `/api/doc` to see the updated documentation

### Configuration

The configuration file is located at `config/scribe.php`. Key settings:

- **`routes`**: Configure which routes to document
- **`auth`**: Configure authentication method (Sanctum is already set up)
- **`theme`**: Choose between `default` and `elements` themes
- **`base_url`**: Set your API base URL

## Adding Documentation to Controllers

### Example: Basic Endpoint

```php
/**
 * Get all notifications
 * 
 * Retrieve all notifications for the authenticated user.
 * 
 * @group Notifications
 * @authenticated
 * 
 * @queryParam is_read boolean Filter by read/unread status. Example: false
 * @queryParam type string Filter by notification type. Example: acc_approved
 * @queryParam per_page integer Items per page. Example: 15
 * 
 * @response 200 {
 *   "success": true,
 *   "notifications": [...],
 *   "pagination": {...},
 *   "unread_count": 12
 * }
 */
public function index(Request $request)
{
    // Your controller logic
}
```

### Example: POST Endpoint with Request Body

```php
/**
 * Mark all notifications as read
 * 
 * Mark all unread notifications as read for the authenticated user.
 * 
 * @group Notifications
 * @authenticated
 * 
 * @response 200 {
 *   "success": true,
 *   "message": "15 notification(s) marked as read",
 *   "updated_count": 15
 * }
 */
public function markAllAsRead(Request $request)
{
    // Your controller logic
}
```

### Example: Endpoint with Path Parameter

```php
/**
 * Get single notification
 * 
 * Get details of a specific notification.
 * 
 * @group Notifications
 * @authenticated
 * 
 * @urlParam id integer required Notification ID. Example: 1
 * 
 * @response 200 {
 *   "success": true,
 *   "notification": {...}
 * }
 * @response 404 {
 *   "message": "Notification not found"
 * }
 */
public function show($id)
{
    // Your controller logic
}
```

## Common Scribe Annotations

### Group Endpoints
```php
/**
 * @group Authentication
 */
```

### Mark as Authenticated
```php
/**
 * @authenticated
 */
```

### Request Body Parameters
```php
/**
 * @bodyParam name string required User's name. Example: John Doe
 * @bodyParam email string required User's email. Example: john@example.com
 * @bodyParam age integer optional User's age. Example: 30
 */
```

### Query Parameters
```php
/**
 * @queryParam page integer Page number. Example: 1
 * @queryParam per_page integer Items per page. Example: 15
 */
```

### URL Parameters
```php
/**
 * @urlParam id integer required Resource ID. Example: 1
 */
```

### Response Examples
```php
/**
 * @response 200 {
 *   "data": {...}
 * }
 * @response 404 {
 *   "message": "Not found"
 * }
 */
```

## Workflow for Adding New Endpoints

1. **Create your controller method** with the endpoint logic
2. **Add PHPDoc comments** above the method (see examples above)
3. **Run** `php artisan scribe:generate` to regenerate documentation
4. **Visit** `/api/doc` to see your new endpoint in the documentation

## Advantages of Scribe

- ✅ **No Attributes Required** - Uses simple PHPDoc comments
- ✅ **Automatic Route Discovery** - Scans your routes automatically
- ✅ **Beautiful HTML Output** - Clean, modern documentation interface
- ✅ **Laravel-First** - Built specifically for Laravel
- ✅ **Request/Response Examples** - Automatically generates examples
- ✅ **Interactive Testing** - Can test endpoints directly from docs (if configured)

## Configuration for /api/doc Route

The default route is `/docs`. To change it to `/api/doc`, update `config/scribe.php`:

```php
'routes' => [
    [
        'match' => [
            'prefixes' => ['api/*'],
        ],
        'apply' => [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'response_calls' => [
                'methods' => ['GET'],
            ],
        ],
    ],
],

// And set the docs route:
'routes' => [
    'docs' => [
        'prefix' => 'api',  // Change this
        'uri' => 'doc',     // Change this
    ],
],
```

## Troubleshooting

### Documentation not updating?
- Make sure you've run `php artisan scribe:generate`
- Clear Laravel cache: `php artisan config:clear`
- Check that your PHPDoc comments are correctly formatted

### 404 on `/api/doc`?
- Make sure you've published the assets: `php artisan scribe:generate`
- Check that the route is registered in `config/scribe.php`

### Authentication not working in docs?
- Configure authentication in `config/scribe.php` under the `auth` section
- For Sanctum, set `'driver' => 'sanctum'`

## Next Steps

1. Add PHPDoc comments to all your controllers
2. Set up a script or hook to auto-generate documentation on deployment
3. Customize the theme and styling to match your brand
4. Configure authentication for testing endpoints from the docs

## Example: Complete Controller with Documentation

See `app/Http/Controllers/API/AuthController.php` for examples of documented endpoints using Scribe annotations.


