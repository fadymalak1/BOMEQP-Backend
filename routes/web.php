<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation (Scramble)
// Scramble will automatically register routes, but we can customize the route here if needed
