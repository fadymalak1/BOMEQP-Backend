<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get authenticated user if available
        if ($request->user()) {
            $language = $request->user()->language ?? 'en';
            
            // Validate language, fallback to 'en' if invalid
            $validLanguages = ['en', 'hi', 'zh-CN'];
            if (in_array($language, $validLanguages)) {
                App::setLocale($language);
            } else {
                App::setLocale('en');
            }
        } else {
            // For unauthenticated requests, default to English
            App::setLocale('en');
        }

        return $next($request);
    }
}

