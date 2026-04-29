<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\TrainingCenter;
use App\Models\TrainingClass;
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
                          'training_center_logo_url', 'acc_logo_url', 'qr_code_url',
                          'instructor_photo', 'trainee_photo'];

            foreach ($imageKeys as $key) {
                if (!empty($data[$key])) {
                    $uri = $this->toDataUri($data[$key]);
                    if ($uri) {
                        $data[$key] = $uri;
                    } else {
                        Log::warning("Could not embed image for key '{$key}'", ['value' => $data[$key]]);
                        $data[$key] = '';
                    }
                }
            }

            // Also pre-resolve qr_code if it was not provided but verification_code was
            if (empty($data['qr_code']) && !empty($data['verification_code'])) {
                $qrUrl = $this->getQrCodeUrl($data['verification_code']);
                $uri   = $this->toDataUri($qrUrl);
                $data['qr_code'] = $uri ?: '';
            }

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
            // STEP 3 – Embed any remaining remote image URLs
            // ------------------------------------------------------------------
            $html = $this->embedRemainingRemoteImages($html);

            // ------------------------------------------------------------------
            // STEP 4 – Page size (certificate page)
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
            // STEP 5 – When include_card: generate two separate PDFs (certificate
            // only + card only), each with its own page size. Otherwise one PDF.
            // ------------------------------------------------------------------
            $directory = 'certificates/' . $template->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $fullPath = Storage::disk('public')->path($filePath);

            if ($template->include_card) {
                $cardDims = $this->getCardDimensionsInPt($template);
                $cardWidthPt  = $cardDims['width_pt'];
                $cardHeightPt = $cardDims['height_pt'];
                $frontCardDiv = $this->buildCardDiv($template, $data, $cardWidthPt, $cardHeightPt, 'front');
                $backCardDiv = $this->buildCardDiv($template, $data, $cardWidthPt, $cardHeightPt, 'back');
                $cardDivs = array_values(array_filter([$frontCardDiv, $backCardDiv]));
                if (!empty($cardDivs)) {
                    $result = $this->generateCertificateAndCardAsSeparatePdfs(
                        $template,
                        $html,
                        $cardDivs,
                        $widthPt,
                        $heightPt,
                        $cardWidthPt,
                        $cardHeightPt,
                        $filePath,
                        $fullPath
                    );
                    if ($result !== null) {
                        return $result;
                    }
                    // Fallback: single PDF with card appended (card page will have cert size)
                    $cardPageCss = sprintf(
                        '@page card { size: %spt %spt; margin: 0; } .card-page{page: card; page-break-before:always; width:%spt; height:%spt; position:relative; overflow:hidden; margin:0; padding:0;}',
                        round($cardWidthPt, 2),
                        round($cardHeightPt, 2),
                        round($cardWidthPt, 2),
                        round($cardHeightPt, 2)
                    );
                    $html = $this->injectCss($html, $cardPageCss);
                    $cardDiv = implode('', $cardDivs);
                    if (stripos($html, '</body>') !== false) {
                        $html = str_ireplace('</body>', $cardDiv . '</body>', $html);
                    } else {
                        $html .= $cardDiv;
                    }
                }
            }

            // ------------------------------------------------------------------
            // STEP 6 – Render PDF (single page or cert+card in one doc)
            // ------------------------------------------------------------------
            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');

            // ------------------------------------------------------------------
            // STEP 7 – Save
            // ------------------------------------------------------------------
            $pdf->save($fullPath);

            return [
                'success'   => true,
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
    // CARD PAGE BUILDER
    // -------------------------------------------------------------------------

    /**
     * Get card page dimensions in points from template (card_template_html or defaults).
     * Parses width/height from the first div in card_template_html (e.g. width: 856px; height: 540px).
     * Returns null when no card content; otherwise ['width_pt' => float, 'height_pt' => float].
     */
    private function getCardDimensionsInPt(CertificateTemplate $template): ?array
    {
        $html = $template->card_template_html ?: ($template->card_back_template_html ?? '');
        $widthPx  = 856;
        $heightPx = 540;
        if (preg_match('/width\s*:\s*(\d+)\s*px/i', $html, $mw)) {
            $widthPx = (int) $mw[1];
        }
        if (preg_match('/height\s*:\s*(\d+)\s*px/i', $html, $mh)) {
            $heightPx = (int) $mh[1];
        }
        // CSS: 96px = 72pt => pt = px * 72/96
        return [
            'width_pt'  => round($widthPx * 72 / 96, 2),
            'height_pt' => round($heightPx * 72 / 96, 2),
        ];
    }

    /**
     * Build the card page as a single <div> to be injected into the main HTML document.
     * Returning a <div> (not a full document) prevents DomPDF from inserting an extra
     * blank page between the certificate and the card.
     *
     * Priority:
     *  1. card_template_html  – full custom HTML stripped to body content
     *  2. card_background_image_url + card_config_json – auto-rendered layout
     *  3. card_background_image_url only – full-bleed background image page
     *
     * Returns null when no card content is configured.
     */
    private function buildCardDiv(CertificateTemplate $template, array $data, float $widthPt, float $heightPt, string $side = 'front'): ?string
    {
        $isBack = $side === 'back';
        $templateHtml = $isBack ? $template->card_back_template_html : $template->card_template_html;
        $backgroundImageUrl = $isBack ? $template->card_back_background_image_url : $template->card_background_image_url;
        $configJson = $isBack ? $template->card_back_config_json : $template->card_config_json;

        // ── Path 1: custom HTML – extract body content and wrap in card div ───
        if (!empty($templateHtml)) {
            $html = $this->replaceTemplateVariables($templateHtml, $data);
            $html = $this->embedRemainingRemoteImages($html);
            // Extract body content so we don't nest full documents
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
                $bodyContent = $m[1];
            } else {
                $bodyContent = $html;
            }
            return sprintf(
                '<div class="card-page" style="width:%spt;height:%spt;position:relative;overflow:hidden;">%s</div>',
                round($widthPt, 2),
                round($heightPt, 2),
                $bodyContent
            );
        }

        // ── Path 2 & 3: background image (+ optional config overlay) ─────────
        if (empty($backgroundImageUrl)) {
            return null;
        }

        $bgUri = $this->toDataUri($backgroundImageUrl) ?? '';

        // Build overlay elements from card_config_json
        $overlayHtml = '';
        $config   = $configJson;
        $elements = is_array($config) && isset($config['elements'])
            ? $config['elements']
            : (is_array($config) ? $config : []);

        foreach ($elements as $el) {
            $type = $el['type'] ?? 'text';
            $x    = round((float)($el['x'] ?? 0) * 100, 4);
            $y    = round((float)($el['y'] ?? 0) * 100, 4);
            $w    = round((float)($el['width']  ?? 0.3) * 100, 4);
            $h    = round((float)($el['height'] ?? 0.1) * 100, 4);

            // Resolve {{variable}} placeholder from $data
            $variable = $el['variable'] ?? '';
            if (preg_match('/\{\{([^}]+)\}\}/', $variable, $matches)) {
                $key   = trim($matches[1]);
                $value = $data[$key] ?? '';
            } else {
                // No braces — treat as literal key name
                $value = $data[$variable] ?? $variable;
            }

            $style = sprintf('position:absolute;left:%s%%;top:%s%%;', $x, $y);

            if ($type === 'image') {
                // Value may already be a data-URI (pre-resolved in Step 1 of generatePdfFromBlade)
                $imgUri = str_starts_with((string) $value, 'data:')
                    ? (string) $value
                    : ($this->toDataUri((string) $value) ?? '');
                if ($imgUri) {
                    $style .= sprintf('width:%s%%;height:%s%%;', $w, $h);
                    $overlayHtml .= sprintf(
                        '<img src="%s" style="%s object-fit:contain;" />',
                        $imgUri,
                        $style
                    );
                }
            } else {
                $fontSize   = (int)($el['font_size']   ?? $el['fontSize']   ?? 14);
                $fontFamily = $el['font_family'] ?? $el['fontFamily'] ?? 'serif';
                $color      = $el['color']      ?? '#000000';
                $align      = $el['text_align'] ?? $el['textAlign']  ?? 'left';
                $weight     = $el['font_weight'] ?? $el['fontWeight'] ?? 'normal';
                $style .= sprintf(
                    'font-size:%spx;font-family:%s;color:%s;text-align:%s;font-weight:%s;white-space:nowrap;',
                    $fontSize,
                    htmlspecialchars($fontFamily, ENT_QUOTES),
                    htmlspecialchars($color,      ENT_QUOTES),
                    htmlspecialchars($align,      ENT_QUOTES),
                    htmlspecialchars($weight,     ENT_QUOTES)
                );
                // Do NOT htmlspecialchars the value — it may contain already-safe text from $data
                $overlayHtml .= sprintf(
                    '<div style="%s">%s</div>',
                    $style,
                    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                );
            }
        }

        $bgStyle = $bgUri
            ? sprintf("background-image:url('%s');background-size:cover;background-position:center;", $bgUri)
            : 'background:#ffffff;';

        return sprintf(
            '<div class="card-page" style="width:%spt;height:%spt;position:relative;overflow:hidden;%s">%s</div>',
            round($widthPt, 2),
            round($heightPt, 2),
            $bgStyle,
            $overlayHtml
        );
    }

    /**
     * Inject CSS into an HTML string (inserts into existing <style> or adds <head>).
     */
    private function injectCss(string $html, string $css): string
    {
        if (preg_match('/<style[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            return substr_replace($html, "\n" . $css . "\n", $pos, 0);
        }
        if (stripos($html, '<head>') !== false) {
            return preg_replace('/<head>/i', "<head>\n<style>{$css}</style>", $html, 1);
        }
        return "<head><style>{$css}</style></head>" . $html;
    }

    /**
     * Generate two separate PDF files: one for the certificate only, one for the card only.
     * Each uses its own page size. Returns result array with file_path, file_url,
     * card_file_path, card_file_url; or null on failure.
     *
     * @param string $certFilePath Relative path for the certificate PDF (e.g. certificates/7/xxx.pdf)
     * @param string $certFullPath Absolute path where to save the certificate PDF
     */
    private function generateCertificateAndCardAsSeparatePdfs(
        CertificateTemplate $template,
        string $certificateHtml,
        array $cardDivs,
        float $certWidthPt,
        float $certHeightPt,
        float $cardWidthPt,
        float $cardHeightPt,
        string $certFilePath,
        string $certFullPath
    ): ?array {
        try {
            $certPdf = Pdf::loadHTML($certificateHtml)
                ->setPaper([0, 0, $certWidthPt, $certHeightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');
            $certPdf->save($certFullPath);

            $baseName = pathinfo($certFilePath, PATHINFO_FILENAME);
            $cardFilePath = dirname($certFilePath) . '/' . $baseName . '_card.pdf';
            $cardFullPath = Storage::disk('public')->path($cardFilePath);

            $cardPageCss = sprintf(
                '@page { size: %spt %spt; margin: 0; } body { margin: 0; padding: 0; }',
                round($cardWidthPt, 2),
                round($cardHeightPt, 2)
            );
            $cardBody = '';
            foreach ($cardDivs as $index => $cardDiv) {
                if ($index > 0) {
                    $cardBody .= '<div style="page-break-before:always;"></div>';
                }
                $cardBody .= $cardDiv;
            }
            $cardHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $cardPageCss . '</style></head><body>' . $cardBody . '</body></html>';
            $cardPdf = Pdf::loadHTML($cardHtml)
                ->setPaper([0, 0, $cardWidthPt, $cardHeightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');
            $cardPdf->save($cardFullPath);

            return [
                'success'        => true,
                'file_path'      => $certFilePath,
                'file_url'       => $this->getCertificateApiUrl($certFilePath),
                'card_file_path' => $cardFilePath,
                'card_file_url'  => $this->getCertificateApiUrl($cardFilePath),
            ];
        } catch (\Throwable $e) {
            Log::warning('Certificate/card separate PDFs failed', [
                'template_id' => $template->id,
                'error'       => $e->getMessage(),
            ]);
            return null;
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
                Log::debug('toDataUri: embedded from local path', ['source' => $source, 'path' => $localPath, 'mime' => $mime, 'bytes' => strlen($bytes)]);
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        // ------------------------------------------------------------------
        // Fall back to HTTP fetch (external URLs, QR code API, etc.)
        // Try both the original URL and the public storage URL form.
        // ------------------------------------------------------------------
        $candidates = [$source];
        // Also try the storage base URL form in case logo_url stored without host
        if (str_starts_with($source, '/') && !str_starts_with($source, '//')) {
            $candidates[] = url($source);
        }

        foreach ($candidates as $fetchUrl) {
            if (!filter_var($fetchUrl, FILTER_VALIDATE_URL)) {
                continue;
            }
            $bytes = $this->httpFetch($fetchUrl);
            if ($bytes && strlen($bytes) > 50) {
                $mime = $this->detectMimeFromBytes($bytes);
                Log::debug('toDataUri: embedded from HTTP fetch', ['source' => $source, 'fetch_url' => $fetchUrl, 'mime' => $mime, 'bytes' => strlen($bytes)]);
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
            Log::warning('toDataUri: HTTP fetch failed', ['fetch_url' => $fetchUrl]);
        }

        Log::warning('toDataUri: could not convert image', [
            'source'      => substr($source, 0, 300),
            'local_path'  => $localPath,
            'storage_url' => Storage::disk('public')->url('') ,
            'app_url'     => config('app.url'),
        ]);
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
     * Strategy: try the original URL first (covers the common case where logo_url
     * is already in Storage::disk('public')->url() format), then try after
     * fixStorageUrl() in case the URL has a malformed double-prefix.
     */
    private function resolveToLocalPath(string $source): ?string
    {
        // Already a local absolute path
        if (str_starts_with($source, '/') && !str_starts_with($source, '//') && file_exists($source)) {
            return $source;
        }

        // Try original URL first, then fixStorageUrl variant
        foreach ([$source, $this->fixStorageUrl($source)] as $candidate) {
            $path = $this->tryUrlToStoragePath($candidate);
            if ($path) {
                Log::debug('resolveToLocalPath: found', ['source' => $source, 'candidate' => $candidate, 'path' => $path]);
                return $path;
            }
        }

        Log::debug('resolveToLocalPath: no local path found, will HTTP-fetch', ['source' => $source]);
        return null;
    }

    /**
     * Attempt to map a URL to a local public-disk path using several heuristics.
     * Returns the path only if it exists.
     */
    private function tryUrlToStoragePath(string $url): ?string
    {
        // Heuristic 1: URL starts with Storage::disk('public')->url() base
        $storageBaseUrl = rtrim(Storage::disk('public')->url(''), '/');
        if ($storageBaseUrl && (str_starts_with($url, $storageBaseUrl . '/') || $url === $storageBaseUrl)) {
            $relative = ltrim(substr($url, strlen($storageBaseUrl)), '/');
            $path     = Storage::disk('public')->path($relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Heuristic 2: match /laravel/storage/app/public/... or /storage/app/public/... in URL
        if (preg_match('#/(?:laravel/)?storage/app/public/(.+)$#i', $url, $m)) {
            $path = Storage::disk('public')->path($m[1]);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Heuristic 3: match /laravel/storage/... or /storage/... (without app/public)
        if (preg_match('#/(?:laravel/)?storage/(.+)$#', $url, $m)) {
            $relative = preg_replace('#^app/public/#i', '', $m[1]);
            $path     = Storage::disk('public')->path($relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Heuristic 4: same app host → strip host and try relative to public disk
        $appUrl = rtrim(config('app.url', ''), '/');
        if ($appUrl && str_starts_with($url, $appUrl)) {
            $relative = ltrim(substr($url, strlen($appUrl)), '/');
            $relative = preg_replace('#^storage/#i', '', $relative);
            $path     = Storage::disk('public')->path($relative);
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
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
     * generatePdfFromBlade). Empty/null values are replaced with empty string
     * so layout is preserved (e.g. group-admin certificate). We do NOT remove
     * entire divs for empty values, as that can remove the certificate wrapper
     * and produce a blank PDF.
     */
    private function replaceTemplateVariables(string $html, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'data:')) {
                $safeValue = $value;
            } else {
                $safeValue = $value !== null && $value !== ''
                    ? htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                    : '';
            }

            $html = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i', $safeValue, $html) ?? $html;

            if ($key === 'verification_code') {
                foreach (['verificationCode', 'VerificationCode', 'VERIFICATION_CODE', 'verification-code'] as $alt) {
                    $html = preg_replace('/\{\{\s*' . preg_quote($alt, '/') . '\s*\}\}/i', $safeValue, $html) ?? $html;
                }
            }
        }

        $html = preg_replace('/\n\s*\n/', "\n", $html) ?? $html;
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
        $result = preg_replace_callback(
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
        $html = $result ?? $html;   // ← guard: if preg_replace_callback returns null, keep original
    
        // <img src="..."> – skip data: and file://
        $result = preg_replace_callback(
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
        $html = $result ?? $html;   // ← guard
    
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
            $data['serial_number']     = $verificationCode;
            $data['qr_code']           = $this->getQrCodeUrl($verificationCode);
        }

        return $this->generate($template, $data, 'pdf');
    }

    public function generateInstructorCertificate(CertificateTemplate $template, $instructor, $course, $acc, ?string $verificationCode = null): array
    {
        if (!$instructor->relationLoaded('trainingCenter')) {
            $instructor->load('trainingCenter');
        }

        $data = [
            'instructor_name'         => trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
            'instructor_first_name'   => $instructor->first_name ?? '',
            'instructor_last_name'    => $instructor->last_name ?? '',
            'instructor_email'        => $instructor->email ?? '',
            'instructor_id_number'    => $instructor->id_number ?? '',
            'instructor_country'      => $instructor->country ?? '',
            'instructor_city'         => $instructor->city ?? '',
            'instructor_photo'        => $this->resolveLogoUrl($instructor->photo_url ?? $instructor->profile_photo_url ?? null),
            'course_name'             => $course->name ?? '',
            'course_name_ar'          => $course->name_ar ?? '',
            'course_code'             => $course->code ?? '',
            'training_center_name'    => $instructor->trainingCenter?->name ?? '',
            'acc_name'                => $acc->name ?? '',
            'acc_legal_name'          => $acc->legal_name ?? '',
            'acc_registration_number' => $acc->registration_number ?? '',
            'acc_country'             => $acc->country ?? '',
            'issue_date'              => now()->format('Y-m-d'),
            'issue_date_formatted'    => now()->format('F j, Y'),
            'expiry_date'             => now()->addYears(3)->format('Y-m-d'),
            'training_center_logo'    => $this->resolveLogoUrl($instructor->trainingCenter?->logo_url ?? null),
            'acc_logo'                => $this->resolveLogoUrl($acc->logo_url ?? null),
        ];

        if ($verificationCode) {
            $data['verification_code'] = $verificationCode;
            $data['serial_number']     = $verificationCode;
            $data['qr_code']           = $this->getQrCodeUrl($verificationCode);
        }

        $trainingCenter = $instructor->trainingCenter;
        if ($trainingCenter) {
            $data = $this->appendCourseCertificateDynamicFields($trainingCenter, null, $data);
        } else {
            $data = array_merge($data, [
                'training_provider_name' => '',
                'training_provider_phone' => '',
                'training_provider_id_number' => '',
                'delivery_method' => '',
            ]);
        }

        return $this->generate($template, $data, 'pdf');
    }

    public function generatePdf(CertificateTemplate $template, array $data): array
    {
        return $this->generate($template, $data, 'pdf');
    }

    /**
     * Merge dynamic fields: training provider name, phone, government ID, delivery method.
     * Used for course completion PDFs (with optional training class for delivery) and for
     * instructor authorization PDFs (delivery usually empty; pass null training class).
     */
    public function appendCourseCertificateDynamicFields(
        TrainingCenter $trainingCenter,
        ?TrainingClass $trainingClass,
        array $data
    ): array {
        $providerName = trim((string) ($trainingCenter->name ?? ''));

        $delivery = '';
        if ($trainingClass !== null) {
            $parts = array_filter([
                $trainingClass->location ? trim((string) $trainingClass->location) : null,
                $trainingClass->location_details ? trim((string) $trainingClass->location_details) : null,
            ]);
            $delivery = implode(' — ', $parts);
        }

        return array_merge($data, [
            'training_provider_name' => $providerName,
            'training_provider_phone' => trim((string) ($trainingCenter->phone ?? '')),
            'training_provider_id_number' => trim((string) ($trainingCenter->company_gov_registry_number ?? '')),
            'delivery_method' => $delivery,
        ]);
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
        // So {{student_name}} works when caller only sends trainee_name
        if (!isset($data['student_name']) && isset($data['trainee_name'])) {
            $data['student_name'] = $data['trainee_name'];
        }
        // So card can use trainee_photo when provided as trainee_image_url
        if (!isset($data['trainee_photo']) && isset($data['trainee_image_url'])) {
            $data['trainee_photo'] = $data['trainee_image_url'];
        }
        // Training provider mirrors training center when only one is supplied
        if (!isset($data['training_provider_name']) && isset($data['training_center_name'])) {
            $data['training_provider_name'] = $data['training_center_name'];
        }
        if (!isset($data['training_provider_phone']) && isset($data['training_center_phone'])) {
            $data['training_provider_phone'] = $data['training_center_phone'];
        }
        if (!isset($data['training_provider_id_number']) && isset($data['company_gov_registry_number'])) {
            $data['training_provider_id_number'] = $data['company_gov_registry_number'];
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