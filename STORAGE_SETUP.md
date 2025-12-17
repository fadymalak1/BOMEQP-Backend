# Storage Setup Guide

## Issue: Storage Link and URL Configuration

If you're experiencing issues with storage links or incorrect storage URLs, follow these steps:

### Problem 1: Storage Link Creation Fails

**Error**: `symlink(): No such file or directory`

**Solution**: The storage link creation fails when the target directory doesn't exist. Run these commands on your server:

```bash
# Navigate to your Laravel application directory
cd ~/public_html/v1

# Ensure the storage/app/public directory exists
mkdir -p storage/app/public

# Ensure the public directory exists
mkdir -p public

# Now create the symlink
php artisan storage:link
```

If the symlink command still fails, you can create it manually:

```bash
# Remove existing link if it exists
rm -f public/storage

# Create the symlink manually
ln -s ../storage/app/public public/storage
```

### Problem 2: Storage URL Missing Subdirectory

**Issue**: Storage URLs are generating as `https://aeroenix.com/api/storage/...` but should be `https://aeroenix.com/v1/api/storage/...`

**Solution**: Update your `.env` file:

```env
# Update APP_URL to include the /v1 subdirectory
APP_URL=https://aeroenix.com/v1/api

# OR set a specific STORAGE_URL (recommended)
STORAGE_URL=https://aeroenix.com/v1/api/storage
```

After updating `.env`, clear the config cache:

```bash
php artisan config:clear
php artisan config:cache
```

### Manual Symlink Creation (Alternative)

If `php artisan storage:link` continues to fail, you can create the symlink manually using SSH:

```bash
cd ~/public_html/v1/public
ln -s ../storage/app/public storage
```

Or using absolute paths:

```bash
ln -s /home/username/public_html/v1/storage/app/public /home/username/public_html/v1/public/storage
```

### Verify the Setup

1. **Check if symlink exists**:
   ```bash
   ls -la public/storage
   ```

2. **Test file upload** and verify the URL includes `/v1`:
   ```
   https://aeroenix.com/v1/api/storage/authorization/7/5/filename.pdf
   ```

3. **Check file permissions**:
   ```bash
   chmod -R 775 storage
   chmod -R 775 public/storage
   ```

### Environment Variables

Add to your `.env` file:

```env
# Base application URL (include subdirectory if applicable)
APP_URL=https://aeroenix.com/v1/api

# Storage URL (optional - defaults to APP_URL/storage)
STORAGE_URL=https://aeroenix.com/v1/api/storage
```

### Notes

- The storage link creates a symbolic link from `public/storage` to `storage/app/public`
- Files uploaded to `storage/app/public` will be accessible via the `public/storage` URL
- Ensure your web server has permission to follow symbolic links (usually enabled by default)

