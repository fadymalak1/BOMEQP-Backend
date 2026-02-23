<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateGenerationService
{
    /**
     * Generate a certificate image/PDF from a template
     * 
     * @param CertificateTemplate $template
     * @param array $data Key-value pairs for template variables (e.g., ['student_name' => 'John Doe', 'course_name' => 'Fire Safety'])
     * @param string $outputFormat 'png' or 'pdf'
     * @return array Returns ['success' => true, 'file_path' => string, 'file_url' => string] or error array
     */
    public function generate(CertificateTemplate $template, array $data, string $outputFormat = 'png'): array
    {
        try {
            // Normalize data: map API keys to template variable names
            $data = $this->normalizeTemplateData($data);

            // For PDF generation, use template_html method (generate image first, then embed in PDF)
            if ($outputFormat === 'pdf') {
                return $this->generatePdfFromBlade($template, $data);
            }

            // Validate template has required data for PNG/JPG generation
            if (!$template->background_image_url || !$template->config_json) {
                return [
                    'success' => false,
                    'message' => 'Template missing background image or configuration',
                ];
            }

            // For PNG/JPG, use GD library (legacy method)
            // Download/load background image
            $backgroundImagePath = $this->getImagePath($template->background_image_url);
            if (!$backgroundImagePath) {
                return [
                    'success' => false,
                    'message' => 'Failed to load background image',
                ];
            }

            // Create image resource from background
            $imageInfo = getimagesize($backgroundImagePath);
            if (!$imageInfo) {
                return [
                    'success' => false,
                    'message' => 'Invalid background image format',
                ];
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            // Create image resource based on type
            $image = $this->createImageResource($backgroundImagePath, $mimeType);
            if (!$image) {
                return [
                    'success' => false,
                    'message' => 'Failed to create image resource',
                ];
            }

            // Get elements from config (support both { elements: [...] } and direct array)
            $config = $template->config_json;
            $elements = is_array($config) && isset($config['elements']) ? $config['elements'] : $config;
            $elements = is_array($elements) ? $elements : [];

            // Apply placeholders (text and image elements)
            $this->applyPlaceholders($image, $elements, $data, $width, $height);

            // Generate output file
            $outputPath = $this->saveCertificate($image, $template, $data, $outputFormat, $width, $height);

            // Clean up
            imagedestroy($image);
            if ($backgroundImagePath && file_exists($backgroundImagePath) && strpos($backgroundImagePath, sys_get_temp_dir()) === 0) {
                @unlink($backgroundImagePath);
            }

            if ($outputPath) {
                // Use API route URL instead of direct storage URL
                $fileUrl = $this->getCertificateApiUrl($outputPath);
                return [
                    'success' => true,
                    'file_path' => $outputPath,
                    'file_url' => $fileUrl,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to save certificate',
            ];

        } catch (\Exception $e) {
            Log::error('Certificate generation error', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Certificate generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get local path to image (download if remote)
     */
    private function getImagePath(string $imageUrl): ?string
    {
        // If it's a local storage URL, convert to path
        $storageBaseUrl = rtrim(Storage::disk('public')->url(''), '/');
        if (str_starts_with($imageUrl, $storageBaseUrl . '/') || $imageUrl === $storageBaseUrl) {
            $relativePath = preg_replace('#^' . preg_quote($storageBaseUrl, '#') . '/?#', '', $imageUrl);
            $relativePath = ltrim($relativePath, '/');
            $fullPath = Storage::disk('public')->path($relativePath);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Extract path from URL like .../storage/... or .../laravel/storage/app/public/...
        if (preg_match('#/(?:storage/|laravel/storage/)(.+)$#', $imageUrl, $m)) {
            $relativePath = $m[1];
            // Public disk root is storage/app/public — strip duplicate "app/public/" if present
            $relativePath = preg_replace('#^app/public/#i', '', $relativePath);
            $fullPath = Storage::disk('public')->path($relativePath);
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // If it's a full URL, download it
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'follow_location' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $imageData = @file_get_contents($imageUrl, false, $context);
            if ($imageData) {
                $tempPath = sys_get_temp_dir() . '/' . Str::random(20) . '.tmp';
                if (file_put_contents($tempPath, $imageData)) {
                    return $tempPath;
                }
            }
        }

        return null;
    }

    /**
     * Create image resource from file
     */
    private function createImageResource(string $filePath, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($filePath);
            
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                // Enable alpha blending for PNG
                imagealphablending($image, true);
                imagesavealpha($image, true);
                return $image;
            
            case 'image/gif':
                return imagecreatefromgif($filePath);
            
            default:
                return null;
        }
    }

    /**
     * Apply placeholders (text and image elements) to image
     */
    private function applyPlaceholders($image, array $elements, array $data, int $imageWidth, int $imageHeight): void
    {
        foreach ($elements as $placeholder) {
            if (!isset($placeholder['variable'])) {
                continue;
            }

            $type = $placeholder['type'] ?? 'text';
            $variable = $placeholder['variable'];

            // Calculate absolute coordinates from normalized (0-1) values
            $x = (float)($placeholder['x'] ?? 0.5) * $imageWidth;
            $y = (float)($placeholder['y'] ?? 0.5) * $imageHeight;

            if ($type === 'image') {
                // Image element: overlay image from URL
                $variableKey = preg_match('/\{\{([^}]+)\}\}/', $variable, $m) ? trim($m[1]) : $variable;
                $imageUrl = $data[$variableKey] ?? $data[$variable] ?? null;
                if ($imageUrl) {
                    $elWidth = (float)($placeholder['width'] ?? 0.2) * $imageWidth;
                    $elHeight = (float)($placeholder['height'] ?? 0.15) * $imageHeight;
                    $this->overlayImage($image, $imageUrl, (int)$x, (int)$y, (int)$elWidth, (int)$elHeight);
                }
                continue;
            }

            // Text element
            if (preg_match('/\{\{([^}]+)\}\}/', $variable, $matches)) {
                $variableKey = trim($matches[1]);
                $text = $data[$variableKey] ?? $variable;
            } else {
                $text = $variable;
            }

            $fontSize = (int)($placeholder['font_size'] ?? $placeholder['fontSize'] ?? 24);
            $colorHex = $placeholder['color'] ?? '#000000';
            $fontFamily = $placeholder['font_family'] ?? $placeholder['fontFamily'] ?? 'Arial';
            $textAlign = $placeholder['text_align'] ?? $placeholder['textAlign'] ?? 'left';

            $color = $this->hexToRgb($colorHex);
            $textColor = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);

            $fontPath = $this->getFontPath($fontFamily);
            if ($fontPath && function_exists('imagettftext') && function_exists('imagettfbbox')) {
                $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
                if ($bbox !== false) {
                    $textWidth = abs($bbox[4] - $bbox[0]);
                    if ($textAlign === 'center') {
                        $x = $x - ($textWidth / 2);
                    } elseif ($textAlign === 'right') {
                        $x = $x - $textWidth;
                    }
                }
                imagettftext($image, $fontSize, 0, (int)$x, (int)$y, $textColor, $fontPath, $text);
            } else {
                imagestring($image, 5, (int)$x, (int)$y - 20, $text, $textColor);
            }
        }
    }

    /**
     * Overlay an image from URL onto the certificate at given position/size
     */
    private function overlayImage($destImage, string $imageUrl, int $x, int $y, int $width, int $height): void
    {
        $srcPath = $this->getImagePath($imageUrl);
        if (!$srcPath) {
            return;
        }
        $info = @getimagesize($srcPath);
        if (!$info) {
            return;
        }
        $srcImage = $this->createImageResource($srcPath, $info['mime']);
        if (!$srcImage) {
            return;
        }
        if (function_exists('imagecopyresampled')) {
            imagecopyresampled($destImage, $srcImage, $x, $y, 0, 0, $width, $height, imagesx($srcImage), imagesy($srcImage));
        } else {
            imagecopyresized($destImage, $srcImage, $x, $y, 0, 0, $width, $height, imagesx($srcImage), imagesy($srcImage));
        }
        imagedestroy($srcImage);
        if ($srcPath && strpos($srcPath, sys_get_temp_dir()) === 0 && file_exists($srcPath)) {
            @unlink($srcPath);
        }
    }

    /**
     * Normalize template data: map API payload keys to template variable names
     */
    private function normalizeTemplateData(array $data): array
    {
        $mapping = [
            'training_center_logo_url' => 'training_center_logo',
            'acc_logo_url' => 'acc_logo',
            'qr_code_url' => 'qr_code',
        ];
        foreach ($mapping as $apiKey => $templateKey) {
            if (isset($data[$apiKey]) && !isset($data[$templateKey])) {
                $data[$templateKey] = $data[$apiKey];
            }
        }
        return $data;
    }

    /**
     * Normalize orientation string from frontend/DB to portrait|landscape
     */
    private function normalizeOrientation(?string $orientation): string
    {
        $o = strtolower(trim((string) $orientation));
        if ($o === 'portrait' || $o === 'landscape') {
            return $o;
        }
        return 'landscape';
    }

    /**
     * Get page dimensions based on orientation
     * Landscape: 1200x848px, Portrait: 848x1200px
     */
    private function getPageDimensions(string $orientation): array
    {
        $dimensions = [
            'landscape' => ['width' => 1200, 'height' => 848],
            'portrait' => ['width' => 848, 'height' => 1200],
        ];
        return $dimensions[$orientation] ?? $dimensions['landscape'];
    }

    /**
     * Convert hex color to RGB
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Get font file path - matches frontend font families
     * Supports: Arial, Helvetica, Times New Roman, Courier New, Verdana, Georgia, Tahoma, Trebuchet MS, Impact
     * Optimized for Linux/cPanel production servers
     */
    private function getFontPath(string $fontFamily): ?string
    {
        // First, check if fonts are available in project resources/fonts directory
        $projectFontDir = resource_path('fonts');
        $projectFonts = [
            'Arial' => ['arial.ttf', 'Arial.ttf', 'arial.TTF', 'ARIAL.TTF'],
            'Helvetica' => ['arial.ttf', 'helvetica.ttf', 'Arial.ttf', 'Helvetica.ttf'],
            'Times New Roman' => ['times.ttf', 'Times.ttf', 'times-new-roman.ttf', 'TimesNewRoman.ttf'],
            'Courier New' => ['courier.ttf', 'cour.ttf', 'Courier.ttf', 'courier-new.ttf'],
            'Courier' => ['courier.ttf', 'cour.ttf', 'Courier.ttf'],
            'Verdana' => ['verdana.ttf', 'Verdana.ttf'],
            'Georgia' => ['georgia.ttf', 'Georgia.ttf'],
            'Tahoma' => ['tahoma.ttf', 'Tahoma.ttf'],
            'Trebuchet MS' => ['trebuchet.ttf', 'trebuc.ttf', 'Trebuchet.ttf', 'trebuchet-ms.ttf'],
            'Impact' => ['impact.ttf', 'Impact.ttf'],
        ];

        if (isset($projectFonts[$fontFamily]) && is_dir($projectFontDir)) {
            foreach ($projectFonts[$fontFamily] as $fontFile) {
                $fontPath = $projectFontDir . '/' . $fontFile;
                if (file_exists($fontPath)) {
                    Log::info("Using project font", ['font' => $fontFamily, 'path' => $fontPath]);
                    return $fontPath;
                }
            }
        }

        // Try Linux/cPanel font paths (check multiple common locations)
        $linuxFontPaths = [
            // Standard Linux font directories
            '/usr/share/fonts/truetype/liberation/',
            '/usr/share/fonts/truetype/dejavu/',
            '/usr/share/fonts/TTF/',
            '/usr/share/fonts/truetype/',
            // cPanel specific paths
            '/usr/local/lib/X11/fonts/TTF/',
            '/usr/X11R6/lib/X11/fonts/TTF/',
            // Alternative locations
            '/usr/share/fonts/',
            '/var/www/html/fonts/',
            storage_path('fonts/'), // Laravel storage fonts
        ];

        $linuxFontFiles = [
            'Arial' => ['arial.ttf', 'Arial.ttf', 'LiberationSans-Regular.ttf', 'DejaVuSans.ttf'],
            'Helvetica' => ['arial.ttf', 'Arial.ttf', 'helvetica.ttf', 'LiberationSans-Regular.ttf', 'DejaVuSans.ttf'],
            'Times New Roman' => ['times.ttf', 'Times.ttf', 'LiberationSerif-Regular.ttf', 'DejaVuSerif.ttf'],
            'Courier New' => ['courier.ttf', 'cour.ttf', 'Courier.ttf', 'LiberationMono-Regular.ttf', 'DejaVuSansMono.ttf'],
            'Courier' => ['courier.ttf', 'cour.ttf', 'Courier.ttf', 'LiberationMono-Regular.ttf'],
            'Verdana' => ['verdana.ttf', 'Verdana.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf'],
            'Georgia' => ['georgia.ttf', 'Georgia.ttf', 'LiberationSerif-Regular.ttf', 'DejaVuSerif.ttf'],
            'Tahoma' => ['tahoma.ttf', 'Tahoma.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf'],
            'Trebuchet MS' => ['trebuchet.ttf', 'trebuc.ttf', 'Trebuchet.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf'],
            'Impact' => ['impact.ttf', 'Impact.ttf', 'DejaVuSans-Bold.ttf'],
        ];

        if (isset($linuxFontFiles[$fontFamily])) {
            foreach ($linuxFontPaths as $basePath) {
                if (!is_dir($basePath)) {
                    continue;
                }
                
                foreach ($linuxFontFiles[$fontFamily] as $fontFile) {
                    $fontPath = rtrim($basePath, '/') . '/' . $fontFile;
                    if (file_exists($fontPath) && is_readable($fontPath)) {
                        Log::info("Using system font", ['font' => $fontFamily, 'path' => $fontPath]);
                        return $fontPath;
                    }
                }
            }
        }

        // Try Windows fonts (for local development)
        $windowsFonts = [
            'Arial' => 'C:\\Windows\\Fonts\\arial.ttf',
            'Helvetica' => 'C:\\Windows\\Fonts\\arial.ttf',
            'Times New Roman' => 'C:\\Windows\\Fonts\\times.ttf',
            'Courier New' => 'C:\\Windows\\Fonts\\cour.ttf',
            'Courier' => 'C:\\Windows\\Fonts\\cour.ttf',
            'Verdana' => 'C:\\Windows\\Fonts\\verdana.ttf',
            'Georgia' => 'C:\\Windows\\Fonts\\georgia.ttf',
            'Tahoma' => 'C:\\Windows\\Fonts\\tahoma.ttf',
            'Trebuchet MS' => 'C:\\Windows\\Fonts\\trebuc.ttf',
            'Impact' => 'C:\\Windows\\Fonts\\impact.ttf',
        ];

        if (isset($windowsFonts[$fontFamily]) && file_exists($windowsFonts[$fontFamily])) {
            return $windowsFonts[$fontFamily];
        }

        // Try macOS fonts
        $macFonts = [
            'Arial' => '/System/Library/Fonts/Supplemental/Arial.ttf',
            'Helvetica' => '/System/Library/Fonts/Helvetica.ttc',
            'Times New Roman' => '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
            'Courier New' => '/System/Library/Fonts/Courier New.ttf',
            'Verdana' => '/System/Library/Fonts/Supplemental/Verdana.ttf',
            'Georgia' => '/System/Library/Fonts/Supplemental/Georgia.ttf',
        ];

        if (isset($macFonts[$fontFamily]) && file_exists($macFonts[$fontFamily])) {
            return $macFonts[$fontFamily];
        }

        // Final fallback - log warning and return null (will use built-in font)
        Log::warning("Font not found", [
            'font_family' => $fontFamily,
            'checked_paths' => array_merge(
                $linuxFontPaths,
                [resource_path('fonts')],
                array_values($windowsFonts ?? []),
                array_values($macFonts ?? [])
            )
        ]);

        return null;
    }

    /**
     * Generate PDF from template_html: Replace variables and convert HTML to PDF directly
     */
    private function generatePdfFromBlade(CertificateTemplate $template, array $data): array
    {
        try {
            // Validate template_html exists
            if (!$template->template_html) {
                return [
                    'success' => false,
                    'message' => 'Template HTML is missing',
                ];
            }

            // Log data being passed for debugging (especially verification_code)
            if (isset($data['verification_code'])) {
                Log::info('Certificate generation - verification_code present', [
                    'template_id' => $template->id,
                    'verification_code' => $data['verification_code'],
                    'has_verification_code' => true,
                ]);
            } else {
                Log::warning('Certificate generation - verification_code missing', [
                    'template_id' => $template->id,
                    'data_keys' => array_keys($data),
                ]);
            }

            // Replace variables in template_html
            $html = $this->replaceTemplateVariables($template->template_html, $data);

            // Convert all image URLs (background-image, img src, etc.) to base64 data URIs
            // DomPDF fails to load remote images; embedding ensures they render
            $html = $this->embedImageUrlsInHtml($html);

            // Use template orientation only (do not override from HTML so frontend/DB setting wins)
            $orientation = $this->normalizeOrientation($template->orientation ?? 'landscape');
            $dimensions = $this->getPageDimensions($orientation);
            $width = $dimensions['width'];
            $height = $dimensions['height'];

            // Convert dimensions from pixels to points (PDF: 1 inch = 72 points, 96 DPI)
            $widthPt = ($width / 96) * 72;
            $heightPt = ($height / 96) * 72;

            // Inject @page so DomPDF respects size (reinforces setPaper)
            $pageCss = sprintf('@page { size: %spt %spt; margin: 0; }', round($widthPt, 2), round($heightPt, 2));
            if (preg_match('/<style[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
                $insertPos = $m[0][1] + strlen($m[0][0]);
                $html = substr_replace($html, "\n" . $pageCss . "\n", $insertPos, 0);
            } elseif (stripos($html, '<head>') !== false) {
                $html = preg_replace('/<head>/i', "<head>\n<style>{$pageCss}</style>", $html, 1);
            } else {
                $html = preg_replace('/<html/i', "<html>\n<head><style>{$pageCss}</style></head>", $html, 1);
            }

            // Ensure DomPDF chroot includes base and storage so file:// image paths load
            $pdf = app('dompdf.wrapper');
            $options = $pdf->getDomPDF()->getOptions();
            $chroot = array_filter(array_unique(array_merge(
                $options->getChroot() ?: [],
                [realpath(base_path()), realpath(storage_path())]
            )));
            if (!empty($chroot)) {
                $options->setChroot($chroot);
            }

            // Custom size: pass dimensions in order; use 'portrait' so DomPDF does not swap them
            $pdf->loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif');

            // Save PDF
            $directory = 'certificates/' . $template->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $fullPath = Storage::disk('public')->path($filePath);

            $pdf->save($fullPath);

            // Use API route URL instead of direct storage URL
            $fileUrl = $this->getCertificateApiUrl($filePath);

            return [
                'success' => true,
                'file_path' => $filePath,
                'file_url' => $fileUrl,
            ];

        } catch (\Exception $e) {
            Log::error('PDF generation error', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'PDF generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Replace variables in template HTML and remove elements with null values
     * Image URLs (training_center_logo, acc_logo, qr_code) are converted to base64 data URIs
     * so DomPDF can render them without fetching remote URLs (which often fails)
     */
    private function replaceTemplateVariables(string $html, array $data): string
    {
        $imageVariables = ['training_center_logo', 'acc_logo', 'qr_code'];

        // First, remove elements (divs and imgs) that contain variables with null values
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $variablePattern = preg_quote('{{' . $key . '}}', '/');
                $variablePatternSpaced = preg_quote('{{ ' . $key . ' }}', '/');
                $varRegex = '(?:' . $variablePattern . '|' . $variablePatternSpaced . ')';

                // Remove divs containing the variable
                $html = preg_replace('/<div[^>]*>[\s\S]*?' . $varRegex . '[\s\S]*?<\/div>/i', '', $html);
                // Remove img tags that contain this variable (e.g. <img src="{{variable}}">)
                $html = preg_replace('/<img[^>]*' . $varRegex . '[^>]*\/?>/i', '', $html);
            }
        }

        // Then, replace remaining variables with actual values
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // For image variables: use file:// path for DomPDF (reliable); fallback to data URI or URL
            if (in_array($key, $imageVariables) && is_string($value)) {
                if (str_starts_with($value, 'data:')) {
                    $safeValue = $value;
                } elseif (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
                    $localPath = $this->urlToLocalPath($value);
                    if ($localPath) {
                        $safeValue = htmlspecialchars($this->filePathToFileUri($localPath), ENT_QUOTES, 'UTF-8');
                    } else {
                        $dataUri = $this->urlToDataUri($value);
                        $safeValue = $dataUri ?: htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                } else {
                    $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                }
            } else {
                $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            }

            $patterns = ['/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i'];
            foreach ($patterns as $pattern) {
                $html = preg_replace($pattern, $safeValue, $html);
            }

            if ($key === 'verification_code') {
                $variations = [
                    'verificationCode',
                    'VerificationCode',
                    'VERIFICATION_CODE',
                    'verification-code',
                    'Verification-Code',
                ];
                
                foreach ($variations as $variation) {
                    $variationPattern = '/\{\{\s*' . preg_quote($variation, '/') . '\s*\}\}/i';
                    $html = preg_replace($variationPattern, $safeValue, $html);
                }
                
                // Log replacement for debugging
                Log::info('Replacing verification_code variable', [
                    'original_value' => $value,
                    'safe_value' => $safeValue,
                    'variations_checked' => $variations,
                ]);
            }
        }

        // Clean up any empty lines or extra whitespace
        $html = preg_replace('/\n\s*\n/', "\n", $html);

        return $html;
    }

    /**
     * Process config_json and replace variables with actual data
     */
    private function processConfigJson(array $config, array $data, float $widthPt, float $heightPt): array
    {
        $textElements = [];

        foreach ($config as $placeholder) {
            if (!isset($placeholder['variable'])) {
                continue;
            }

            $variable = $placeholder['variable'];

            // Handle both dynamic variables ({{variable_name}}) and static text
            if (preg_match('/\{\{([^}]+)\}\}/', $variable, $matches)) {
                // Dynamic variable: extract variable name and replace with data
                $variableKey = trim($matches[1]);
                $text = $data[$variableKey] ?? $variable; // Fallback to original if data not found
            } else {
                // Static text: use as-is
                $text = $variable;
            }

            // Get styling (support both snake_case and camelCase)
            $fontSizePx = (int)($placeholder['font_size'] ?? $placeholder['fontSize'] ?? 24);
            // Convert pixels to points (1 pixel = 0.75 points at 96 DPI: 72 points / 96 pixels)
            $fontSize = $fontSizePx * 0.75;
            $colorHex = $placeholder['color'] ?? '#000000';
            $fontFamily = $placeholder['font_family'] ?? $placeholder['fontFamily'] ?? 'Arial';
            $textAlign = $placeholder['text_align'] ?? $placeholder['textAlign'] ?? 'left';
            
            // Convert percentage coordinates (0.0-1.0) to points
            $xPercent = (float)($placeholder['x'] ?? 0.5);
            $yPercent = (float)($placeholder['y'] ?? 0.5);
            $xPt = $xPercent * $widthPt;
            $yPt = $yPercent * $heightPt;

            $textElements[] = [
                'text' => $text,
                'x_pt' => $xPt,
                'y_pt' => $yPt,
                'font_family' => $fontFamily,
                'font_size' => $fontSize,
                'color' => $colorHex,
                'text_align' => $textAlign,
            ];
        }

        return $textElements;
    }

    /**
     * Save certificate as PNG or PDF (legacy method for PNG/JPG)
     */
    private function saveCertificate($image, CertificateTemplate $template, array $data, string $format, int $width, int $height): ?string
    {
        $directory = 'certificates/' . $template->id;
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        if ($format === 'pdf') {
            // Generate temporary PNG file first
            $tempPngPath = sys_get_temp_dir() . '/' . Str::random(40) . '.png';
            imagepng($image, $tempPngPath, 9); // 9 = highest quality
            
            // Convert PNG to base64 for embedding in PDF
            $imageData = file_get_contents($tempPngPath);
            $base64Image = base64_encode($imageData);
            
            // Calculate dimensions in points (PDF uses points: 1 inch = 72 points)
            // Assuming 96 DPI for screen images: width_px / 96 * 72 = width_pt
            $widthPt = ($width / 96) * 72;
            $heightPt = ($height / 96) * 72;
            
            // Create HTML for PDF with the image
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        margin: 0;
                        padding: 0;
                    }
                    img {
                        width: ' . $widthPt . 'pt;
                        height: ' . $heightPt . 'pt;
                        display: block;
                        page-break-after: always;
                    }
                </style>
            </head>
            <body>
                <img src="data:image/png;base64,' . $base64Image . '" />
            </body>
            </html>';
            
            // Generate PDF using DomPDF
            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt], 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'))
                ->setOption('defaultFont', 'serif'); // Use DomPDF's built-in serif font
            
            $fileName = Str::random(40) . '.pdf';
            $filePath = $directory . '/' . $fileName;
            $fullPath = Storage::disk('public')->path($filePath);
            
            // Save PDF
            $pdf->save($fullPath);
            
            // Clean up temporary PNG
            if (file_exists($tempPngPath)) {
                @unlink($tempPngPath);
            }
            
            return $filePath;
            
        } elseif ($format === 'png') {
            $fileName = Str::random(40) . '.png';
            $filePath = $directory . '/' . $fileName;
            $fullPath = Storage::disk('public')->path($filePath);
            imagepng($image, $fullPath, 9); // 9 = highest quality
            return $filePath;
        } elseif ($format === 'jpg' || $format === 'jpeg') {
            $fileName = Str::random(40) . '.jpg';
            $filePath = $directory . '/' . $fileName;
            $fullPath = Storage::disk('public')->path($filePath);
            imagejpeg($image, $fullPath, 95); // 95 = high quality
            return $filePath;
        } else {
            return null;
        }
    }

    /**
     * Generate API URL for certificate file
     */
    private function getCertificateApiUrl(string $filePath): string
    {
        // Convert storage path to API route URL
        // e.g., certificates/1/abc123.png -> /api/storage/certificates/1/abc123.png
        return url('/api/storage/' . $filePath);
    }

    /**
     * Generate PDF from PNG (requires additional library like DomPDF or TCPDF)
     * This is a placeholder - you may want to use DomPDF or TCPDF for PDF generation
     */
    public function generatePdf(CertificateTemplate $template, array $data): array
    {
        // Generate PNG first
        $result = $this->generate($template, $data, 'png');
        
        if (!$result['success']) {
            return $result;
        }

        // Convert PNG to PDF using DomPDF
        // Note: This is a simplified version - you may want to use a more sophisticated PDF library
        try {
            $pngPath = Storage::disk('public')->path($result['file_path']);
            
            // For now, return the PNG
            // In production, you'd convert this to PDF using DomPDF or similar
            // This requires creating an HTML wrapper with the image
            
            return $result; // Return PNG for now
            
        } catch (\Exception $e) {
            Log::error('PDF generation error', [
                'error' => $e->getMessage(),
            ]);
            
            return $result; // Return PNG as fallback
        }
    }

    /**
     * Generate training center authorization certificate
     * 
     * @param CertificateTemplate $template
     * @param \App\Models\TrainingCenter $trainingCenter
     * @param \App\Models\ACC $acc
     * @param string|null $verificationCode Optional verification code to include in certificate
     * @return array
     */
    public function generateTrainingCenterCertificate(CertificateTemplate $template, $trainingCenter, $acc, ?string $verificationCode = null): array
    {
        $data = [
            'training_center_name' => $trainingCenter->name ?? '',
            'training_center_legal_name' => $trainingCenter->legal_name ?? '',
            'training_center_email' => $trainingCenter->email ?? '',
            'training_center_country' => $trainingCenter->country ?? '',
            'training_center_city' => $trainingCenter->city ?? '',
            'training_center_registration_number' => $trainingCenter->registration_number ?? '',
            'acc_name' => $acc->name ?? '',
            'acc_legal_name' => $acc->legal_name ?? '',
            'acc_registration_number' => $acc->registration_number ?? '',
            'acc_country' => $acc->country ?? '',
            'issue_date' => now()->format('Y-m-d'),
            'issue_date_formatted' => now()->format('F j, Y'),
            // Image placeholders for template variables
            'training_center_logo' => $this->resolveLogoUrl($trainingCenter->logo_url ?? null),
            'acc_logo' => $this->resolveLogoUrl($acc->logo_url ?? null),
        ];

        if ($verificationCode) {
            $data['verification_code'] = $verificationCode;
            $data['qr_code'] = $this->getQrCodeUrl($verificationCode);
        }

        return $this->generate($template, $data, 'pdf');
    }

    /**
     * Generate instructor authorization certificate for a specific course
     * 
     * @param CertificateTemplate $template
     * @param \App\Models\Instructor $instructor
     * @param \App\Models\Course $course
     * @param \App\Models\ACC $acc
     * @param string|null $verificationCode Optional verification code to include in certificate
     * @return array
     */
    public function generateInstructorCertificate(CertificateTemplate $template, $instructor, $course, $acc, ?string $verificationCode = null): array
    {
        // Instructor certificates typically expire 3 years from issue (configurable)
        $expiryDate = now()->addYears(3)->format('Y-m-d');

        $data = [
            'instructor_name' => trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
            'instructor_first_name' => $instructor->first_name ?? '',
            'instructor_last_name' => $instructor->last_name ?? '',
            'instructor_email' => $instructor->email ?? '',
            'instructor_id_number' => $instructor->id_number ?? '',
            'instructor_country' => $instructor->country ?? '',
            'instructor_city' => $instructor->city ?? '',
            'course_name' => $course->name ?? '',
            'course_name_ar' => $course->name_ar ?? '',
            'course_code' => $course->code ?? '',
            'acc_name' => $acc->name ?? '',
            'acc_legal_name' => $acc->legal_name ?? '',
            'acc_registration_number' => $acc->registration_number ?? '',
            'acc_country' => $acc->country ?? '',
            'issue_date' => now()->format('Y-m-d'),
            'issue_date_formatted' => now()->format('F j, Y'),
            'expiry_date' => $expiryDate,
            // Image placeholders
            'training_center_logo' => $this->resolveLogoUrl($instructor->trainingCenter?->logo_url ?? null),
            'acc_logo' => $this->resolveLogoUrl($acc->logo_url ?? null),
        ];

        if ($verificationCode) {
            $data['verification_code'] = $verificationCode;
            $data['qr_code'] = $this->getQrCodeUrl($verificationCode);
        }

        return $this->generate($template, $data, 'pdf');
    }

    /**
     * Find all image URLs in HTML (background-image, img src) and convert to base64 data URIs
     * Fixes DomPDF failing to load remote images
     */
    private function embedImageUrlsInHtml(string $html): string
    {
        // Match url('...') or url("...") in CSS (handles background-image, etc.) — use file:// for DomPDF
        $html = preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
            function ($matches) {
                $url = trim($matches[1]);
                if (empty($url) || str_starts_with($url, 'data:') || str_starts_with($url, 'file://')) {
                    return $matches[0];
                }
                $localPath = $this->urlToLocalPath($url);
                if ($localPath) {
                    $fileUri = $this->filePathToFileUri($localPath);
                    return 'url("' . str_replace('"', '%22', $fileUri) . '")';
                }
                $dataUri = $this->urlToDataUri($url);
                return $dataUri ? 'url(' . $dataUri . ')' : $matches[0];
            },
            $html
        );

        // Match img src="..." or src='...' — use file:// for DomPDF
        $html = preg_replace_callback(
            '/<img([^>]*)\ssrc\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) {
                $url = trim($matches[2]);
                if (empty($url) || str_starts_with($url, 'data:') || str_starts_with($url, 'file://')) {
                    return $matches[0];
                }
                $localPath = $this->urlToLocalPath($url);
                if ($localPath) {
                    $src = htmlspecialchars($this->filePathToFileUri($localPath), ENT_QUOTES, 'UTF-8');
                } else {
                    $dataUri = $this->urlToDataUri($url);
                    $src = $dataUri ?: htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                }
                return '<img' . $matches[1] . ' src="' . $src . '"' . $matches[3] . '>';
            },
            $html
        );

        return $html;
    }

    /**
     * Fix malformed storage URLs (e.g. storage/app/public -> storage)
     */
    private function fixStorageUrl(string $url): string
    {
        // Fix: .../storage/app/public/... -> .../storage/... (Laravel public symlink)
        $url = preg_replace('#/storage/app/public/#i', '/storage/', $url);
        $url = preg_replace('#storage/app/public/#i', 'storage/', $url);
        // Fix: .../laravel/storage/app/public/... -> .../laravel/storage/...
        $url = preg_replace('#/laravel/storage/app/public/#i', '/laravel/storage/', $url);
        $url = preg_replace('#laravel/storage/app/public/#i', 'laravel/storage/', $url);
        return $url;
    }

    /**
     * Resolve image URL to a local file path that DomPDF can load (under chroot).
     * DomPDF shows broken image boxes for failed data URIs; file:// paths under base_path() work reliably.
     */
    private function urlToLocalPath(string $url): ?string
    {
        $url = $this->fixStorageUrl($url);
        $localPath = $this->getImagePath($url);
        if (!$localPath || !file_exists($localPath)) {
            return null;
        }
        $basePath = realpath(base_path());
        $localReal = realpath($localPath);
        if (!$basePath || !$localReal) {
            return null;
        }
        // Path must be under chroot (base_path). If in system temp, copy to storage under base_path.
        if (str_starts_with($localReal, $basePath)) {
            return $localReal;
        }
        $tempDir = storage_path('app/temp/cert-images');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $ext = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'png';
        if (!in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $ext = 'png';
        }
        $dest = $tempDir . '/' . Str::random(16) . '.' . $ext;
        if (!@copy($localPath, $dest)) {
            return null;
        }
        $destReal = realpath($dest);
        return $destReal && str_starts_with($destReal, $basePath) ? $destReal : null;
    }

    /**
     * Format local file path as file:// URI for use in HTML (DomPDF loads these under chroot).
     */
    private function filePathToFileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return 'file://' . $path;
    }

    /**
     * Convert image URL to base64 data URI (fallback when file path not used).
     */
    private function urlToDataUri(string $url): ?string
    {
        try {
            $url = $this->fixStorageUrl($url);
            $localPath = $this->getImagePath($url);
            if ($localPath && file_exists($localPath)) {
                $imageData = file_get_contents($localPath);
                if ($imageData) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($localPath) ?: 'image/png';
                    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                }
            }
            $fullUrl = str_starts_with($url, '/') && !str_starts_with($url, '//') ? url($url) : $url;
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'follow_location' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $imageData = @file_get_contents($fullUrl, false, $context);
            if (!$imageData || strlen($imageData) < 50) {
                Log::warning('Failed to fetch image for certificate', ['url' => $fullUrl]);
                return null;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData) ?: 'image/png';
            if (!str_starts_with($mimeType, 'image/')) {
                $mimeType = 'image/png';
            }
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } catch (\Throwable $e) {
            Log::warning('Error converting image URL to data URI', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Resolve logo URL to full URL (for storage paths)
     */
    private function resolveLogoUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return url($url);
    }

    /**
     * Get QR code image URL for verification (uses public QR API)
     */
    private function getQrCodeUrl(string $verificationCode): string
    {
        $verifyUrl = url('/api/certificates/verify/' . $verificationCode);
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'data' => $verifyUrl,
            'size' => '200x200',
            'format' => 'png',
        ]);
    }
}

