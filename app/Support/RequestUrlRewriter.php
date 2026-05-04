<?php

namespace App\Support;

/**
 * Rewrites absolute URLs that were saved using another environment's APP_URL
 * (e.g. app.bomeqp.com) so API responses match the current HTTP request
 * (e.g. dev.bomeqp.com). Does not alter the database.
 */
class RequestUrlRewriter
{
    /**
     * Paths produced by this app for uploaded/public assets (substring match).
     */
    private static function looksLikeOurStoredAssetUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if ($path === '') {
            return false;
        }

        return str_contains($path, 'certificate-templates')
            || str_contains($path, '/storage/app/public')
            || str_contains($path, '/api/storage/')
            || str_contains($path, '/storage/instructors/')
            || str_contains($path, '/laravel/storage/');
    }

    public static function toCurrentRequest(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        if (! self::looksLikeOurStoredAssetUrl($url)) {
            return $url;
        }

        if (app()->runningInConsole()) {
            return $url;
        }

        if (! app()->bound('request')) {
            return $url;
        }

        $request = request();
        $root = rtrim($request->root(), '/');
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return $url;
        }

        $rewritten = $root . $path;
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            $rewritten .= '?' . $query;
        }
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if ($fragment) {
            $rewritten .= '#' . $fragment;
        }

        return $rewritten;
    }
}
