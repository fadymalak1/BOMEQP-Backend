<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificatePdfService
{
    /**
     * Generate HTML from template configuration
     * Smart implementation that ensures all content fits within PDF boundaries
     */
    private function generateHtmlFromConfig($config, $backgroundImageUrl = null)
    {
        $layout = $config['layout'] ?? [];
        $orientation = $layout['orientation'] ?? 'landscape';
        $borderColor = $layout['border_color'] ?? '#D4AF37';
        $borderWidth = $layout['border_width'] ?? '10px';
        $backgroundColor = $layout['background_color'] ?? '#ffffff';

        // A4 dimensions: Landscape 297mm × 210mm, Portrait 210mm × 297mm
        $width = $orientation === 'landscape' ? '297mm' : '210mm';
        $height = $orientation === 'landscape' ? '210mm' : '297mm';

        // Process background image URL - ensure it's absolute and accessible
        $bgImageStyle = $this->processBackgroundImage($backgroundImageUrl);

        // Build comprehensive CSS with smart sizing
        $html = $this->buildHtmlStructure($config, $width, $height, $orientation, $borderColor, $borderWidth, $backgroundColor, $bgImageStyle);

        return $html;
    }

    /**
     * Process background image URL to ensure it's accessible
     */
    private function processBackgroundImage($backgroundImageUrl)
    {
        if (!$backgroundImageUrl) {
            return '';
        }

        // Convert relative URL to absolute
        if (!filter_var($backgroundImageUrl, FILTER_VALIDATE_URL)) {
            $backgroundImageUrl = url($backgroundImageUrl);
        }

        // Ensure image URL is properly encoded
        $encodedUrl = htmlspecialchars($backgroundImageUrl, ENT_QUOTES, 'UTF-8');

        return sprintf(
            'background-image: url(\'%s\'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;',
            $encodedUrl
        );
    }

    /**
     * Build complete HTML structure with smart CSS
     */
    private function buildHtmlStructure($config, $width, $height, $orientation, $borderColor, $borderWidth, $backgroundColor, $bgImageStyle)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Page setup - zero margins to prevent cutting */
        @page {
            size: A4 ' . $orientation . ';
            margin: 0;
            padding: 0;
        }

        /* Global reset - ensure no overflow */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }

        /* HTML and Body - exact dimensions */
        html, body {
            margin: 0;
            padding: 0;
            width: ' . $width . ';
            height: ' . $height . ';
            overflow: hidden;
            box-sizing: border-box;
            position: relative;
        }

        html {
            page-break-inside: avoid !important;
        }

        body {
            font-family: "Times New Roman", "DejaVu Serif", serif;
            position: relative;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            orphans: 0;
            widows: 0;
        }

        /* Certificate container - fills entire page */
        .certificate {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            border-top: ' . $borderWidth . ' solid ' . $borderColor . ';
            border-right: ' . $borderWidth . ' solid ' . $borderColor . ';
            border-bottom: ' . $borderWidth . ' solid ' . $borderColor . ';
            border-left: ' . $borderWidth . ' solid ' . $borderColor . ';
            background-color: ' . $backgroundColor . ';
            position: absolute;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            overflow: hidden;
            orphans: 0;
            widows: 0;
            ' . $bgImageStyle . '
        }

        /* Certificate content wrapper - smart padding */
        .certificate-content {
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px 40px;
            min-height: 0;
            max-height: 100%;
            overflow: visible;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }';

        // Add element-specific styles
        $html .= $this->buildElementStyles($config);

        $html .= '
        /* Details section - bottom aligned */
        .details {
            width: 100%;
            padding: 15px 40px;
            font-size: 11pt;
            color: #7f8c8d;
            text-align: center;
            flex-shrink: 0;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }

        .details p {
            margin: 2px 0;
            font-size: 11pt;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        /* Verification code - absolute positioned */
        .verification {
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 9pt;
            color: #95a5a6;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        /* Image handling - ensure images fit */
        img {
            max-width: 100%;
            height: auto;
            display: block;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        /* Logo and signature containers */
        .logo-container, .signature-container {
            max-width: 100%;
            max-height: 150px;
            overflow: hidden;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .logo-container img, .signature-container img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate-content">';

        // Build content HTML
        $html .= $this->buildContentHtml($config);

        $html .= '
        </div>';

        // Add details section
        $html .= $this->buildDetailsHtml($config);

        // Add verification code
        if (isset($config['verification_code']) && ($config['verification_code']['show'] ?? true)) {
            $html .= '
        <div class="verification">
            Verification Code: {{verification_code}}
        </div>';
        }

        $html .= '
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Build CSS styles for certificate elements
     */
    private function buildElementStyles($config)
    {
        $styles = '';

        // Title styles
        if (isset($config['title']) && ($config['title']['show'] ?? true)) {
            $title = $config['title'];
            $textAlign = $this->getTextAlign($title['text_align'] ?? 'center');
            $fontSize = $this->convertFontSize($title['font_size'] ?? '32pt');

            $styles .= '
        .title {
            font-size: ' . $fontSize . ';
            font-weight: ' . ($title['font_weight'] ?? 'bold') . ';
            color: ' . ($title['color'] ?? '#2c3e50') . ';
            margin: 8px 0;
            text-transform: uppercase;
            text-align: ' . $textAlign . ';
            line-height: 1.1;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }';
        }

        // Trainee name styles
        if (isset($config['trainee_name']) && ($config['trainee_name']['show'] ?? true)) {
            $trainee = $config['trainee_name'];
            $textAlign = $this->getTextAlign($trainee['text_align'] ?? 'center');
            $fontSize = $this->convertFontSize($trainee['font_size'] ?? '26pt');

            $styles .= '
        .trainee-name {
            font-size: ' . $fontSize . ';
            font-weight: ' . ($trainee['font_weight'] ?? 'bold') . ';
            color: ' . ($trainee['color'] ?? '#2c3e50') . ';
            margin: 8px 0;
            text-decoration: underline;
            text-align: ' . $textAlign . ';
            line-height: 1.1;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }';
        }

        // Course name styles
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $course = $config['course_name'];
            $textAlign = $this->getTextAlign($course['text_align'] ?? 'center');
            $fontSize = $this->convertFontSize($course['font_size'] ?? '18pt');

            $styles .= '
        .course-name {
            font-size: ' . $fontSize . ';
            color: ' . ($course['color'] ?? '#34495e') . ';
            margin: 6px 0;
            text-align: ' . $textAlign . ';
            line-height: 1.2;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }';
        }

        // Subtitle styles
        $subtitleFontSize = $this->convertFontSize($config['subtitle']['font_size'] ?? '14pt');
        $styles .= '
        .subtitle {
            font-size: ' . $subtitleFontSize . ';
            color: ' . ($config['subtitle']['color'] ?? '#7f8c8d') . ';
            margin: 4px 0;
            text-align: center;
            line-height: 1.2;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }';

        return $styles;
    }

    /**
     * Build content HTML from config
     */
    private function buildContentHtml($config)
    {
        $html = '';

        // Title
        if (isset($config['title']) && ($config['title']['show'] ?? true)) {
            $titleText = $config['title']['text'] ?? 'Certificate of Completion';
            $html .= '
            <div class="title">' . htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') . '</div>';

            // Subtitle before trainee name
            if (isset($config['subtitle_before']) && ($config['subtitle_before']['show'] ?? true)) {
                $subtitleText = $config['subtitle_before']['text'] ?? 'This is to certify that';
                $html .= '
            <div class="subtitle">' . htmlspecialchars($subtitleText, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        // Trainee name
        if (isset($config['trainee_name']) && ($config['trainee_name']['show'] ?? true)) {
            $html .= '
            <div class="trainee-name">{{trainee_name}}</div>';

            // Subtitle after trainee name
            if (isset($config['subtitle_after']) && ($config['subtitle_after']['show'] ?? true)) {
                $subtitleText = $config['subtitle_after']['text'] ?? 'has successfully completed the course';
                $html .= '
            <div class="subtitle">' . htmlspecialchars($subtitleText, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        // Course name
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $html .= '
            <div class="course-name">{{course_name}}</div>';
        }

        return $html;
    }

    /**
     * Build details HTML section
     */
    private function buildDetailsHtml($config)
    {
        $html = '
        <div class="details">';

        if (isset($config['issue_date']) && ($config['issue_date']['show'] ?? true)) {
            $html .= '
            <p>Issued on: {{issue_date}}</p>';
        }

        if (isset($config['certificate_number']) && ($config['certificate_number']['show'] ?? true)) {
            $html .= '
            <p>Certificate Number: {{certificate_number}}</p>';
        }

        $html .= '
        </div>';

        return $html;
    }

    /**
     * Convert font size from px to pt for better PDF rendering
     */
    private function convertFontSize($fontSize)
    {
        if (strpos($fontSize, 'px') !== false) {
            $pxValue = (float) str_replace('px', '', $fontSize);
            return ($pxValue * 0.75) . 'pt';
        }

        if (strpos($fontSize, 'pt') === false) {
            return '14pt'; // Default
        }

        return $fontSize;
    }

    /**
     * Get CSS text-align value from config
     * Supports all CSS text-align values
     */
    private function getTextAlign($align): string
    {
        if (empty($align)) {
            return 'center';
        }

        $align = strtolower(trim($align));

        $validAlignments = [
            'left', 'right', 'center', 'justify',
            'start', 'end', 'initial', 'inherit'
        ];

        if (in_array($align, $validAlignments)) {
            return $align;
        }

        // Legacy support
        switch ($align) {
            case 'right-center':
            case 'right_center':
                return 'right';
            case 'left-center':
            case 'left_center':
                return 'left';
            default:
                return 'center';
        }
    }

    /**
     * Generate PDF for certificate
     */
    public function generatePdf(Certificate $certificate): string
    {
        try {
            // Load certificate with all required relationships
            $certificate->load([
                'template',
                'course.acc',
                'trainingCenter',
                'instructor',
                'classModel'
            ]);

            // Get template from certificate
            $template = $certificate->template;

            if (!$template) {
                throw new \Exception('Certificate template not found. Template ID: ' . $certificate->template_id);
            }

            // Refresh template to get latest data
            $template->refresh();

            // Verify template has required data
            if (empty($template->template_config) && empty($template->template_html)) {
                throw new \Exception('Template has no content. Template ID: ' . $template->id);
            }

            // Get orientation from template config
            $orientation = 'landscape';
            if ($template->template_config && is_array($template->template_config)) {
                $orientation = $template->template_config['layout']['orientation'] ?? 'landscape';
            }

            // Prepare HTML content
            $html = $this->prepareHtml($certificate, $template);

            // Generate PDF with correct orientation
            $pdf = $this->createPdf($html, $orientation);

            // Save PDF file
            $fileName = 'certificate-' . Str::random(20) . '.pdf';
            $path = 'certificates/' . $fileName;

            Storage::disk('public')->put($path, $pdf);

            // Return URL
            return Storage::disk('public')->url($path);

        } catch (\Exception $e) {
            \Log::error('Certificate PDF Generation Failed', [
                'certificate_id' => $certificate->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare HTML content with certificate data
     */
    private function prepareHtml(Certificate $certificate, CertificateTemplate $template): string
    {
        // Use template_config to generate HTML if available (preferred method)
        if ($template->template_config && is_array($template->template_config) && !empty($template->template_config)) {
            $html = $this->generateHtmlFromConfig($template->template_config, $template->background_image_url);
        } else {
            // Fallback to template_html
            $html = $template->template_html ?? '';
        }

        if (empty($html)) {
            throw new \Exception('Template HTML is empty. Template ID: ' . $template->id);
        }

        // Replace variables with actual data
        $replacements = [
            '{{trainee_name}}' => $certificate->trainee_name ?? '',
            '{{trainee_id_number}}' => $certificate->trainee_id_number ?? '',
            '{{course_name}}' => $certificate->course->name ?? '',
            '{{course_code}}' => $certificate->course->code ?? '',
            '{{certificate_number}}' => $certificate->certificate_number ?? '',
            '{{verification_code}}' => $certificate->verification_code ?? '',
            '{{issue_date}}' => $certificate->issue_date ? $certificate->issue_date->format('Y-m-d') : '',
            '{{expiry_date}}' => $certificate->expiry_date ? $certificate->expiry_date->format('Y-m-d') : '',
            '{{training_center_name}}' => $certificate->trainingCenter->name ?? '',
            '{{instructor_name}}' => $certificate->instructor->name ?? '',
            '{{class_name}}' => $certificate->classModel->name ?? '',
            '{{acc_name}}' => $certificate->course->acc->name ?? '',
        ];

        // Format dates
        if ($certificate->issue_date) {
            $replacements['{{issue_date_formatted}}'] = $certificate->issue_date->format('F d, Y');
        }
        if ($certificate->expiry_date) {
            $replacements['{{expiry_date_formatted}}'] = $certificate->expiry_date->format('F d, Y');
        }

        // Replace all variables
        foreach ($replacements as $variable => $value) {
            $html = str_replace($variable, htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $html);
        }

        return $html;
    }

    /**
     * Create PDF from HTML with smart dimension handling
     */
    private function createPdf(string $html, $orientation = 'landscape'): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \Exception('PDF library not installed. Please run: composer require dompdf/dompdf');
        }

        try {
            $dompdf = new \Dompdf\Dompdf();

            // Configure options for best PDF quality
            $options = $dompdf->getOptions();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('enableCssFloat', true);
            $options->set('enableFontSubsetting', true);
            $options->set('dpi', 150); // High DPI for better image quality
            $dompdf->setOptions($options);

            // Load HTML
            $dompdf->loadHtml($html);

            // Set paper size with exact dimensions in points
            // A4 Landscape: 297mm × 210mm = 841.89pt × 595.28pt
            // A4 Portrait: 210mm × 297mm = 595.28pt × 841.89pt
            // 1mm = 2.83465pt
            if ($orientation === 'landscape') {
                $dompdf->setPaper([0, 0, 841.89, 595.28], 'landscape');
            } else {
                $dompdf->setPaper([0, 0, 595.28, 841.89], 'portrait');
            }

            // Render PDF
            $dompdf->render();

            // Verify single page
            $canvas = $dompdf->getCanvas();
            if ($canvas && method_exists($canvas, 'get_page_count')) {
                $pageCount = $canvas->get_page_count();
                if ($pageCount > 1) {
                    \Log::warning('PDF generated with multiple pages', [
                        'pages' => $pageCount,
                        'orientation' => $orientation
                    ]);
                }
            }

            return $dompdf->output();

        } catch (\Exception $e) {
            \Log::error('PDF Generation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'orientation' => $orientation
            ]);
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate PDF and update certificate
     */
    public function generateAndUpdate(Certificate $certificate): Certificate
    {
        $pdfUrl = $this->generatePdf($certificate);

        $certificate->update([
            'certificate_pdf_url' => $pdfUrl
        ]);

        return $certificate->fresh();
    }
}
