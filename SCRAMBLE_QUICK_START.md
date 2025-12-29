# Scramble Quick Start Guide

## Installation

```bash
composer require dedoc/scramble
```

That's it! Scramble will automatically register the route.

## Access Documentation

**URL:** `https://aeroenix.com/v1/api/doc`

## How Scramble Works

Scramble automatically:
- ✅ Scans your `routes/api.php` file
- ✅ Analyzes controller methods and validation rules
- ✅ Generates OpenAPI 3.0 specification
- ✅ Provides beautiful web UI

## Documentation Format

Your existing PHPDoc comments work with Scramble:

```php
/**
 * Register a new user
 * 
 * @group Authentication
 */
public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email',
    ]);
    // ...
}
```

Scramble automatically converts Laravel validation rules to OpenAPI schema!

## Configuration (Optional)

To customize, publish config:

```bash
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag=scramble-config
```

Then edit `config/scramble.php`:

```php
'api' => [
    'title' => 'BOMEQP API Documentation',
    'version' => '1.0.0',
],

'routes' => [
    'api' => '/api/doc',
],
```

## Advantages

- 🎨 **Beautiful UI** - Modern, professional interface
- ⚡ **Fast** - No manual generation needed
- 🔄 **Real-time** - Always up-to-date with your code
- 📦 **No Assets** - Everything served via Laravel
- 🎯 **Smart** - Understands Laravel validation automatically

## That's It!

Just install and access `/api/doc` - Scramble handles everything else!

