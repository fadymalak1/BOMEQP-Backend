<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Routes - Redirect to /docs
Route::get('/api/doc', function () {
    return redirect('/docs');
});

Route::get('/api/docs', function () {
    return redirect('/docs');
});

// Serve Scribe static documentation
Route::get('/docs', function () {
    $docsPath = public_path('docs/index.html');
    
    if (!file_exists($docsPath)) {
        abort(404, 'Documentation not found. Please run: php artisan scribe:generate');
    }
    
    return response()->file($docsPath);
});

Route::get('/docs/{path}', function ($path) {
    $docsPath = public_path("docs/{$path}");
    
    if (!file_exists($docsPath)) {
        abort(404);
    }
    
    // Determine MIME type
    $extension = pathinfo($docsPath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'yaml' => 'text/yaml',
        'yml' => 'text/yaml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'gif' => 'image/gif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    
    return response()->file($docsPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->where('path', '.*');

// Serve Scribe assets with correct path for subdirectory deployment
Route::get('/vendor/scribe/{path}', function ($path) {
    $assetPath = public_path("vendor/scribe/{$path}");
    
    if (!file_exists($assetPath)) {
        abort(404);
    }
    
    // Determine MIME type
    $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'gif' => 'image/gif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    
    return response()->file($assetPath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');
