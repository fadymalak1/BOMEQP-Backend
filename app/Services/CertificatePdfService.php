<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificatePdfService
{
    /**
     * Generate HTML from template configuration (same logic as in CertificateTemplateController)
     */
    private function generateHtmlFromConfig($config, $backgroundImageUrl = null)
    {
        $layout = $config['layout'] ?? [];
        $orientation = $layout['orientation'] ?? 'landscape';
        $borderColor = $layout['border_color'] ?? '#D4AF37';
        $borderWidth = $layout['border_width'] ?? '15px';
        $backgroundColor = $layout['background_color'] ?? '#ffffff';

        // A4 Landscape: 297mm × 210mm (843pt × 596pt)
        // A4 Portrait: 210mm × 297mm (596pt × 843pt)
        $width = $orientation === 'landscape' ? '297mm' : '210mm';
        $height = $orientation === 'landscape' ? '210mm' : '297mm';
        
        // Also define in points for precise PDF rendering
        $widthPt = $orientation === 'landscape' ? '843pt' : '596pt';
        $heightPt = $orientation === 'landscape' ? '596pt' : '843pt';

        // Convert background image URL to absolute if needed
        $bgImageStyle = '';
        if ($backgroundImageUrl) {
            // If it's a relative URL, make it absolute
            if (!filter_var($backgroundImageUrl, FILTER_VALIDATE_URL)) {
                $backgroundImageUrl = url($backgroundImageUrl);
            }
            $bgImageStyle = 'background-image: url(\'' . htmlspecialchars($backgroundImageUrl, ENT_QUOTES) . '\'); background-size: cover; background-position: center; background-repeat: no-repeat;';
        }
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 ' . $orientation . ';
            margin: 0;
            padding: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }
        html {
            width: ' . $width . ';
            height: ' . $height . ';
            margin: 0;
            padding: 0;
            overflow: hidden;
            page-break-inside: avoid !important;
        }
        body {
            width: ' . $width . ';
            height: ' . $height . ';
            margin: 0;
            padding: 0;
            font-family: "Times New Roman", serif;
            overflow: hidden;
            position: relative;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            orphans: 0;
            widows: 0;
        }
        .certificate {
            width: ' . $width . ';
            height: ' . $height . ';
            min-width: ' . $width . ';
            min-height: ' . $height . ';
            max-width: ' . $width . ';
            max-height: ' . $height . ';
            border-top: ' . $borderWidth . ' solid ' . $borderColor . ';
            border-right: ' . $borderWidth . ' solid ' . $borderColor . ';
            border-bottom: ' . $borderWidth . ' solid ' . $borderColor . ';
            border-left: ' . $borderWidth . ' solid ' . $borderColor . ';
            padding: 25px;
            text-align: center;
            background-color: ' . $backgroundColor . ';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            page-break-after: avoid !important;
            page-break-before: avoid !important;
            overflow: hidden;
            box-sizing: border-box;
            orphans: 0;
            widows: 0;
            ' . $bgImageStyle . '
        }';

        // Title styles
        if (isset($config['title']) && ($config['title']['show'] ?? true)) {
            $title = $config['title'];
            $textAlign = $this->getTextAlign($title['text_align'] ?? 'center');
            // Convert px to pt for better PDF rendering (1px ≈ 0.75pt, but we'll use larger for visibility)
            $fontSize = $title['font_size'] ?? '56pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '56pt'; // Default larger size
            }
            $html .= '
        .title {
            font-size: ' . $fontSize . ';
            font-weight: ' . ($title['font_weight'] ?? 'bold') . ';
            color: ' . ($title['color'] ?? '#2c3e50') . ';
            margin-bottom: 15px;
            margin-top: 0;
            text-transform: uppercase;
            text-align: ' . $textAlign . ';
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
            line-height: 1.2;
        }';
        }

        // Trainee name styles
        if (isset($config['trainee_name']) && ($config['trainee_name']['show'] ?? true)) {
            $trainee = $config['trainee_name'];
            $textAlign = $this->getTextAlign($trainee['text_align'] ?? 'center');
            // Convert px to pt for better PDF rendering
            $fontSize = $trainee['font_size'] ?? '42pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '42pt'; // Default larger size
            }
            $html .= '
        .trainee-name {
            font-size: ' . $fontSize . ';
            font-weight: ' . ($trainee['font_weight'] ?? 'bold') . ';
            color: ' . ($trainee['color'] ?? '#2c3e50') . ';
            margin: 20px 0;
            text-decoration: underline;
            text-align: ' . $textAlign . ';
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
            line-height: 1.2;
        }';
        }

        // Course name styles
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $course = $config['course_name'];
            $textAlign = $this->getTextAlign($course['text_align'] ?? 'center');
            // Convert px to pt for better PDF rendering
            $fontSize = $course['font_size'] ?? '28pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '28pt'; // Default larger size
            }
            $html .= '
        .course-name {
            font-size: ' . $fontSize . ';
            color: ' . ($course['color'] ?? '#34495e') . ';
            margin: 15px 0;
            text-align: ' . $textAlign . ';
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
            line-height: 1.3;
        }';
        }

        // Subtitle styles
        if (isset($config['subtitle']) && ($config['subtitle']['show'] ?? true)) {
            $subtitle = $config['subtitle'];
            $textAlign = $this->getTextAlign($subtitle['text_align'] ?? 'center');
            // Convert px to pt for better PDF rendering
            $fontSize = $subtitle['font_size'] ?? '22pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '22pt'; // Default larger size
            }
            $html .= '
        .subtitle {
            font-size: ' . $fontSize . ';
            color: ' . ($subtitle['color'] ?? '#7f8c8d') . ';
            margin: 10px 0;
            text-align: ' . $textAlign . ';
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
            line-height: 1.3;
        }';
        } else {
            $html .= '
        .subtitle {
            font-size: 22pt;
            color: #7f8c8d;
            margin: 10px 0;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
            line-height: 1.3;
        }';
        }
        
        // Details styles
        $html .= '
        .details {
            margin-top: auto;
            padding-top: 20px;
            font-size: 16pt;
            color: #7f8c8d;
            width: 100%;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }
        .details p {
            margin: 5px 0;
            font-size: 16pt;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }
        .verification {
            position: absolute;
            bottom: 15px;
            right: 15px;
            font-size: 12pt;
            color: #95a5a6;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        .certificate-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            flex: 1;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
            min-height: 0;
            max-height: 100%;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="certificate-content">';

        // Title
        if (isset($config['title']) && ($config['title']['show'] ?? true)) {
            $titleText = $config['title']['text'] ?? 'Certificate of Completion';
            $html .= '
            <div class="title">' . htmlspecialchars($titleText) . '</div>';
            
            // Subtitle before trainee name
            if (isset($config['subtitle_before']) && ($config['subtitle_before']['show'] ?? true)) {
                $subtitleText = $config['subtitle_before']['text'] ?? 'This is to certify that';
                $html .= '
            <div class="subtitle">' . htmlspecialchars($subtitleText) . '</div>';
            } else {
                $html .= '
            <div class="subtitle">This is to certify that</div>';
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
            <div class="subtitle">' . htmlspecialchars($subtitleText) . '</div>';
            } else {
                $html .= '
            <div class="subtitle">has successfully completed the course</div>';
            }
        }

        // Course name
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $html .= '
            <div class="course-name">{{course_name}}</div>';
        }

        $html .= '
        </div>';

        // Details
        $html .= '
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

        // Verification code
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
     * Generate PDF for certificate
     */
    public function generatePdf(Certificate $certificate): string
    {
        // Load certificate with all required relationships
        $certificate->load([
            'template', 
            'course.acc', 
            'trainingCenter', 
            'instructor', 
            'classModel'
        ]);
        
        // Get template from certificate - this is the same template from certificate_templates table
        $template = $certificate->template;
        
        if (!$template) {
            throw new \Exception('Certificate template not found. Template ID: ' . $certificate->template_id);
        }
        
        // Ensure template is fresh from database to get latest template_config and template_html
        $template->refresh();
        
        // Verify template has required data
        if (empty($template->template_config) && empty($template->template_html)) {
            throw new \Exception('Template has no content. Template ID: ' . $template->id . '. Please ensure template has either template_config or template_html.');
        }
        
        // Get orientation from template config
        $orientation = 'landscape';
        if ($template->template_config && is_array($template->template_config) && isset($template->template_config['layout']['orientation'])) {
            $orientation = $template->template_config['layout']['orientation'];
        }
        
        // Get HTML content using the template from certificate_templates table
        $html = $this->prepareHtml($certificate, $template);
        
        // Generate PDF with correct orientation
        $pdf = $this->createPdf($html, $orientation);
        
        // Save PDF file
        $fileName = Str::random(20) . '.pdf';
        $path = 'certificates/' . $fileName;
        
        Storage::disk('public')->put($path, $pdf);
        
        // Return URL
        return Storage::disk('public')->url($path);
    }
    
    /**
     * Prepare HTML content with certificate data
     */
    private function prepareHtml(Certificate $certificate, CertificateTemplate $template): string
    {
        // Use template_config to generate HTML if available (preferred method)
        // This uses the same template_config that is returned by /acc/certificate-templates/{id} API
        if ($template->template_config && is_array($template->template_config) && !empty($template->template_config)) {
            // Generate HTML from template_config - this is the same config from certificate_templates table
            $html = $this->generateHtmlFromConfig($template->template_config, $template->background_image_url);
        } else {
            // Fallback to template_html if template_config is not available
            $html = $template->template_html ?? '';
        }
        
        if (empty($html)) {
            throw new \Exception('Template HTML is empty. Template ID: ' . $template->id . '. Please ensure template has either template_html or template_config in certificate_templates table.');
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
        
        // Background image URL is already handled in generateHtmlFromConfig
        // No need to replace it again here as it's already included in the HTML
        
        return $html;
    }
    
    /**
     * Get CSS text-align value from config
     * Supports all CSS text-align values: left, right, center, justify, start, end, initial, inherit
     */
    private function getTextAlign($align): string
    {
        if (empty($align)) {
            return 'center';
        }
        
        $align = strtolower(trim($align));
        
        // Standard CSS text-align values
        $validAlignments = [
            'left',
            'right',
            'center',
            'justify',
            'start',
            'end',
            'initial',
            'inherit'
        ];
        
        // Check if it's a valid CSS alignment value
        if (in_array($align, $validAlignments)) {
            return $align;
        }
        
        // Legacy support for right-center and left-center (for backward compatibility)
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
     * Create PDF from HTML
     */
    private function createPdf(string $html, $orientation = 'landscape'): string
    {
        // Use dompdf directly (works with Laravel 12)
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \Exception('PDF library not installed. Please run: composer require dompdf/dompdf');
        }
        
        try {
            // Create new DomPDF instance
            $dompdf = new \Dompdf\Dompdf();
            
            // Set options to prevent page breaks and enable remote images
            $options = $dompdf->getOptions();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('enableCssFloat', true);
            $options->set('enableFontSubsetting', true);
            $options->set('dpi', 150);
            $dompdf->setOptions($options);
            
            // Load HTML
            $dompdf->loadHtml($html);
            
            // Set paper size and orientation - A4 Landscape: 297mm × 210mm (843pt × 596pt)
            // A4 Portrait: 210mm × 297mm (596pt × 843pt)
            // Using exact dimensions in points for precision
            // 1mm = 2.83465pt, so 297mm = 842.04pt, 210mm = 595.28pt
            // But user specified 843pt × 596pt for landscape
            if ($orientation === 'landscape') {
                // A4 Landscape: 843pt × 596pt (297mm × 210mm)
                $dompdf->setPaper([0, 0, 843, 596], 'landscape');
            } else {
                // A4 Portrait: 596pt × 843pt (210mm × 297mm)
                $dompdf->setPaper([0, 0, 596, 843], 'portrait');
            }
            
            // Render PDF
            $dompdf->render();
            
            // Force single page by checking page count
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
            
            // Return PDF output
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

