<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Dompdf\Options;

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
     */
    private function getFontPath(string $fontFamily): ?string
    {
        // Map frontend font families to system font file names
        // Windows font file names
        $windowsFonts = [
            'Arial' => 'C:\\Windows\\Fonts\\arial.ttf',
            'Helvetica' => 'C:\\Windows\\Fonts\\arial.ttf', // Helvetica uses Arial on Windows
            'Times New Roman' => 'C:\\Windows\\Fonts\\times.ttf',
            'Courier New' => 'C:\\Windows\\Fonts\\cour.ttf',
            'Courier' => 'C:\\Windows\\Fonts\\cour.ttf',
            'Verdana' => 'C:\\Windows\\Fonts\\verdana.ttf',
            'Georgia' => 'C:\\Windows\\Fonts\\georgia.ttf',
            'Tahoma' => 'C:\\Windows\\Fonts\\tahoma.ttf',
            'Trebuchet MS' => 'C:\\Windows\\Fonts\\trebuc.ttf',
            'Impact' => 'C:\\Windows\\Fonts\\impact.ttf',
        ];

        // Check Windows fonts first (most common in production)
        if (isset($windowsFonts[$fontFamily])) {
            $fontPath = $windowsFonts[$fontFamily];
            if (file_exists($fontPath)) {
                return $fontPath;
            }
        }

        // Try Linux fonts
        $linuxFonts = [
            'Arial' => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'Helvetica' => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'Times New Roman' => '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
            'Courier New' => '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
            'Courier' => '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
            'Verdana' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'Georgia' => '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
            'Tahoma' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'Trebuchet MS' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        if (isset($linuxFonts[$fontFamily])) {
            $fontPaths = [
                $linuxFonts[$fontFamily],
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', // Fallback
            ];
            
            foreach ($fontPaths as $fontPath) {
                if (file_exists($fontPath)) {
                    return $fontPath;
                }
            }
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

        // Final fallback - try common system fonts
        $fallbackFonts = [
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
        ];

        foreach ($fallbackFonts as $fontPath) {
            if (file_exists($fontPath)) {
                return $fontPath;
            }
        }

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

        $fileName = Str::random(40) . '.' . $format;
        $filePath = $directory . '/' . $fileName;
        $fullPath = Storage::disk('public')->path($filePath);

        if ($format === 'pdf') {
            // Generate temporary PNG file
            $tempPngPath = sys_get_temp_dir() . '/' . Str::random(40) . '.png';
            imagepng($image, $tempPngPath, 9); // 9 = highest quality
            
            // Convert PNG to base64 for embedding in PDF
            $imageData = file_get_contents($tempPngPath);
            $base64Image = base64_encode($imageData);
            
            // Calculate dimensions in points (PDF uses points: 1 inch = 72 points)
            // Assuming 96 DPI for screen images: width_px / 96 * 72 = width_pt
            $widthPt = ($width / 96) * 72;
            $heightPt = ($height / 96) * 72;
            
            // Create HTML for PDF
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                    }
                    img {
                        width: ' . $widthPt . 'pt;
                        height: ' . $heightPt . 'pt;
                        display: block;
                    }
                </style>
            </head>
            <body>
                <img src="data:image/png;base64,' . $base64Image . '" />
            </body>
            </html>';
            
            // Configure DomPDF options
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            // Create DomPDF instance
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper([0, 0, $widthPt, $heightPt], 'portrait');
            $dompdf->render();
            
            // Save PDF
            file_put_contents($fullPath, $dompdf->output());
            
            // Clean up temporary PNG
            if (file_exists($tempPngPath)) {
                @unlink($tempPngPath);
            }
            
        } elseif ($format === 'png') {
            imagepng($image, $fullPath, 9); // 9 = highest quality
        } elseif ($format === 'jpg' || $format === 'jpeg') {
            imagejpeg($image, $fullPath, 95); // 95 = high quality
        } else {
            return null;
        }

        return $filePath;
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

