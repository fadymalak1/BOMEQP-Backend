<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Route - Redirect /api/doc to /docs
Route::get('/api/doc', function () {
    return redirect('/docs');
});

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
