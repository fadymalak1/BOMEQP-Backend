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

        $width = $orientation === 'landscape' ? '297mm' : '210mm';
        $height = $orientation === 'landscape' ? '210mm' : '297mm';

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 ' . $orientation . ';
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: "Times New Roman", serif;
        }
        .certificate {
            width: ' . $width . ';
            height: ' . $height . ';
            border: ' . $borderWidth . ' solid ' . $borderColor . ';
            padding: 40px;
            text-align: center;
            background-color: ' . $backgroundColor . ';
            position: relative;
            ' . ($backgroundImageUrl ? 'background-image: url(\'' . $backgroundImageUrl . '\'); background-size: cover; background-position: center;' : '') . '
        }';

        // Title styles
        if (isset($config['title']) && ($config['title']['show'] ?? true)) {
            $title = $config['title'];
            $html .= '
        .title {
            font-size: ' . ($title['font_size'] ?? '48px') . ';
            font-weight: ' . ($title['font_weight'] ?? 'bold') . ';
            color: ' . ($title['color'] ?? '#2c3e50') . ';
            margin-bottom: 20px;
            text-transform: uppercase;
        }';
        }

        // Trainee name styles
        if (isset($config['trainee_name']) && ($config['trainee_name']['show'] ?? true)) {
            $trainee = $config['trainee_name'];
            $html .= '
        .trainee-name {
            font-size: ' . ($trainee['font_size'] ?? '36px') . ';
            font-weight: ' . ($trainee['font_weight'] ?? 'bold') . ';
            color: ' . ($trainee['color'] ?? '#2c3e50') . ';
            margin: 30px 0;
            text-decoration: underline;
        }';
        }

        // Course name styles
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $course = $config['course_name'];
            $html .= '
        .course-name {
            font-size: ' . ($course['font_size'] ?? '24px') . ';
            color: ' . ($course['color'] ?? '#34495e') . ';
            margin: 20px 0;
        }';
        }

        // Details styles
        $html .= '
        .subtitle {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 40px;
        }
        .details {
            margin-top: 50px;
            font-size: 14px;
            color: #7f8c8d;
        }
        .verification {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 10px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="certificate">';

        // Title
        if (isset($config['title']) && ($config['title']['show'] ?? true)) {
            $titleText = $config['title']['text'] ?? 'Certificate of Completion';
            $html .= '
        <div class="title">' . htmlspecialchars($titleText) . '</div>
        <div class="subtitle">This is to certify that</div>';
        }

        // Trainee name
        if (isset($config['trainee_name']) && ($config['trainee_name']['show'] ?? true)) {
            $html .= '
        <div class="trainee-name">{{trainee_name}}</div>
        <div class="subtitle">has successfully completed the course</div>';
        }

        // Course name
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $html .= '
        <div class="course-name">{{course_name}}</div>';
        }

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
        $certificate->load(['template', 'course', 'trainingCenter', 'instructor', 'classModel']);
        $template = $certificate->template;
        
        // Get HTML content
        $html = $this->prepareHtml($certificate, $template);
        
        // Generate PDF
        $pdf = $this->createPdf($html);
        
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
        // Use template_config to generate HTML if available, otherwise use template_html
        if ($template->template_config && !empty($template->template_config)) {
            $html = $this->generateHtmlFromConfig($template->template_config, $template->background_image_url);
        } else {
            $html = $template->template_html ?? '';
        }
        
        if (empty($html)) {
            throw new \Exception('Template HTML is empty');
        }
        
        // Replace variables with actual data
        $replacements = [
            '{{trainee_name}}' => $certificate->trainee_name,
            '{{trainee_id_number}}' => $certificate->trainee_id_number ?? '',
            '{{course_name}}' => $certificate->course->name ?? '',
            '{{course_code}}' => $certificate->course->code ?? '',
            '{{certificate_number}}' => $certificate->certificate_number,
            '{{verification_code}}' => $certificate->verification_code,
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
            $html = str_replace($variable, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $html);
        }
        
        // Handle background image URL - convert relative URLs to absolute
        if ($template->background_image_url) {
            $backgroundUrl = $template->background_image_url;
            // If it's a relative URL, make it absolute
            if (!filter_var($backgroundUrl, FILTER_VALIDATE_URL)) {
                $backgroundUrl = url($backgroundUrl);
            }
            // Replace background_image_url variable if exists
            $html = str_replace('{{background_image_url}}', $backgroundUrl, $html);
        }
        
        return $html;
    }
    
    /**
     * Create PDF from HTML
     */
    private function createPdf(string $html): string
    {
        // Check if dompdf is available
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new \Exception('PDF library not installed. Please run: composer require barryvdh/laravel-dompdf');
        }
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'landscape'); // Default to landscape, can be customized
        
        return $pdf->output();
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

