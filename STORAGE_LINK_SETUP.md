# Storage Link Setup Guide

If you encounter errors when running `php artisan storage:link`, follow these steps:

## Method 1: Using the Helper Script (Recommended)

1. Upload `create_storage_link.php` to your server root directory
2. Run: `php create_storage_link.php`
3. This will automatically:
   - Create the required directories
   - Create the symbolic link properly

## Method 2: Manual Setup

### Step 1: Ensure directories exist

```bash
# Create storage directory if it doesn't exist
mkdir -p storage/app/public

# Ensure public directory exists
mkdir -p public

# Create authorization subdirectory for uploads
mkdir -p storage/app/public/authorization
```

### Step 2: Create the symlink

Try these commands in order:

**Option A: Relative path (recommended)**
```bash
cd public
ln -s ../storage/app/public storage
cd ..
```

**Option B: Absolute path**
```bash
ln -s /full/path/to/your/project/storage/app/public /full/path/to/your/project/public/storage
```

**Option C: Using PHP artisan with force**
```bash
php artisan storage:link --force
```

## Method 3: Alternative Solution (If symlinks don't work)

If your server doesn't support symlinks, you can use an `.htaccess` redirect (Apache) or Nginx configuration:

### For Apache (create `public/storage/.htaccess`):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /storage/
    RewriteRule ^(.*)$ ../../storage/app/public/$1 [L]
</IfModule>
```

### For Nginx (add to your server config):

```nginx
location /storage {
    alias /path/to/your/project/storage/app/public;
    try_files $uri $uri/ =404;
}
```

## Method 4: Update Storage Configuration (Not Recommended)

As a last resort, you can change the storage disk to use the `public` directory directly:

**Update `config/filesystems.php`:**
```php
'public' => [
    'driver' => 'local',
    'root' => public_path('storage'), // Changed from storage_path('app/public')
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
```

Then ensure `public/storage` directory exists and is writable.

## Verify the Setup

After creating the link, verify it works:

```bash
# Check if the link exists
ls -la public/storage

# Should show something like:
# storage -> ../storage/app/public
```

## Permissions

Make sure the storage directory has proper permissions:

```bash
chmod -R 775 storage/app/public
chown -R www-data:www-data storage/app/public  # Adjust user/group as needed
```

## Troubleshooting

1. **"symlink(): No such file or directory"**
   - Ensure `storage/app/public` directory exists
   - Ensure `public` directory exists
   - Try using relative paths instead of absolute paths

2. **Permission denied**
   - Check file permissions: `ls -la public/`
   - Ensure the web server user has write access
   - Try: `chmod 775 public storage/app/public`

3. **Symlink created but files not accessible**
   - Verify the link: `ls -la public/storage`
   - Check web server configuration allows following symlinks
   - Verify file permissions are correct (755 for directories, 644 for files)

4. **Server doesn't support symlinks**
   - Use Method 3 (`.htaccess` or Nginx config) above
   - Or use Method 4 (direct public storage)

