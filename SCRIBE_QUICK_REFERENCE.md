# Scribe API Documentation - Quick Reference

## Quick Start

1. **Install the package:**
   ```bash
   composer require knuckleswtf/scribe
   ```

2. **Publish configuration:**
   ```bash
   php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
   ```

3. **Generate documentation:**
   ```bash
   php artisan scribe:generate
   ```

4. **Access documentation:**
   Visit: `http://your-domain.com/api/doc`

## Adding Documentation to Endpoints

### Minimal Example
```php
/**
 * Endpoint summary
 * 
 * Detailed description of what this endpoint does.
 * 
 * @group TagName
 */
public function myMethod() { }
```

### With Authentication
```php
/**
 * Endpoint summary
 * 
 * @group TagName
 * @authenticated
 */
public function myMethod() { }
```

### With Request Body
```php
/**
 * Create resource
 * 
 * @group TagName
 * 
 * @bodyParam name string required Resource name. Example: My Resource
 * @bodyParam description string optional Resource description. Example: A description
 */
public function store(Request $request) { }
```

### With Query Parameters
```php
/**
 * List resources
 * 
 * @group TagName
 * 
 * @queryParam page integer Page number. Example: 1
 * @queryParam per_page integer Items per page. Example: 15
 */
public function index(Request $request) { }
```

### With Path Parameters
```php
/**
 * Get resource
 * 
 * @group TagName
 * 
 * @urlParam id integer required Resource ID. Example: 1
 */
public function show($id) { }
```

## Common Annotations

### Grouping Endpoints
```php
/**
 * @group Authentication
 */
```

### Authentication
```php
/**
 * @authenticated
 */
```

### Request Body
```php
/**
 * @bodyParam field1 string required Description. Example: value
 * @bodyParam field2 integer optional Description. Example: 123
 */
```

### Query Parameters
```php
/**
 * @queryParam page integer Page number. Example: 1
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
 *   "message": "Success",
 *   "data": {...}
 * }
 * @response 404 {
 *   "message": "Not found"
 * }
 */
```

## Updating Documentation

After adding or modifying endpoints:

```bash
php artisan scribe:generate
```

Then refresh `/api/doc` in your browser.

## Configuration

Edit `config/scribe.php` to customize:

- **Route prefix**: Change where docs are served
- **Theme**: Choose between `default` and `elements`
- **Authentication**: Configure Sanctum/Bearer token
- **Routes**: Specify which routes to document

## Advantages Over Swagger

- ✅ **Simpler syntax** - Just PHPDoc comments, no attributes
- ✅ **Laravel-native** - Built specifically for Laravel
- ✅ **Automatic route discovery** - No manual route registration needed
- ✅ **Better for Laravel conventions** - Understands Laravel patterns
- ✅ **Cleaner output** - Beautiful HTML documentation

## Examples

See `app/Http/Controllers/API/AuthController.php` for complete examples.


