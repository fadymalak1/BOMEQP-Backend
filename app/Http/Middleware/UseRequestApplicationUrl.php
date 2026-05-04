<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Forces URL generation (url(), route(), Storage disk URLs) to use the current
 * request host and base path, so the same deployment can serve e.g. /dev and
 * production without every asset URL pointing at APP_URL from .env.
 */
class UseRequestApplicationUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $rawRoot = rtrim($request->root(), '/');
            // Strip trailing /api repeatedly (e.g. request base /api/api/... leaves root ending in .../api/api).
            $root = $rawRoot;
            while ($root !== '' && preg_match('#/api$#', $root)) {
                $root = rtrim(substr($root, 0, -strlen('/api')), '/');
            }

            if ($root !== '') {
                URL::forceRootUrl($root);
                // Same pattern as config/filesystems.php default for disk public
                config(['filesystems.disks.public.url' => $root.'/storage/app/public']);
                Storage::forgetDisk('public');
            }
        } catch (Throwable $e) {
            Log::warning('UseRequestApplicationUrl failed; continuing without forcing URLs.', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }
}
