<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = auth()->user();

        // Handle comma-separated roles (e.g., "group_admin,acc_admin")
        $allowedRoles = [];
        foreach ($roles as $role) {
            $allowedRoles = array_merge($allowedRoles, explode(',', $role));
        }
        $allowedRoles = array_map('trim', $allowedRoles);

        if (!in_array($user->role, $allowedRoles)) {
            return response()->json(['message' => 'Unauthorized. Required role: ' . implode(' or ', $allowedRoles)], 403);
        }

        // Status check removed - allow access regardless of status
        // if ($user->status !== 'active') {
        //     return response()->json(['message' => 'Account is not active'], 403);
        // }

        return $next($request);
    }
}

