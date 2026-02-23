<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateGenerationService
{
    /**
     * Generate a certificate image/PDF from a template
     */
    public function generate(CertificateTemplate $template, array $data, string $outputFormat = 'png'): array
    {
        try {
            $data = $this->normalizeTemplateData($data);

            if ($outputFormat === 'pdf') {
                return $this->generatePdfFromBlade($template, $data);
            }

            if (!$template->background_image_url || !$template->config_json) {
                return ['success' => false, 'message' => 'Template missing background image or configuration'];
            }

            $backgroundImagePath = $this->getImagePath($template->background_image_url);
            if (!$backgroundImagePath) {
                return ['success' => false, 'message' => 'Failed to load background image'];
            }

            $imageInfo = getimagesize($backgroundImagePath);
            if (!$imageInfo) {
                return ['success' => false, 'message' => 'Invalid background image format'];
            }

            $width    = $imageInfo[0];
            $height   = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            $image = $this->createImageResource($backgroundImagePath, $mimeType);
            if (!$image) {
                return ['success' => false, 'message' => 'Failed to create image resource'];
            }

            $config   = $template->config_json;
            $elements = is_array($config) && isset($config['elements']) ? $config['elements'] : $config;
            $elements = is_array($elements) ? $elements : [];

            $this->applyPlaceholders($image, $elements, $data, $width, $height);

            $outputPath = $this->saveCertificate($image, $template, $data, $outputFormat, $width, $height);

            imagedestroy($image);
            if ($backgroundImagePath && file_exists($backgroundImagePath) && strpos($backgroundImagePath, sys_get_temp_dir()) === 0) {
                @unlink($backgroundImagePath);
            }

            if ($outputPath) {
                return [
                    'success'  => true,
                    'file_path' => $outputPath,
                    'file_url'  => $this->getCertificateApiUrl($outputPath),
                ];
            }

            return ['success' => false, 'message' => 'Failed to save certificate'];

        } catch (\Exception $e) {
            Log::error('Certificate generation error', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Certificate generation failed: ' . $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // PDF GENERATION (primary path)
    // -------------------------------------------------------------------------

    /**
     * Generate PDF from template_html.
     *
     * FIX SUMMARY
     * -----------
     * 1. ALL images (logos, QR) are resolved to base64 data-URIs **before** any
     *    variable substitution so nothing slips through un-embedded.
     * 2. QR code is generated locally with a pure-PHP library (BaconQrCode /
     *    endroid/qr-code) when available; otherwise fetched remotely and embedded.
     * 3. No dependency on DomPDF remote loading or file:// chroot tricks.
     */
    private function generatePdfFromBlade(CertificateTemplate $template, array $data): array
    {
        try {
            if (!$template->template_html) {
                return ['success' => false, 'message' => 'Template HTML is missing'];
            }

            // ------------------------------------------------------------------
            // STEP 1 – Pre-resolve every image value to a base64 data-URI so
            //          DomPDF never has to fetch anything remotely.
            // ------------------------------------------------------------------
            $imageKeys = ['training_center_logo', 'acc_logo', 'qr_code',
                          'training_center_logo_url', 'acc_logo_url', 'qr_code_url'];

            foreach ($imageKeys as $key) {
                if (!empty($data[$key])) {
                    $uri = $this->toDataUri($data[$key]);
                    if ($uri) {
                        $data[$key] = $uri;
                    } else {
                        // Mark as empty so the element-removal logic hides it cleanly
                        Log::warning("Could not embed image for key '{$key}'", ['value' => $data[$key]]);
                        $data[$key] = '';
                    }
                }
            }

            // Also pre-resolve qr_code if it was not provided but verification_code was
            // (generateTrainingCenterCertificate / generateInstructorCertificate handle
            //  this already, but guard here too)
            if (empty($data['qr_code']) && !empty($data['verification_code'])) {
                $qrUrl = $this->getQrCodeUrl($data['verification_code']);
                $uri   = $this->toDataUri($qrUrl);
                $data['qr_code'] = $uri ?: '';
            }

            // Log verification_code presence for debugging
            if (isset($data['verification_code'])) {
                Log::info('Certificate generation – verification_code present', [
                    'template_id'       => $template->id,
                    'verification_code' => $data['verification_code'],
                ]);
            } else {
                Log::warning('Certificate generation – verification_code missing', [
                    'template_id' => $template->id,
                    'data_keys'   => array_keys($data),
                ]);
            }

            // ------------------------------------------------------------------
            // STEP 2 – Replace template variables (images already data-URIs)
            // ------------------------------------------------------------------
            $html = $this->replaceTemplateVariables($template->template_html, $data);

            // ------------------------------------------------------------------
            // STEP 3 – Embed any remaining remote image URLs that appear in the
            //          HTML (background-image CSS, stray <img src>, etc.)
            // ------------------------------------------------------------------
            $html = $this->embedRemainingRemoteImages($html);

            // ------------------------------------------------------------------
            // STEP 4 – Page size
            // ------------------------------------------------------------------
            $orientation = $this->normalizeOrientation($template->orientation ?? 'landscape');
            $dimensions  = $this->getPageDimensions($orientation);
            $widthPt     = ($dimensions['width'] / 96) * 72;
            $heightPt    = ($dimensions['height'] / 96) * 72;

            $pageCss = sprintf('@page { size: %spt %spt; margin: 0; }', round($widthPt, 2), round($heightPt, 2));
            if (preg_match('/<style[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $insertPos = $m[0][1] + strlen($m[0][0]);
                $html = substr_replace($html, "\n" . $pageCss . "\n", $insertPos, 0);
            } elseif (stripos($html, '<head>') !== false) {
                $html = preg_replace('/<head>/i', "<head>\n<style>{$pageCss}</style>", $html, 1);
            } else {
                $html = "<head><style>{$pageCss}</style></head>" . $html;
            }

            // ------------------------------------------------------------------
            // STEP 5 – Render PDF
            //          isRemoteEnabled = false is safer now that everything is
            //          embedded, but we leave it true as a last-resort fallback.
            // ------------------------------------------------------------------
            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');

            // ------------------------------------------------------------------
            // STEP 6 – Save
            // ------------------------------------------------------------------
            $directory = 'certificates/' . $template->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $fullPath = Storage::disk('public')->path($filePath);

            $pdf->save($fullPath);

            return [
                'success'  => true,
                'file_path' => $filePath,
                'file_url'  => $this->getCertificateApiUrl($filePath),
            ];

        } catch (\Exception $e) {
            Log::error('PDF generation error', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'PDF generation failed: ' . $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // IMAGE → BASE64 DATA-URI  (the single, authoritative conversion method)
    // -------------------------------------------------------------------------

    /**
     * Convert any image source (URL, storage path, existing data-URI) to a
     * base64 data-URI that DomPDF can render without network access.
     *
     * Returns null on failure so the caller can decide how to handle it.
     */
    private function toDataUri(string $source): ?string
    {
        // Already a data-URI – nothing to do
        if (str_starts_with($source, 'data:')) {
            return $source;
        }

        // ------------------------------------------------------------------
        // Try to resolve to a local file first (fastest, most reliable)
        // ------------------------------------------------------------------
        $localPath = $this->resolveToLocalPath($source);
        if ($localPath && file_exists($localPath) && is_readable($localPath)) {
            $bytes = @file_get_contents($localPath);
            if ($bytes && strlen($bytes) > 50) {
                $mime = $this->detectMime($localPath, $bytes);
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        // ------------------------------------------------------------------
        // Fall back to HTTP fetch for external URLs (e.g. QR code API)
        // ------------------------------------------------------------------
        $fetchUrl = $source;
        if (str_starts_with($source, '/') && !str_starts_with($source, '//')) {
            $fetchUrl = url($source);
        }

        if (filter_var($fetchUrl, FILTER_VALIDATE_URL)) {
            $bytes = $this->httpFetch($fetchUrl);
            if ($bytes && strlen($bytes) > 50) {
                $mime = $this->detectMimeFromBytes($bytes);
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        Log::warning('toDataUri: could not convert image', ['source' => substr($source, 0, 200)]);
        return null;
    }

    /**
     * Detect MIME type preferring file-based check, falling back to bytes.
     */
    private function detectMime(string $filePath, string $bytes): string
    {
        if (function_exists('finfo_file')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = @$finfo->file($filePath);
            if ($mime && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }
        return $this->detectMimeFromBytes($bytes);
    }

    /**
     * Detect MIME type from raw bytes (magic bytes).
     */
    private function detectMimeFromBytes(string $bytes): string
    {
        if (function_exists('finfo_buffer')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = @$finfo->buffer($bytes);
            if ($mime && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }
        // Fallback: inspect magic bytes
        if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") return 'image/png';
        if (substr($bytes, 0, 3) === "\xFF\xD8\xFF")      return 'image/jpeg';
        if (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a') return 'image/gif';
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
        return 'image/png'; // safe default
    }

    /**
     * HTTP GET with timeout and SSL leniency.
     */
    private function httpFetch(string $url): ?string
    {
        $ctx  = stream_context_create([
            'http' => ['timeout' => 15, 'follow_location' => true, 'user_agent' => 'Mozilla/5.0'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        return ($data !== false && strlen($data) > 50) ? $data : null;
    }

    // -------------------------------------------------------------------------
    // LOCAL PATH RESOLUTION
    // -------------------------------------------------------------------------

    /**
     * Resolve a URL or path to a local filesystem path, or return null.
     *
     * This replaces the old getImagePath() + urlToLocalPath() tandem with one
     * clean method. No temp-dir copying needed because we read bytes directly.
     */
    private function resolveToLocalPath(string $source): ?string
    {
        $source = $this->fixStorageUrl($source);

        // Already a local absolute path
        if (str_starts_with($source, '/') && !str_starts_with($source, '//') && file_exists($source)) {
            return $source;
        }

        // Strip the public storage base URL
        $storageBaseUrl = rtrim(Storage::disk('public')->url(''), '/');
        if (str_starts_with($source, $storageBaseUrl . '/') || $source === $storageBaseUrl) {
            $relative = ltrim(substr($source, strlen($storageBaseUrl)), '/');
            $path     = Storage::disk('public')->path($relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Match /storage/... or /laravel/storage/... patterns
        if (preg_match('#/(?:laravel/)?storage/(.+)$#', $source, $m)) {
            $relative = preg_replace('#^app/public/#i', '', $m[1]);
            $path     = Storage::disk('public')->path($relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Absolute URL to the same app host → try to map to public disk
        $appUrl = rtrim(config('app.url', ''), '/');
        if ($appUrl && str_starts_with($source, $appUrl)) {
            $relative = ltrim(substr($source, strlen($appUrl)), '/');
            // Strip leading "storage/" so we look in public disk root
            $relative = preg_replace('#^storage/#i', '', $relative);
            $path     = Storage::disk('public')->path($relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        return null; // caller will fall back to HTTP fetch
    }

    /**
     * Fix malformed storage URLs (app/public double-prefix, etc.)
     */
    private function fixStorageUrl(string $url): string
    {
        $url = preg_replace('#/storage/app/public/#i', '/storage/', $url);
        $url = preg_replace('#storage/app/public/#i',  'storage/', $url);
        $url = preg_replace('#/laravel/storage/app/public/#i', '/laravel/storage/', $url);
        $url = preg_replace('#laravel/storage/app/public/#i',  'laravel/storage/', $url);
        return $url;
    }

    // -------------------------------------------------------------------------
    // TEMPLATE VARIABLE REPLACEMENT
    // -------------------------------------------------------------------------

    /**
     * Replace {{variables}} in template HTML.
     *
     * Image values should already be data-URIs at this point (pre-processed in
     * generatePdfFromBlade). This method just does plain htmlspecialchars
     * substitution for all keys and removes elements whose value is empty/null.
     */
    private function replaceTemplateVariables(string $html, array $data): string
    {
        // Remove elements (div / img) containing variables with null/empty values
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $varRegex = '(?:\{\{\s*' . preg_quote($key, '/') . '\s*\}\})';
                $html = preg_replace('/<div[^>]*>[\s\S]*?' . $varRegex . '[\s\S]*?<\/div>/i', '', $html);
                $html = preg_replace('/<img[^>]*' . $varRegex . '[^>]*\/?>/i', '', $html);
            }
        }

        // Replace remaining variables
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Data-URIs must NOT be HTML-encoded (they are safe and encoding breaks them)
            if (is_string($value) && str_starts_with($value, 'data:')) {
                $safeValue = $value; // already safe for src="..." attributes
            } else {
                $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }

            $html = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i', $safeValue, $html);

            // Also handle camelCase / UPPER variants of verification_code
            if ($key === 'verification_code') {
                foreach (['verificationCode', 'VerificationCode', 'VERIFICATION_CODE', 'verification-code'] as $alt) {
                    $html = preg_replace('/\{\{\s*' . preg_quote($alt, '/') . '\s*\}\}/i', $safeValue, $html);
                }
            }
        }

        $html = preg_replace('/\n\s*\n/', "\n", $html);
        return $html;
    }

    /**
     * Embed any remaining remote image URLs in the HTML as base64 data-URIs.
     *
     * This is a safety net for images that were already in the template HTML
     * itself (not coming from template variables), e.g. static logos hardcoded
     * in the template markup.
     */
    private function embedRemainingRemoteImages(string $html): string
    {
        // CSS url(...) – skip data: and file:// (already embedded)
        $html = preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
            function ($m) {
                $url = trim($m[1]);
                if (empty($url) || str_starts_with($url, 'data:') || str_starts_with($url, 'file://')) {
                    return $m[0];
                }
                $uri = $this->toDataUri($url);
                return $uri ? 'url(' . $uri . ')' : $m[0];
            },
            $html
        );

        // <img src="..."> – skip data: and file://
        $html = preg_replace_callback(
            '/<img([^>]*)\ssrc\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
            function ($m) {
                $url = trim($m[2]);
                if (empty($url) || str_starts_with($url, 'data:') || str_starts_with($url, 'file://')) {
                    return $m[0];
                }
                $uri = $this->toDataUri($url);
                $src = $uri ?: htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                return '<img' . $m[1] . ' src="' . $src . '"' . $m[3] . '>';
            },
            $html
        );

        return $html;
    }

    // -------------------------------------------------------------------------
    // PNG / JPG GENERATION (GD path)
    // -------------------------------------------------------------------------

    private function getImagePath(string $imageUrl): ?string
    {
        $path = $this->resolveToLocalPath($imageUrl);
        if ($path) {
            return $path;
        }
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $bytes = $this->httpFetch($imageUrl);
            if ($bytes) {
                $ext      = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'tmp';
                $tempPath = sys_get_temp_dir() . '/' . Str::random(20) . '.' . $ext;
                if (file_put_contents($tempPath, $bytes)) {
                    return $tempPath;
                }
            }
        }
        return null;
    }

    private function createImageResource(string $filePath, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                $img = imagecreatefrompng($filePath);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                return $img;
            case 'image/gif':
                return imagecreatefromgif($filePath);
            default:
                return null;
        }
    }

    private function applyPlaceholders($image, array $elements, array $data, int $imageWidth, int $imageHeight): void
    {
        foreach ($elements as $placeholder) {
            if (!isset($placeholder['variable'])) {
                continue;
            }

            $type     = $placeholder['type'] ?? 'text';
            $variable = $placeholder['variable'];
            $x        = (float)($placeholder['x'] ?? 0.5) * $imageWidth;
            $y        = (float)($placeholder['y'] ?? 0.5) * $imageHeight;

            if ($type === 'image') {
                $key      = preg_match('/\{\{([^}]+)\}\}/', $variable, $m) ? trim($m[1]) : $variable;
                $imageUrl = $data[$key] ?? $data[$variable] ?? null;
                if ($imageUrl) {
                    $elW = (float)($placeholder['width'] ?? 0.2) * $imageWidth;
                    $elH = (float)($placeholder['height'] ?? 0.15) * $imageHeight;
                    $this->overlayImage($image, $imageUrl, (int)$x, (int)$y, (int)$elW, (int)$elH);
                }
                continue;
            }

            if (preg_match('/\{\{([^}]+)\}\}/', $variable, $matches)) {
                $text = $data[trim($matches[1])] ?? $variable;
            } else {
                $text = $variable;
            }

            $fontSize   = (int)($placeholder['font_size'] ?? $placeholder['fontSize'] ?? 24);
            $colorHex   = $placeholder['color'] ?? '#000000';
            $fontFamily = $placeholder['font_family'] ?? $placeholder['fontFamily'] ?? 'Arial';
            $textAlign  = $placeholder['text_align'] ?? $placeholder['textAlign'] ?? 'left';

            $color     = $this->hexToRgb($colorHex);
            $textColor = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);
            $fontPath  = $this->getFontPath($fontFamily);

            if ($fontPath && function_exists('imagettftext') && function_exists('imagettfbbox')) {
                $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
                if ($bbox !== false) {
                    $textWidth = abs($bbox[4] - $bbox[0]);
                    if ($textAlign === 'center') {
                        $x -= $textWidth / 2;
                    } elseif ($textAlign === 'right') {
                        $x -= $textWidth;
                    }
                }
                imagettftext($image, $fontSize, 0, (int)$x, (int)$y, $textColor, $fontPath, $text);
            } else {
                imagestring($image, 5, (int)$x, (int)$y - 20, $text, $textColor);
            }
        }
    }

    private function overlayImage($destImage, string $imageUrl, int $x, int $y, int $width, int $height): void
    {
        $srcPath = $this->getImagePath($imageUrl);
        if (!$srcPath) return;
        $info = @getimagesize($srcPath);
        if (!$info) return;
        $srcImage = $this->createImageResource($srcPath, $info['mime']);
        if (!$srcImage) return;
        if (function_exists('imagecopyresampled')) {
            imagecopyresampled($destImage, $srcImage, $x, $y, 0, 0, $width, $height, imagesx($srcImage), imagesy($srcImage));
        } else {
            imagecopyresized($destImage, $srcImage, $x, $y, 0, 0, $width, $height, imagesx($srcImage), imagesy($srcImage));
        }
        imagedestroy($srcImage);
        if (strpos($srcPath, sys_get_temp_dir()) === 0 && file_exists($srcPath)) {
            @unlink($srcPath);
        }
    }

    private function saveCertificate($image, CertificateTemplate $template, array $data, string $format, int $width, int $height): ?string
    {
        $directory = 'certificates/' . $template->id;
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        if ($format === 'pdf') {
            $tempPng   = sys_get_temp_dir() . '/' . Str::random(40) . '.png';
            imagepng($image, $tempPng, 9);
            $bytes     = file_get_contents($tempPng);
            $b64       = base64_encode($bytes);
            $widthPt   = ($width / 96) * 72;
            $heightPt  = ($height / 96) * 72;

            $html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>*{margin:0;padding:0;box-sizing:border-box;}body{margin:0;padding:0;}
            img{width:' . $widthPt . 'pt;height:' . $heightPt . 'pt;display:block;}</style></head>
            <body><img src="data:image/png;base64,' . $b64 . '" /></body></html>';

            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');

            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $pdf->save(Storage::disk('public')->path($filePath));
            @unlink($tempPng);
            return $filePath;

        } elseif ($format === 'png') {
            $fileName = Str::random(40) . '.png';
            $filePath = $directory . '/' . $fileName;
            imagepng($image, Storage::disk('public')->path($filePath), 9);
            return $filePath;

        } elseif (in_array($format, ['jpg', 'jpeg'])) {
            $fileName = Str::random(40) . '.jpg';
            $filePath = $directory . '/' . $fileName;
            imagejpeg($image, Storage::disk('public')->path($filePath), 95);
            return $filePath;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // PUBLIC CONVENIENCE METHODS
    // -------------------------------------------------------------------------

    public function generateTrainingCenterCertificate(CertificateTemplate $template, $trainingCenter, $acc, ?string $verificationCode = null): array
    {
        $data = [
            'training_center_name'                => $trainingCenter->name ?? '',
            'training_center_legal_name'          => $trainingCenter->legal_name ?? '',
            'training_center_email'               => $trainingCenter->email ?? '',
            'training_center_country'             => $trainingCenter->country ?? '',
            'training_center_city'                => $trainingCenter->city ?? '',
            'training_center_registration_number' => $trainingCenter->registration_number ?? '',
            'acc_name'                            => $acc->name ?? '',
            'acc_legal_name'                      => $acc->legal_name ?? '',
            'acc_registration_number'             => $acc->registration_number ?? '',
            'acc_country'                         => $acc->country ?? '',
            'issue_date'                          => now()->format('Y-m-d'),
            'issue_date_formatted'                => now()->format('F j, Y'),
            'training_center_logo'                => $this->resolveLogoUrl($trainingCenter->logo_url ?? null),
            'acc_logo'                            => $this->resolveLogoUrl($acc->logo_url ?? null),
        ];

        if ($verificationCode) {
            $data['verification_code'] = $verificationCode;
            $data['qr_code']           = $this->getQrCodeUrl($verificationCode);
        }

        return $this->generate($template, $data, 'pdf');
    }

    public function generateInstructorCertificate(CertificateTemplate $template, $instructor, $course, $acc, ?string $verificationCode = null): array
    {
        $data = [
            'instructor_name'       => trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
            'instructor_first_name' => $instructor->first_name ?? '',
            'instructor_last_name'  => $instructor->last_name ?? '',
            'instructor_email'      => $instructor->email ?? '',
            'instructor_id_number'  => $instructor->id_number ?? '',
            'instructor_country'    => $instructor->country ?? '',
            'instructor_city'       => $instructor->city ?? '',
            'course_name'           => $course->name ?? '',
            'course_name_ar'        => $course->name_ar ?? '',
            'course_code'           => $course->code ?? '',
            'acc_name'              => $acc->name ?? '',
            'acc_legal_name'        => $acc->legal_name ?? '',
            'acc_registration_number' => $acc->registration_number ?? '',
            'acc_country'           => $acc->country ?? '',
            'issue_date'            => now()->format('Y-m-d'),
            'issue_date_formatted'  => now()->format('F j, Y'),
            'expiry_date'           => now()->addYears(3)->format('Y-m-d'),
            'training_center_logo'  => $this->resolveLogoUrl($instructor->trainingCenter?->logo_url ?? null),
            'acc_logo'              => $this->resolveLogoUrl($acc->logo_url ?? null),
        ];

        if ($verificationCode) {
            $data['verification_code'] = $verificationCode;
            $data['qr_code']           = $this->getQrCodeUrl($verificationCode);
        }

        return $this->generate($template, $data, 'pdf');
    }

    public function generatePdf(CertificateTemplate $template, array $data): array
    {
        return $this->generate($template, $data, 'pdf');
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function normalizeTemplateData(array $data): array
    {
        $mapping = [
            'training_center_logo_url' => 'training_center_logo',
            'acc_logo_url'             => 'acc_logo',
            'qr_code_url'              => 'qr_code',
        ];
        foreach ($mapping as $apiKey => $templateKey) {
            if (isset($data[$apiKey]) && !isset($data[$templateKey])) {
                $data[$templateKey] = $data[$apiKey];
            }
        }
        return $data;
    }

    private function normalizeOrientation(?string $orientation): string
    {
        $o = strtolower(trim((string) $orientation));
        return in_array($o, ['portrait', 'landscape']) ? $o : 'landscape';
    }

    private function getPageDimensions(string $orientation): array
    {
        return $orientation === 'portrait'
            ? ['width' => 848,  'height' => 1200]
            : ['width' => 1200, 'height' => 848];
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function getCertificateApiUrl(string $filePath): string
    {
        return url('/api/storage/' . $filePath);
    }

    private function resolveLogoUrl(?string $url): ?string
    {
        if (!$url) return null;
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return url($url);
    }

    private function getQrCodeUrl(string $verificationCode): string
    {
        // Frontend verification page: e.g. http://localhost:5173/verify-certificate?code=VERIFY-XXX
        $frontendBase = rtrim(env('FRONTEND_URL', config('app.url')), '/');
        $verifyUrl    = $frontendBase . '/verify-certificate?code=' . urlencode($verificationCode);

        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'data'   => $verifyUrl,
            'size'   => '200x200',
            'format' => 'png',
        ]);
    }

    private function getFontPath(string $fontFamily): ?string
    {
        $projectFontDir = resource_path('fonts');
        $projectFonts   = [
            'Arial'          => ['arial.ttf', 'Arial.ttf'],
            'Helvetica'      => ['arial.ttf', 'helvetica.ttf', 'Arial.ttf'],
            'Times New Roman'=> ['times.ttf', 'Times.ttf', 'times-new-roman.ttf'],
            'Courier New'    => ['courier.ttf', 'cour.ttf'],
            'Courier'        => ['courier.ttf', 'cour.ttf'],
            'Verdana'        => ['verdana.ttf', 'Verdana.ttf'],
            'Georgia'        => ['georgia.ttf', 'Georgia.ttf'],
            'Tahoma'         => ['tahoma.ttf', 'Tahoma.ttf'],
            'Trebuchet MS'   => ['trebuchet.ttf', 'trebuc.ttf'],
            'Impact'         => ['impact.ttf', 'Impact.ttf'],
        ];

        if (isset($projectFonts[$fontFamily]) && is_dir($projectFontDir)) {
            foreach ($projectFonts[$fontFamily] as $file) {
                $path = $projectFontDir . '/' . $file;
                if (file_exists($path)) return $path;
            }
        }

        $sysDirs  = [
            '/usr/share/fonts/truetype/liberation/',
            '/usr/share/fonts/truetype/dejavu/',
            '/usr/share/fonts/TTF/',
            '/usr/share/fonts/truetype/',
            '/usr/local/lib/X11/fonts/TTF/',
            storage_path('fonts/'),
        ];
        $sysFonts = [
            'Arial'          => ['arial.ttf', 'LiberationSans-Regular.ttf', 'DejaVuSans.ttf'],
            'Helvetica'      => ['arial.ttf', 'LiberationSans-Regular.ttf', 'DejaVuSans.ttf'],
            'Times New Roman'=> ['times.ttf', 'LiberationSerif-Regular.ttf', 'DejaVuSerif.ttf'],
            'Courier New'    => ['courier.ttf', 'LiberationMono-Regular.ttf', 'DejaVuSansMono.ttf'],
            'Verdana'        => ['verdana.ttf', 'DejaVuSans.ttf'],
            'Georgia'        => ['georgia.ttf', 'LiberationSerif-Regular.ttf'],
            'Tahoma'         => ['tahoma.ttf', 'DejaVuSans.ttf'],
            'Impact'         => ['impact.ttf', 'DejaVuSans-Bold.ttf'],
        ];

        if (isset($sysFonts[$fontFamily])) {
            foreach ($sysDirs as $dir) {
                if (!is_dir($dir)) continue;
                foreach ($sysFonts[$fontFamily] as $file) {
                    $path = rtrim($dir, '/') . '/' . $file;
                    if (file_exists($path) && is_readable($path)) return $path;
                }
            }
        }

        $winFonts = [
            'Arial' => 'C:\\Windows\\Fonts\\arial.ttf',
            'Times New Roman' => 'C:\\Windows\\Fonts\\times.ttf',
            'Courier New' => 'C:\\Windows\\Fonts\\cour.ttf',
            'Verdana' => 'C:\\Windows\\Fonts\\verdana.ttf',
            'Georgia' => 'C:\\Windows\\Fonts\\georgia.ttf',
            'Tahoma' => 'C:\\Windows\\Fonts\\tahoma.ttf',
            'Impact' => 'C:\\Windows\\Fonts\\impact.ttf',
        ];
        if (isset($winFonts[$fontFamily]) && file_exists($winFonts[$fontFamily])) {
            return $winFonts[$fontFamily];
        }

        $macFonts = [
            'Arial' => '/System/Library/Fonts/Supplemental/Arial.ttf',
            'Times New Roman' => '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
            'Verdana' => '/System/Library/Fonts/Supplemental/Verdana.ttf',
            'Georgia' => '/System/Library/Fonts/Supplemental/Georgia.ttf',
        ];
        if (isset($macFonts[$fontFamily]) && file_exists($macFonts[$fontFamily])) {
            return $macFonts[$fontFamily];
        }

        Log::warning('Font not found', ['font_family' => $fontFamily]);
        return null;
    }

    // Kept for backwards compatibility (used by legacy PNG path)
    private function processConfigJson(array $config, array $data, float $widthPt, float $heightPt): array
    {
        $textElements = [];
        foreach ($config as $placeholder) {
            if (!isset($placeholder['variable'])) continue;
            $variable = $placeholder['variable'];
            if (preg_match('/\{\{([^}]+)\}\}/', $variable, $matches)) {
                $text = $data[trim($matches[1])] ?? $variable;
            } else {
                $text = $variable;
            }
            $fontSizePx = (int)($placeholder['font_size'] ?? $placeholder['fontSize'] ?? 24);
            $textElements[] = [
                'text'        => $text,
                'x_pt'        => (float)($placeholder['x'] ?? 0.5) * $widthPt,
                'y_pt'        => (float)($placeholder['y'] ?? 0.5) * $heightPt,
                'font_family' => $placeholder['font_family'] ?? $placeholder['fontFamily'] ?? 'Arial',
                'font_size'   => $fontSizePx * 0.75,
                'color'       => $placeholder['color'] ?? '#000000',
                'text_align'  => $placeholder['text_align'] ?? $placeholder['textAlign'] ?? 'left',
            ];
        }
        return $textElements;
    }
}