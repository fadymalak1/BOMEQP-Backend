# Scramble API Documentation Setup

Scramble is a modern Laravel API documentation generator with a beautiful UI. It generates OpenAPI 3.0 specification automatically from your Laravel routes.

## Installation Steps

### 1. Install Scramble Package

Run this command on your server:

```bash
composer require dedoc/scramble
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag=scramble-config
```

### 3. Access Documentation

Scramble automatically registers routes. Access the documentation at:

**URL:** `http://your-domain.com/api/doc`

Or if in subdirectory: `https://aeroenix.com/v1/api/doc`

## Features

- ✅ **Beautiful Modern UI** - Clean, professional interface
- ✅ **Automatic Generation** - No manual spec writing needed
- ✅ **Laravel-Native** - Works seamlessly with Laravel routes
- ✅ **OpenAPI 3.0** - Industry standard specification
- ✅ **Interactive** - Try out endpoints directly from the docs
- ✅ **Type Inference** - Automatically understands Laravel validation rules
- ✅ **Response Examples** - Generates example responses automatically

## How It Works

Scramble automatically:
- Scans your Laravel routes
- Analyzes your controllers and request validation
- Generates OpenAPI 3.0 specification
- Provides a beautiful web UI to view and interact with the API

## Documentation Format

Scramble works with standard PHPDoc comments. Your existing Scribe annotations will mostly work, but Scramble also understands Laravel's validation rules automatically.

### Example Controller Method

```php
/**
 * Register a new user
 * 
 * Register a new user (Training Center or ACC Admin).
 * 
 * @group Authentication
 */
public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ]);
    
    // Your logic here
}
```

Scramble will automatically:
- Extract validation rules and convert them to OpenAPI schema
- Determine request/response types
- Generate example requests and responses

## Configuration

Edit `config/scramble.php` (after publishing) to customize:

```php
'api' => [
    'title' => 'BOMEQP API Documentation',
    'version' => '1.0.0',
    'description' => 'Comprehensive API documentation for BOMEQP',
    'servers' => [
        ['url' => 'https://aeroenix.com/v1/api', 'description' => 'Production'],
    ],
],

'ui' => [
    'theme' => 'light', // or 'dark'
    'hide_download_button' => false,
],

'routes' => [
    'api' => '/api/doc', // Documentation route
],
```

## Advantages Over Scribe

- ✅ **Better UI** - More modern and professional interface
- ✅ **Better Type Inference** - Understands Laravel validation automatically
- ✅ **OpenAPI Standard** - Industry-standard OpenAPI 3.0 spec
- ✅ **No Asset Publishing** - Everything is served via Laravel routes
- ✅ **Real-time** - Documentation is generated on-the-fly
- ✅ **Better Performance** - No need to regenerate docs manually

## Troubleshooting

### 404 on `/api/doc`?

Make sure Scramble is installed:
```bash
composer show dedoc/scramble
```

Clear cache:
```bash
php artisan config:clear
php artisan route:clear
```

### Documentation not showing routes?

- Make sure your routes are in `routes/api.php`
- Check that controllers are properly documented
- Verify validation rules are using Laravel's validation

## Next Steps

1. Install Scramble: `composer require dedoc/scramble`
2. Access docs at: `https://aeroenix.com/v1/api/doc`
3. Your existing PHPDoc comments will work
4. Scramble will automatically generate beautiful documentation!

## More Information

- **Documentation:** https://scramble.dedoc.co
- **GitHub:** https://github.com/dedoc/documentation-scramble

