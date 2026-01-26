<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ACC;
use Symfony\Component\HttpFoundation\Response;

class EnsureACCIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only check for ACC admin users
        if ($user && $user->role === 'acc_admin') {
            $acc = ACC::where('email', $user->email)->first();
            
            if (!$acc) {
                return response()->json([
                    'message' => 'ACC not found for this user'
                ], 404);
            }

            // Check if ACC is active - only 'active' status allows work
            if ($acc->status !== 'active') {
                $statusMessages = [
                    'pending' => 'Your ACC application is pending approval.',
                    'approved' => 'Your ACC has been approved but is waiting for activation. Please contact the administrator.',
                    'suspended' => 'Your ACC has been suspended. Please contact the administrator.',
                    'expired' => 'Your ACC subscription has expired. Please renew your subscription.',
                    'rejected' => 'Your ACC application has been rejected.',
                ];

                $message = $statusMessages[$acc->status] ?? 'Your ACC is not active. Please contact the administrator.';

                return response()->json([
                    'message' => $message,
                    'acc_status' => $acc->status,
                ], 403);
            }
        }

        return $next($request);
    }
}

