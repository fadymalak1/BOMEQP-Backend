<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces URL generation (url(), route(), Storage disk URLs) to use the current
 * request host and base path, so the same deployment can serve e.g. /dev and
 * production without every asset URL pointing at APP_URL from .env.
 */
class UseRequestApplicationUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawRoot = rtrim($request->root(), '/');
        $root = rtrim(str_replace('/api', '', $rawRoot), '/');

        if ($root !== '') {
            URL::forceRootUrl($root);
            // Same pattern as config/filesystems.php default for disk public
            config(['filesystems.disks.public.url' => $root . '/storage/app/public']);
            Storage::forgetDisk('public');
        }

        return $next($request);
    }
}
