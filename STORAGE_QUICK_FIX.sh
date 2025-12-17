#!/bin/bash
# Quick fix script for storage symlink issue
# Run this on your server: bash storage_fix.sh

echo "=== Storage Symlink Fix Script ==="
echo ""

# Navigate to Laravel root
cd ~/public_html/v1 || { echo "Error: Cannot find ~/public_html/v1"; exit 1; }

echo "1. Checking current directory..."
pwd

echo ""
echo "2. Checking if storage/app/public exists..."
if [ ! -d "storage/app/public" ]; then
    echo "   Creating storage/app/public directory..."
    mkdir -p storage/app/public
fi

echo ""
echo "3. Checking if public/storage exists..."
if [ -L "public/storage" ]; then
    echo "   Symlink exists. Checking if it's valid..."
    if [ ! -e "public/storage" ]; then
        echo "   Symlink is broken. Removing it..."
        rm public/storage
    else
        echo "   Symlink is valid!"
        ls -la public/storage
        exit 0
    fi
elif [ -d "public/storage" ]; then
    echo "   public/storage exists but is a directory (not a symlink). Removing..."
    rm -rf public/storage
fi

echo ""
echo "4. Creating symlink..."
ln -s ../storage/app/public public/storage

echo ""
echo "5. Verifying symlink..."
if [ -L "public/storage" ] && [ -e "public/storage" ]; then
    echo "   ✓ Symlink created successfully!"
    ls -la public/storage
else
    echo "   ✗ Error: Symlink creation failed"
    exit 1
fi

echo ""
echo "6. Setting permissions..."
chmod -R 775 storage/app/public
chmod 775 public/storage 2>/dev/null || true

echo ""
echo "7. Testing if files are accessible..."
if [ -d "storage/app/public/authorization" ]; then
    echo "   Authorization files found in storage"
    ls -la storage/app/public/authorization/ | head -5
    echo ""
    echo "   Checking via symlink..."
    if [ -d "public/storage/authorization" ]; then
        echo "   ✓ Files accessible via symlink!"
    else
        echo "   ✗ Warning: Files not accessible via symlink"
    fi
fi

echo ""
echo "=== Fix Complete ==="
echo ""
echo "Next steps:"
echo "1. Clear Laravel config cache: php artisan config:clear && php artisan config:cache"
echo "2. Test URL: https://aeroenix.com/v1/storage/authorization/9/6/filename.pdf"
echo "3. If still 404, check web server configuration (document root should be public_html/v1/public)"

