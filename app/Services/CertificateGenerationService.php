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
     * 
     * @param CertificateTemplate $template
     * @param array $data Key-value pairs for template variables (e.g., ['student_name' => 'John Doe', 'course_name' => 'Fire Safety'])
     * @param string $outputFormat 'png' or 'pdf'
     * @return array Returns ['success' => true, 'file_path' => string, 'file_url' => string] or error array
     */
    public function generate(CertificateTemplate $template, array $data, string $outputFormat = 'png'): array
    {
        try {
            // Validate template has required data
            if (!$template->background_image_url || !$template->config_json) {
                return [
                    'success' => false,
                    'message' => 'Template missing background image or configuration',
                ];
            }

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

            // Apply text placeholders
            $this->applyTextPlaceholders($image, $template->config_json, $data, $width, $height);

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
        if (strpos($imageUrl, Storage::disk('public')->url('')) === 0) {
            $relativePath = str_replace(Storage::disk('public')->url(''), '', $imageUrl);
            $fullPath = Storage::disk('public')->path($relativePath);
            
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // If it's a full URL, download it
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $tempPath = sys_get_temp_dir() . '/' . Str::random(20) . '.tmp';
            $imageData = @file_get_contents($imageUrl);
            
            if ($imageData && file_put_contents($tempPath, $imageData)) {
                return $tempPath;
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
     * Apply text placeholders to image
     */
    private function applyTextPlaceholders($image, array $config, array $data, int $imageWidth, int $imageHeight): void
    {
        foreach ($config as $placeholder) {
            if (!isset($placeholder['variable'])) {
                continue;
            }

            $variable = $placeholder['variable'];
            
            // Handle both dynamic variables ({{variable_name}}) and static text
            // Frontend sends the actual text content in the 'variable' field
            if (preg_match('/\{\{([^}]+)\}\}/', $variable, $matches)) {
                // Dynamic variable: extract variable name and replace with data
                $variableKey = trim($matches[1]);
                $text = $data[$variableKey] ?? $variable; // Fallback to original if data not found
            } else {
                // Static text: use as-is
                $text = $variable;
            }

            // Calculate absolute coordinates from percentages
            $x = (float)($placeholder['x'] ?? 0.5) * $imageWidth;
            $y = (float)($placeholder['y'] ?? 0.5) * $imageHeight;

            // Get styling (support both snake_case and camelCase for flexibility)
            $fontSize = (int)($placeholder['font_size'] ?? $placeholder['fontSize'] ?? 24);
            $colorHex = $placeholder['color'] ?? '#000000';
            $fontFamily = $placeholder['font_family'] ?? $placeholder['fontFamily'] ?? 'Arial';
            $textAlign = $placeholder['text_align'] ?? $placeholder['textAlign'] ?? 'left';

            // Convert hex color to RGB
            $color = $this->hexToRgb($colorHex);
            $textColor = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);

            // Use TrueType fonts if available, otherwise use built-in font
            $fontPath = $this->getFontPath($fontFamily);
            
            if ($fontPath && function_exists('imagettftext') && function_exists('imagettfbbox')) {
                // Adjust X position based on text alignment
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
                // Fallback to built-in font (limited sizing)
                // Built-in fonts don't support alignment, so just use left alignment
                imagestring($image, 5, (int)$x, (int)$y - 20, $text, $textColor);
            }
        }
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
     * Save certificate as PNG or PDF
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
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper([0, 0, $widthPt, $heightPt], 'portrait');
            
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
}

