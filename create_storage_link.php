<?php

/**
 * Script to create storage link for Laravel
 * Run this script from the root directory: php create_storage_link.php
 */

$basePath = __DIR__;
$targetPath = $basePath . '/public/storage';
$linkPath = $basePath . '/storage/app/public';

// Ensure storage/app/public directory exists
if (!file_exists($linkPath)) {
    if (!is_dir(dirname($linkPath))) {
        mkdir(dirname($linkPath), 0755, true);
    }
    mkdir($linkPath, 0755, true);
    echo "Created directory: $linkPath\n";
} else {
    echo "Directory exists: $linkPath\n";
}

// Ensure public directory exists
$publicDir = $basePath . '/public';
if (!file_exists($publicDir)) {
    mkdir($publicDir, 0755, true);
    echo "Created directory: $publicDir\n";
}

// Remove existing symlink or file if it exists
if (file_exists($targetPath) || is_link($targetPath)) {
    if (is_link($targetPath)) {
        unlink($targetPath);
        echo "Removed existing symlink: $targetPath\n";
    } elseif (is_dir($targetPath)) {
        rmdir($targetPath);
        echo "Removed existing directory: $targetPath\n";
    }
}

// Create the symlink
$relativePath = '../storage/app/public';
$relativeTargetPath = $basePath . '/public/storage';

// Use relative path for better portability
if (function_exists('symlink')) {
    // Try relative path first
    chdir($publicDir);
    if (symlink($relativePath, 'storage')) {
        echo "✓ Successfully created symlink: public/storage -> storage/app/public\n";
        exit(0);
    }
    
    // If relative path fails, try absolute path
    chdir($basePath);
    if (symlink($linkPath, $targetPath)) {
        echo "✓ Successfully created symlink using absolute path\n";
        exit(0);
    }
    
    echo "✗ Failed to create symlink. Error: " . error_get_last()['message'] . "\n";
    echo "\nAlternative solution: Create a .htaccess file in public/storage that redirects to storage/app/public\n";
    exit(1);
} else {
    echo "✗ symlink() function is not available on this system\n";
    exit(1);
}

