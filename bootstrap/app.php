<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
        ]);
        
        // Set user locale for API routes - prepend to run early
        $middleware->prepend(\App\Http\Middleware\SetUserLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle validation exceptions for API routes - return localized validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => trans('messages.validation_failed'),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Handle authentication exceptions for API routes - return JSON instead of redirecting
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });

        // Handle route not found exceptions for API routes
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Route not found.'
                ], 404);
            }
        });

        // Handle PostTooLargeException for API routes
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'File size exceeds server limits. Maximum size is 10MB.',
                    'error' => 'File too large',
                    'error_code' => 'post_too_large'
                ], 413);
            }
        });
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Configure unauthenticated responses for API routes
        $middleware->redirectGuestsTo(function ($request) {
            // For API routes, return null to prevent redirect (exception handler will catch it)
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            // For web routes, you can return a login route if it exists
            // return route('login');
            return null; // Return null for now since we don't have a web login route
        });
    })->create();
