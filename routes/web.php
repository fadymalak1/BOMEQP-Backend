<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation (Scribe)
// After installing Scribe, this will serve the docs at /api/doc
// Install: composer require knuckleswtf/scribe
// Generate: php artisan scribe:generate
