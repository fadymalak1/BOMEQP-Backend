# Storage 404 Fix - Server Configuration

## Problem
- Files are stored at: `public_html/v1/storage/app/public/authorization/9/6/`
- URL generated: `https://aeroenix.com/v1/storage/authorization/9/6/filename.pdf`
- Result: 404 Not Found

## Root Cause
The symlink from `public/storage` to `storage/app/public` is either missing or broken, OR the web server document root is not correctly configured.

## Solution Steps

### Step 1: Verify/Create the Symlink

SSH into your server and run:

```bash
cd ~/public_html/v1

# Check if symlink exists
ls -la public/storage

# If it doesn't exist or is broken, remove it and recreate
rm -f public/storage
ln -s ../storage/app/public public/storage

# Verify the symlink
ls -la public/storage
# Should show: storage -> ../storage/app/public
```

### Step 2: Verify Web Server Document Root

The web server document root should point to `public_html/v1/public/` (the Laravel public directory), NOT `public_html/v1/`.

**For Apache (.htaccess in public_html/v1/):**
Check if your `.htaccess` in `public_html/v1/` redirects to the `public` directory.

**For Nginx:**
Verify the `root` directive points to:
```
root /home/username/public_html/v1/public;
```

### Step 3: Test the Symlink Manually

```bash
# Test if you can access files via the symlink
ls -la public/storage/authorization/9/6/

# If this works, the symlink is correct
```

### Step 4: Check Permissions

```bash
# Ensure proper permissions
chmod -R 775 storage/app/public
chmod -R 775 public/storage
chown -R www-data:www-data storage/app/public public/storage
# (adjust user/group as needed for your server)
```

### Step 5: Verify Web Server Follows Symlinks

**Apache (.htaccess):**
Make sure `Options +FollowSymLinks` is enabled (should be in Laravel's default .htaccess).

**Nginx:**
Should follow symlinks by default.

### Step 6: Alternative - Check Actual URL Path

If your web server document root is `public_html/v1/` (not `public_html/v1/public/`), then:
- The URL should be: `https://aeroenix.com/public/storage/authorization/9/6/filename.pdf`
- OR you need to configure the web server to serve from the `public` directory

## Quick Diagnostic Commands

```bash
cd ~/public_html/v1

# 1. Check symlink
ls -la public/storage

# 2. Check if file exists in storage
ls -la storage/app/public/authorization/9/6/

# 3. Check if accessible via symlink
ls -la public/storage/authorization/9/6/

# 4. Check web server user
ps aux | grep -E 'apache|nginx|httpd'

# 5. Check permissions
stat public/storage
stat storage/app/public/authorization/9/6/
```

## Temporary Workaround

If the symlink cannot be fixed immediately, you can update the `.env` file to use the direct path:

```env
STORAGE_URL=https://aeroenix.com/v1/public/storage
```

Then clear config cache:
```bash
php artisan config:clear
php artisan config:cache
```

But the proper fix is to ensure the symlink exists and the web server is correctly configured.

