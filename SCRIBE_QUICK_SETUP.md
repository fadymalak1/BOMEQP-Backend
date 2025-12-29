# Scribe API Documentation - Quick Setup Guide

Quick reference for setting up Scribe on your production server.

## 🚀 Quick Setup (5 Steps)

### Step 1: Publish Configuration & Assets

```bash
cd ~/public_html/v1

# Publish Scribe config (if not already published)
php artisan vendor:publish --tag=scribe-config --force

# Publish Scribe assets (CSS, JS, images)
php artisan vendor:publish --tag=scribe-assets --force
```

### Step 2: Verify Configuration

Check `config/scribe.php` - it should have:
```php
'base_url' => env('SCRIBE_BASE_URL', 'https://aeroenix.com/v1'),
```

Or set in `.env`:
```env
SCRIBE_BASE_URL=https://aeroenix.com/v1
```

### Step 3: Generate Documentation

```bash
# Clear caches first
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Generate documentation
php artisan scribe:generate
```

### Step 4: Set Permissions

```bash
# Ensure assets are accessible
chmod -R 755 public/vendor/scribe
chmod -R 755 public/docs
```

### Step 5: Access Documentation

Open in browser:
- **HTML Docs:** `https://aeroenix.com/v1/docs`
- **OpenAPI:** `https://aeroenix.com/v1/docs/openapi.yaml`
- **Postman:** `https://aeroenix.com/v1/docs/postman.json`
- **Redirect:** `https://aeroenix.com/v1/api/doc` → redirects to `/docs`

---

## ✅ Verification Checklist

- [ ] Config file exists: `config/scribe.php`
- [ ] Assets published: `public/vendor/scribe/` exists
- [ ] Documentation generated: `public/docs/index.html` exists
- [ ] Route added: `/api/doc` redirects to `/docs`
- [ ] Base URL configured: `https://aeroenix.com/v1`
- [ ] Permissions set: Assets are readable

---

## 🔄 Regenerating Documentation

After adding new endpoints or updating annotations:

```bash
php artisan scribe:generate
php artisan config:clear
```

---

## 📝 Adding Annotations

Add PHPDoc comments to your controllers:

```php
/**
 * Get user profile
 *
 * @group User Management
 * @authenticated
 * 
 * @response 200 {
     *   "id": 1,
     *   "name": "John Doe"
     * }
 */
public function profile(Request $request)
{
    return $request->user();
}
```

---

## 🐛 Troubleshooting

### Assets not loading?
```bash
php artisan vendor:publish --tag=scribe-assets --force
php artisan config:clear
```

### Routes not appearing?
```bash
php artisan route:clear
php artisan scribe:generate
```

### Wrong base URL?
Update `config/scribe.php` or `.env`:
```php
'base_url' => 'https://aeroenix.com/v1',
```

---

## 📚 Full Documentation

See `SCRIBE_SERVER_DEPLOYMENT_GUIDE.md` for complete setup instructions.

