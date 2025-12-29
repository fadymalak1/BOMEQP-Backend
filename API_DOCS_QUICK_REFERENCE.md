# API Documentation - Quick Reference

## 🚀 Generate Documentation

### On Server (Production)
```bash
cd ~/public_html/v1
php artisan scribe:generate
```

### Using Script (Linux/Mac)
```bash
./generate-api-docs.sh
```

### Using Script (Windows)
```batch
generate-api-docs.bat
```

### Using Composer
```bash
composer docs          # Full generation with cache clear
composer docs:generate # Quick generation only
```

## 📍 Access Documentation

- **Production**: https://aeroenix.com/v1/docs
- **Local**: http://localhost:8000/docs

## 🔄 Auto-Update After Adding Endpoints

**Just run:**
```bash
php artisan scribe:generate
```

**Or use the script:**
```bash
./generate-api-docs.sh  # Linux/Mac
generate-api-docs.bat   # Windows
```

## 📝 Quick Documentation Template

```php
/**
 * Endpoint description
 * 
 * @group Group Name
 * @authenticated
 * 
 * @bodyParam field string required Description. Example: value
 * @urlParam id integer required Description. Example: 1
 * @queryParam page integer Page number. Example: 1
 * 
 * @response 200 {
 *   "data": {...}
 * }
 */
public function methodName(Request $request)
{
    // Your code
}
```

## 🎯 Common Groups

- `@group Authentication`
- `@group Courses`
- `@group Instructors`
- `@group Training Centers`
- `@group ACC`
- `@group Admin`
- `@group Financial`
- `@group Certificates`

## ✅ Checklist After Adding New Endpoint

- [ ] Add route in `routes/api.php`
- [ ] Create/update controller method
- [ ] Add PHPDoc with `@group` annotation
- [ ] Document all parameters (`@bodyParam`, `@urlParam`, `@queryParam`)
- [ ] Add example responses (`@response`)
- [ ] Run `php artisan scribe:generate`
- [ ] Verify in browser at `/docs`

---

**Full Guide**: See `API_DOCUMENTATION_GUIDE.md`

