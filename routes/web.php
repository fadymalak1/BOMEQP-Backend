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
