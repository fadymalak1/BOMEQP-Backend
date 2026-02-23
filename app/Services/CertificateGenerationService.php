<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateGenerationService
{
    // =========================================================================
    // PUBLIC API
    // =========================================================================

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

            $backgroundImagePath = $this->downloadToLocalTemp($template->background_image_url);
            if (!$backgroundImagePath) {
                return ['success' => false, 'message' => 'Failed to load background image'];
            }

            $imageInfo = getimagesize($backgroundImagePath);
            if (!$imageInfo) {
                return ['success' => false, 'message' => 'Invalid background image format'];
            }

            $image = $this->createImageResource($backgroundImagePath, $imageInfo['mime']);
            if (!$image) {
                return ['success' => false, 'message' => 'Failed to create image resource'];
            }

            $config   = $template->config_json;
            $elements = is_array($config) && isset($config['elements']) ? $config['elements'] : $config;
            $elements = is_array($elements) ? $elements : [];

            $this->applyPlaceholders($image, $elements, $data, $imageInfo[0], $imageInfo[1]);

            $outputPath = $this->saveCertificate($image, $template, $data, $outputFormat, $imageInfo[0], $imageInfo[1]);

            imagedestroy($image);
            $this->cleanupTemp($backgroundImagePath);

            if ($outputPath) {
                return ['success' => true, 'file_path' => $outputPath, 'file_url' => $this->getCertificateApiUrl($outputPath)];
            }

            return ['success' => false, 'message' => 'Failed to save certificate'];

        } catch (\Exception $e) {
            Log::error('Certificate generation error', ['template_id' => $template->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => 'Certificate generation failed: ' . $e->getMessage()];
        }
    }

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
            // Pass raw logo_url values directly — toDataUri() handles all URL formats
            'training_center_logo'                => $trainingCenter->logo_url ?? null,
            'acc_logo'                            => $acc->logo_url ?? null,
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
            'instructor_name'              => trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
            'instructor_first_name'        => $instructor->first_name ?? '',
            'instructor_last_name'         => $instructor->last_name ?? '',
            'instructor_email'             => $instructor->email ?? '',
            'instructor_id_number'         => $instructor->id_number ?? '',
            'instructor_country'           => $instructor->country ?? '',
            'instructor_city'              => $instructor->city ?? '',
            'course_name'                  => $course->name ?? '',
            'course_name_ar'               => $course->name_ar ?? '',
            'course_code'                  => $course->code ?? '',
            'acc_name'                     => $acc->name ?? '',
            'acc_legal_name'               => $acc->legal_name ?? '',
            'acc_registration_number'      => $acc->registration_number ?? '',
            'acc_country'                  => $acc->country ?? '',
            'issue_date'                   => now()->format('Y-m-d'),
            'issue_date_formatted'         => now()->format('F j, Y'),
            'expiry_date'                  => now()->addYears(3)->format('Y-m-d'),
            'training_center_logo'         => $instructor->trainingCenter?->logo_url ?? null,
            'acc_logo'                     => $acc->logo_url ?? null,
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

    // =========================================================================
    // PDF GENERATION
    // =========================================================================

    private function generatePdfFromBlade(CertificateTemplate $template, array $data): array
    {
        try {
            if (!$template->template_html) {
                return ['success' => false, 'message' => 'Template HTML is missing'];
            }

            // ------------------------------------------------------------------
            // Pre-convert ALL image values to base64 data-URIs BEFORE touching HTML.
            // This is the only reliable way to make DomPDF render images:
            //   - No remote fetching needed by DomPDF
            //   - No chroot/file:// path issues
            //   - Works for both <img src> and CSS background-image
            // ------------------------------------------------------------------
            $imageKeys = ['training_center_logo', 'acc_logo', 'qr_code',
                          'training_center_logo_url', 'acc_logo_url', 'qr_code_url'];

            foreach ($imageKeys as $key) {
                if (!empty($data[$key]) && !str_starts_with((string)$data[$key], 'data:')) {
                    $uri = $this->toDataUri($data[$key]);
                    if ($uri) {
                        $data[$key] = $uri;
                        Log::info("Certificate: embedded image for '{$key}'", ['length' => strlen($uri)]);
                    } else {
                        Log::error("Certificate: FAILED to embed image for '{$key}'", ['value' => $data[$key]]);
                        $data[$key] = ''; // hide element rather than show broken box
                    }
                }
            }

            // Generate QR from verification_code if qr_code not already set
            if (empty($data['qr_code']) && !empty($data['verification_code'])) {
                $uri = $this->toDataUri($this->getQrCodeUrl($data['verification_code']));
                $data['qr_code'] = $uri ?: '';
            }

            // Replace template variables
            $html = $this->replaceTemplateVariables($template->template_html, $data);

            // Embed any remaining hardcoded image URLs in the template HTML itself
            $html = $this->embedRemainingRemoteImages($html);

            // Page dimensions
            $orientation = $this->normalizeOrientation($template->orientation ?? 'landscape');
            $dimensions  = $this->getPageDimensions($orientation);
            $widthPt     = ($dimensions['width'] / 96) * 72;
            $heightPt    = ($dimensions['height'] / 96) * 72;

            $pageCss = sprintf('@page { size: %spt %spt; margin: 0; }', round($widthPt, 2), round($heightPt, 2));
            if (preg_match('/<style[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $html = substr_replace($html, "\n" . $pageCss . "\n", $m[0][1] + strlen($m[0][0]), 0);
            } elseif (stripos($html, '<head>') !== false) {
                $html = preg_replace('/<head>/i', "<head>\n<style>{$pageCss}</style>", $html, 1);
            } else {
                $html = "<head><style>{$pageCss}</style></head>" . $html;
            }

            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false)   // everything is embedded – no remote needed
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');

            $directory = 'certificates/' . $template->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $pdf->save(Storage::disk('public')->path($filePath));

            return ['success' => true, 'file_path' => $filePath, 'file_url' => $this->getCertificateApiUrl($filePath)];

        } catch (\Exception $e) {
            Log::error('PDF generation error', ['template_id' => $template->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => 'PDF generation failed: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // IMAGE → BASE64 DATA-URI  (the single authoritative conversion method)
    // =========================================================================

    /**
     * Convert any image source to a base64 data-URI.
     *
     * Handles ALL URL/path formats used by this application:
     *   https://app.bomeqp.com/laravel/storage/app/public/accs/13/logo/file.png
     *   https://app.bomeqp.com/storage/logos/file.png
     *   /storage/logos/file.png
     *   logos/file.png
     *   data:image/png;base64,...   (pass-through)
     *   https://api.qrserver.com/... (external, fetched via HTTP)
     */
    private function toDataUri(string $source): ?string
    {
        if (str_starts_with($source, 'data:')) {
            return $source; // already embedded
        }

        Log::info('toDataUri: start', ['source' => $source]);

        // Strategy 1: resolve to a local filesystem path and read bytes directly.
        // This is the most reliable approach — no network, no permissions issues.
        $localPath = $this->urlToAbsolutePath($source);
        if ($localPath) {
            $bytes = @file_get_contents($localPath);
            if ($bytes && strlen($bytes) > 50) {
                $mime = $this->detectMime($localPath, $bytes);
                Log::info('toDataUri: success via local path', ['path' => $localPath, 'bytes' => strlen($bytes)]);
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        // Strategy 2: HTTP fetch (for truly external URLs like QR code API).
        $fetchUrl = $this->toAbsoluteUrl($source);
        if ($fetchUrl) {
            Log::info('toDataUri: trying HTTP fetch', ['url' => $fetchUrl]);
            $bytes = $this->httpFetch($fetchUrl);
            if ($bytes && strlen($bytes) > 50) {
                $mime = $this->detectMimeFromBytes($bytes);
                Log::info('toDataUri: success via HTTP', ['url' => $fetchUrl, 'bytes' => strlen($bytes)]);
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        Log::error('toDataUri: ALL strategies failed', ['source' => $source]);
        return null;
    }

    /**
     * THE KEY METHOD: Convert any URL format used by this app to an absolute filesystem path.
     *
     * Known URL formats in the DB / passed by callers:
     *   https://app.bomeqp.com/laravel/storage/app/public/accs/13/logo/file.png
     *   https://app.bomeqp.com/storage/logos/file.png
     *   http://localhost/storage/logos/file.png
     *   /storage/logos/file.png
     *   /laravel/storage/app/public/logos/file.png
     *   logos/file.png    (relative, stored in DB)
     *   storage/logos/file.png
     *
     * All of these ultimately resolve to a file under storage/app/public/ on disk.
     */
    private function urlToAbsolutePath(string $source): ?string
    {
        // Strip scheme + host (any host — handles APP_URL mismatches)
        $path = $this->stripSchemeAndHost($source);

        // Normalise known path prefix variants to a canonical relative key
        // e.g. /laravel/storage/app/public/accs/... → accs/...
        //      /storage/accs/...                    → accs/...
        //      storage/accs/...                     → accs/...
        $relative = $this->normaliseToRelative($path);

        if ($relative === null) {
            // Could not derive a relative path — maybe it's already a relative path
            // like "logos/file.png" stored directly in DB
            if (!str_contains($source, '://') && !str_starts_with($source, '/')) {
                $relative = ltrim($source, '/');
            }
        }

        if ($relative === null) {
            Log::warning('urlToAbsolutePath: could not derive relative path', ['source' => $source]);
            return null;
        }

        // Try all disk locations where the file could live
        $candidates = [
            Storage::disk('public')->path($relative),          // storage/app/public/<relative>
            storage_path('app/public/' . $relative),          // same, explicit
            public_path('storage/' . $relative),              // public/storage/<relative> (symlink)
            base_path('public/storage/' . $relative),         // same via base_path
        ];

        foreach ($candidates as $candidate) {
            $candidate = $this->normalisePath($candidate);
            if (file_exists($candidate) && is_readable($candidate)) {
                Log::info('urlToAbsolutePath: found', ['path' => $candidate]);
                return $candidate;
            }
        }

        Log::warning('urlToAbsolutePath: not found in any location', [
            'source'     => $source,
            'relative'   => $relative,
            'tried'      => $candidates,
        ]);
        return null;
    }

    /**
     * Strip the URL scheme and host, returning just the path portion.
     * Works regardless of what the actual APP_URL is configured as.
     *
     * "https://app.bomeqp.com/laravel/storage/app/public/accs/13/file.png"
     *   → "/laravel/storage/app/public/accs/13/file.png"
     *
     * "/storage/logos/file.png" → "/storage/logos/file.png" (unchanged)
     */
    private function stripSchemeAndHost(string $url): string
    {
        if (preg_match('#^https?://[^/]+(/.*)?$#i', $url, $m)) {
            return $m[1] ?? '/';
        }
        return $url; // already a path
    }

    /**
     * Normalise a URL path to a relative key suitable for passing to
     * Storage::disk('public')->path($relative).
     *
     * Returns null if the path cannot be mapped to the storage disk.
     */
    private function normaliseToRelative(string $path): ?string
    {
        // Remove leading slash for easier matching
        $p = ltrim($path, '/');

        // Pattern: laravel/storage/app/public/<relative>
        if (preg_match('#^laravel/storage/app/public/(.+)$#i', $p, $m)) {
            return $m[1];
        }

        // Pattern: storage/app/public/<relative>
        if (preg_match('#^storage/app/public/(.+)$#i', $p, $m)) {
            return $m[1];
        }

        // Pattern: laravel/storage/<relative>  (app/public already stripped)
        if (preg_match('#^laravel/storage/(.+)$#i', $p, $m)) {
            return $m[1];
        }

        // Pattern: storage/<relative>
        if (preg_match('#^storage/(.+)$#i', $p, $m)) {
            return $m[1];
        }

        // If it already looks like a direct relative path to a file (has extension, no "storage" prefix)
        if (preg_match('#^[a-zA-Z0-9_\-/]+\.[a-zA-Z]{2,5}$#', $p)) {
            return $p;
        }

        return null;
    }

    /**
     * Make a source string into an absolute HTTPS URL for HTTP fetching.
     * Returns null for paths that are clearly local-only.
     */
    private function toAbsoluteUrl(string $source): ?string
    {
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return $source;
        }
        if (str_starts_with($source, '/') && !str_starts_with($source, '//')) {
            return rtrim(config('app.url', ''), '/') . $source;
        }
        return null;
    }

    private function normalisePath(string $path): string
    {
        return str_replace(['\\', '//'], ['/', '/'], $path);
    }

    // =========================================================================
    // TEMPLATE HTML PROCESSING
    // =========================================================================

    /**
     * Replace {{variables}} in template HTML.
     *
     * Image values are already data-URIs at this point. The key rule:
     * data-URIs must NOT be passed through htmlspecialchars() — they are
     * binary-safe already and encoding would break them.
     *
     * Also handles the DomPDF limitation where data-URIs inside CSS
     * background-image url() break — those are converted to <img> tags.
     */
    private function replaceTemplateVariables(string $html, array $data): string
    {
        // Remove divs/imgs whose variable value is null/empty
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $varRe = '(?:\{\{\s*' . preg_quote($key, '/') . '\s*\}\})';
                $html  = preg_replace('/<div[^>]*>[\s\S]*?' . $varRe . '[\s\S]*?<\/div>/i', '', $html);
                $html  = preg_replace('/<img[^>]*' . $varRe . '[^>]*\/?>/i', '', $html);
            }
        }

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $isDataUri = is_string($value) && str_starts_with($value, 'data:');
            $safeValue = $isDataUri ? $value : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

            $pattern = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i';
            $html    = preg_replace($pattern, $safeValue, $html);

            if ($key === 'verification_code') {
                foreach (['verificationCode', 'VerificationCode', 'VERIFICATION_CODE', 'verification-code'] as $alt) {
                    $html = preg_replace('/\{\{\s*' . preg_quote($alt, '/') . '\s*\}\}/i', $safeValue, $html);
                }
            }
        }

        // FIX: DomPDF cannot render data-URIs inside CSS background-image: url(data:...)
        // Convert any such pattern to an absolutely-positioned <img> overlay instead.
        $html = $this->convertBackgroundDataUrisToImg($html);

        $html = preg_replace('/\n\s*\n/', "\n", $html);
        return $html;
    }

    /**
     * DomPDF has a known bug: it ignores or corrupts data-URIs in CSS
     * background-image: url(data:...) rules.
     *
     * This method finds elements using such backgrounds and injects an <img>
     * child element instead, which DomPDF handles correctly.
     */
    private function convertBackgroundDataUrisToImg(string $html): string
    {
        // Match: background-image: url(data:image/...;base64,...) or background: url(...)
        // Replace with an absolutely-positioned img inside the same element
        $html = preg_replace_callback(
            '/(<(?:div|span|td|section)[^>]*style\s*=\s*["\'][^"\']*background(?:-image)?\s*:\s*url\s*\(\s*)(data:image\/[^)]+)(\s*\)[^"\']*["\'][^>]*>)/i',
            function ($m) {
                // Inject an img tag right after the opening tag as the first child
                $dataUri = $m[2];
                $imgTag  = '<img src="' . $dataUri . '" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:contain;" />';
                // Remove the data-URI from the background-image to avoid confusion
                $openTag = preg_replace('/background(?:-image)?\s*:\s*url\s*\([^)]+\)\s*;?/i', '', $m[1] . 'X' . $m[3]);
                $openTag = substr($openTag, 0, -1); // remove the trailing X placeholder
                return $openTag . $imgTag;
            },
            $html
        );
        return $html;
    }

    /**
     * Embed any hardcoded remote image URLs that appear in the template HTML
     * itself (i.e. not coming from {{variables}}), e.g. static logos or
     * decorative images baked into the template markup.
     */
    private function embedRemainingRemoteImages(string $html): string
    {
        // CSS url('...') — skip already-embedded data: URIs
        $html = preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
            function ($m) {
                $url = trim($m[1]);
                if (empty($url) || str_starts_with($url, 'data:')) return $m[0];
                $uri = $this->toDataUri($url);
                return $uri ? 'url(' . $uri . ')' : $m[0];
            },
            $html
        );

        // <img src="...">
        $html = preg_replace_callback(
            '/<img([^>]*)\ssrc\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
            function ($m) {
                $url = trim($m[2]);
                if (empty($url) || str_starts_with($url, 'data:')) return $m[0];
                $uri = $this->toDataUri($url);
                $src = $uri ?: htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                return '<img' . $m[1] . ' src="' . $src . '"' . $m[3] . '>';
            },
            $html
        );

        return $html;
    }

    // =========================================================================
    // PNG / JPG GENERATION (GD path)
    // =========================================================================

    private function downloadToLocalTemp(string $url): ?string
    {
        $path = $this->urlToAbsolutePath($url);
        if ($path) return $path;

        $fetchUrl = $this->toAbsoluteUrl($url);
        if ($fetchUrl) {
            $bytes = $this->httpFetch($fetchUrl);
            if ($bytes) {
                $ext      = pathinfo(parse_url($fetchUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'tmp';
                $tempPath = sys_get_temp_dir() . '/' . Str::random(20) . '.' . $ext;
                if (file_put_contents($tempPath, $bytes)) return $tempPath;
            }
        }
        return null;
    }

    private function createImageResource(string $filePath, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg': case 'image/jpg': return imagecreatefromjpeg($filePath);
            case 'image/png':
                $img = imagecreatefrompng($filePath);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                return $img;
            case 'image/gif': return imagecreatefromgif($filePath);
            default: return null;
        }
    }

    private function applyPlaceholders($image, array $elements, array $data, int $imageWidth, int $imageHeight): void
    {
        foreach ($elements as $placeholder) {
            if (!isset($placeholder['variable'])) continue;

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

            $text       = preg_match('/\{\{([^}]+)\}\}/', $variable, $m) ? ($data[trim($m[1])] ?? $variable) : $variable;
            $fontSize   = (int)($placeholder['font_size'] ?? $placeholder['fontSize'] ?? 24);
            $colorHex   = $placeholder['color'] ?? '#000000';
            $fontFamily = $placeholder['font_family'] ?? $placeholder['fontFamily'] ?? 'Arial';
            $textAlign  = $placeholder['text_align'] ?? $placeholder['textAlign'] ?? 'left';
            $color      = $this->hexToRgb($colorHex);
            $textColor  = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);
            $fontPath   = $this->getFontPath($fontFamily);

            if ($fontPath && function_exists('imagettftext')) {
                $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
                if ($bbox !== false) {
                    $tw = abs($bbox[4] - $bbox[0]);
                    if ($textAlign === 'center') $x -= $tw / 2;
                    elseif ($textAlign === 'right') $x -= $tw;
                }
                imagettftext($image, $fontSize, 0, (int)$x, (int)$y, $textColor, $fontPath, $text);
            } else {
                imagestring($image, 5, (int)$x, (int)$y - 20, $text, $textColor);
            }
        }
    }

    private function overlayImage($destImage, string $imageUrl, int $x, int $y, int $width, int $height): void
    {
        $srcPath = $this->downloadToLocalTemp($imageUrl);
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
        $this->cleanupTemp($srcPath);
    }

    private function saveCertificate($image, CertificateTemplate $template, array $data, string $format, int $width, int $height): ?string
    {
        $directory = 'certificates/' . $template->id;
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        if ($format === 'pdf') {
            $tempPng  = sys_get_temp_dir() . '/' . Str::random(40) . '.png';
            imagepng($image, $tempPng, 9);
            $b64      = base64_encode(file_get_contents($tempPng));
            $widthPt  = ($width / 96) * 72;
            $heightPt = ($height / 96) * 72;
            $html     = '<!DOCTYPE html><html><head><meta charset="utf-8"/><style>*{margin:0;padding:0;}img{width:' . $widthPt . 'pt;height:' . $heightPt . 'pt;display:block;}</style></head><body><img src="data:image/png;base64,' . $b64 . '"/></body></html>';
            $pdf      = Pdf::loadHTML($html)->setPaper([0, 0, $widthPt, $heightPt], 'portrait')->setOption('isRemoteEnabled', false);
            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $pdf->save(Storage::disk('public')->path($filePath));
            @unlink($tempPng);
            return $filePath;
        }

        if ($format === 'png') {
            $fileName = Str::random(40) . '.png';
            $filePath = $directory . '/' . $fileName;
            imagepng($image, Storage::disk('public')->path($filePath), 9);
            return $filePath;
        }

        if (in_array($format, ['jpg', 'jpeg'])) {
            $fileName = Str::random(40) . '.jpg';
            $filePath = $directory . '/' . $fileName;
            imagejpeg($image, Storage::disk('public')->path($filePath), 95);
            return $filePath;
        }

        return null;
    }

    // =========================================================================
    // MIME / HTTP HELPERS
    // =========================================================================

    private function detectMime(string $filePath, string $bytes): string
    {
        if (function_exists('finfo_file')) {
            $mime = @(new \finfo(FILEINFO_MIME_TYPE))->file($filePath);
            if ($mime && str_starts_with($mime, 'image/')) return $mime;
        }
        return $this->detectMimeFromBytes($bytes);
    }

    private function detectMimeFromBytes(string $bytes): string
    {
        if (function_exists('finfo_buffer')) {
            $mime = @(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if ($mime && str_starts_with($mime, 'image/')) return $mime;
        }
        if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") return 'image/png';
        if (substr($bytes, 0, 3) === "\xFF\xD8\xFF")      return 'image/jpeg';
        if (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a') return 'image/gif';
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
        return 'image/png';
    }

    private function httpFetch(string $url): ?string
    {
        $ctx  = stream_context_create([
            'http' => ['timeout' => 15, 'follow_location' => true, 'user_agent' => 'Mozilla/5.0'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        return ($data !== false && strlen($data) > 50) ? $data : null;
    }

    private function cleanupTemp(string $path): void
    {
        if ($path && strpos($path, sys_get_temp_dir()) === 0 && file_exists($path)) {
            @unlink($path);
        }
    }

    // =========================================================================
    // MISC HELPERS
    // =========================================================================

    private function normalizeTemplateData(array $data): array
    {
        $mapping = ['training_center_logo_url' => 'training_center_logo', 'acc_logo_url' => 'acc_logo', 'qr_code_url' => 'qr_code'];
        foreach ($mapping as $apiKey => $templateKey) {
            if (isset($data[$apiKey]) && !isset($data[$templateKey])) {
                $data[$templateKey] = $data[$apiKey];
            }
        }
        return $data;
    }

    private function normalizeOrientation(?string $orientation): string
    {
        $o = strtolower(trim((string)$orientation));
        return in_array($o, ['portrait', 'landscape']) ? $o : 'landscape';
    }

    private function getPageDimensions(string $orientation): array
    {
        return $orientation === 'portrait' ? ['width' => 848, 'height' => 1200] : ['width' => 1200, 'height' => 848];
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return ['r' => hexdec(substr($hex, 0, 2)), 'g' => hexdec(substr($hex, 2, 2)), 'b' => hexdec(substr($hex, 4, 2))];
    }

    private function getCertificateApiUrl(string $filePath): string
    {
        return url('/api/storage/' . $filePath);
    }

    private function getQrCodeUrl(string $verificationCode): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'data'   => url('/api/certificates/verify/' . $verificationCode),
            'size'   => '200x200',
            'format' => 'png',
        ]);
    }

    private function getFontPath(string $fontFamily): ?string
    {
        $projectFontDir = resource_path('fonts');
        $fonts = [
            'Arial'           => ['arial.ttf', 'Arial.ttf', 'LiberationSans-Regular.ttf', 'DejaVuSans.ttf'],
            'Helvetica'       => ['arial.ttf', 'LiberationSans-Regular.ttf', 'DejaVuSans.ttf'],
            'Times New Roman' => ['times.ttf', 'Times.ttf', 'LiberationSerif-Regular.ttf', 'DejaVuSerif.ttf'],
            'Courier New'     => ['courier.ttf', 'cour.ttf', 'LiberationMono-Regular.ttf', 'DejaVuSansMono.ttf'],
            'Verdana'         => ['verdana.ttf', 'Verdana.ttf', 'DejaVuSans.ttf'],
            'Georgia'         => ['georgia.ttf', 'Georgia.ttf', 'LiberationSerif-Regular.ttf'],
            'Tahoma'          => ['tahoma.ttf', 'Tahoma.ttf', 'DejaVuSans.ttf'],
            'Trebuchet MS'    => ['trebuchet.ttf', 'trebuc.ttf', 'DejaVuSans.ttf'],
            'Impact'          => ['impact.ttf', 'Impact.ttf', 'DejaVuSans-Bold.ttf'],
        ];

        $searchDirs = array_filter([
            $projectFontDir,
            '/usr/share/fonts/truetype/liberation/',
            '/usr/share/fonts/truetype/dejavu/',
            '/usr/share/fonts/TTF/',
            '/usr/share/fonts/truetype/',
            storage_path('fonts/'),
            'C:\\Windows\\Fonts\\',
            '/System/Library/Fonts/Supplemental/',
        ], 'is_dir');

        $fileList = $fonts[$fontFamily] ?? [strtolower(str_replace(' ', '', $fontFamily)) . '.ttf'];

        foreach ($searchDirs as $dir) {
            foreach ($fileList as $file) {
                $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
                if (file_exists($path) && is_readable($path)) return $path;
            }
        }

        Log::warning('Font not found', ['font_family' => $fontFamily]);
        return null;
    }
}