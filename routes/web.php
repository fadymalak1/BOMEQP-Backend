<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/health', function () {
    return response()->json(['status' => 'ok']);
});


// API Documentation Routes - Swagger/OpenAPI
Route::get('/api/doc', function () {
    return redirect('/api/documentation');
});

Route::get('/api/docs', function () {
    return redirect('/api/documentation');
});

// Test route to verify URL structure
Route::get('/api/test-swagger-url', function () {
    return response()->json([
        'message' => 'Swagger URL test',
        'swagger_route' => route('l5-swagger.default.api'),
        'swagger_url' => url('api/documentation'),
        'base_url' => url('/'),
        'app_url' => config('app.url'),
        'current_url' => request()->url(),
        'base_path' => request()->getBasePath(),
    ]);
});
