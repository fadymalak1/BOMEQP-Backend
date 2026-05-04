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

    /**
     * Remove a mistaken "/api" segment before "/laravel/" or "/storage/" in absolute URLs
     * (e.g. when APP_URL or forced root included /api and Storage::url doubled the path).
     */
    public static function stripErroneousApiSegment(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $out = $url;
        $prev = null;
        while ($prev !== $out) {
            $prev = $out;
            $out = preg_replace('#^(https?://[^/]+)/api(?=/laravel|/storage)#', '$1', $out) ?? $out;
        }

        return $out;
    }

    /**
     * Same as stripErroneousApiSegment but applied to every occurrence inside HTML (e.g. template_html).
     */
    public static function sanitizeUrlsInHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $out = $html;
        $prev = null;
        while ($prev !== $out) {
            $prev = $out;
            $out = preg_replace('#(https?://[^/\'"\s]+)/api(?=/laravel|/storage)#', '$1', $out) ?? $out;
        }

        return $out;
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
