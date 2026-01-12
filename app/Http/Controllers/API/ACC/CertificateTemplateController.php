<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\CertificateTemplate;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class CertificateTemplateController extends Controller
{
    #[OA\Get(
        path: "/acc/certificate-templates",
        summary: "List certificate templates",
        description: "Get all certificate templates for the authenticated ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Templates retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "templates", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $templates = CertificateTemplate::where('acc_id', $acc->id)
            ->with('category')
            ->get();

        return response()->json(['templates' => $templates]);
    }

    #[OA\Post(
        path: "/acc/certificate-templates/generate-from-image",
        summary: "Generate certificate template from image using AI",
        description: "Upload a certificate image and use AI (Gemini) to automatically analyze it and generate a complete template with HTML and template_config.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["category_id", "name", "certificate_image", "status", "orientation"],
                    properties: [
                        new OA\Property(property: "category_id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template"),
                        new OA\Property(property: "certificate_image", type: "string", format: "binary", description: "Certificate image file (JPEG, PNG, JPG - max 10MB)"),
                        new OA\Property(property: "orientation", type: "string", enum: ["landscape", "portrait"], example: "landscape", description: "Certificate orientation"),
                        new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Template generated successfully from image"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "AI analysis failed")
        ]
    )]
    public function generateFromImage(Request $request, GeminiService $geminiService)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'certificate_image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 10MB max
            'orientation' => 'required|in:landscape,portrait',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        try {
            // Upload certificate image
            $imageFile = $request->file('certificate_image');
            $imagePath = $imageFile->store('certificate-templates/source-images', 'public');
            
            // Analyze image using Gemini AI
            $analysisResult = $geminiService->analyzeCertificateImage($imagePath, $request->orientation);
            
            // Get template_config and template_html from AI analysis
            $templateConfig = $analysisResult['template_config'];
            $templateHtml = $analysisResult['template_html'];
            
            // Ensure orientation matches request
            if (!isset($templateConfig['layout'])) {
                $templateConfig['layout'] = [];
            }
            $templateConfig['layout']['orientation'] = $request->orientation;
            
            // Use the uploaded image as background
            $backgroundImageUrl = Storage::disk('public')->url($imagePath);
            
            // Regenerate HTML with correct background image URL
            $templateHtml = $this->generateHtmlFromConfig($templateConfig, $backgroundImageUrl);
            
            // Extract variables from template_config
            $templateVariables = $this->extractVariablesFromConfig($templateConfig);
            
            // Create template
            $template = CertificateTemplate::create([
                'acc_id' => $acc->id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'template_html' => $templateHtml,
                'template_config' => $templateConfig,
                'template_variables' => $templateVariables,
                'background_image_url' => $backgroundImageUrl,
                'status' => $request->status,
            ]);
            
            return response()->json([
                'message' => 'Template generated successfully from image',
                'template' => $template,
                'ai_analysis' => [
                    'source_image' => $backgroundImageUrl,
                    'orientation' => $request->orientation,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Failed to generate template from image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to generate template from image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Post(
        path: "/acc/certificate-templates",
        summary: "Create certificate template",
        description: "Create a new certificate template for the authenticated ACC. You can either provide template_config (recommended) or template_html. If template_config is provided, HTML will be generated automatically.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id", "name", "status"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template"),
                    new OA\Property(property: "template_config", type: "object", nullable: true),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
                    new OA\Property(property: "background_image", type: "string", format: "binary", nullable: true),
                    new OA\Property(property: "background_image_url", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Template created successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'template_config' => 'nullable|array',
            'template_html' => 'nullable|string',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'background_image_url' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if (!$request->has('template_config') && !$request->has('template_html')) {
            return response()->json([
                'message' => 'Either template_config or template_html must be provided'
            ], 422);
        }

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Handle background image upload
        $backgroundImageUrl = $this->handleBackgroundImageUpload($request);

        // Get template_config and ensure it's an array
        $templateConfig = $request->input('template_config');
        if (is_string($templateConfig)) {
            $templateConfig = json_decode($templateConfig, true);
        }

        // Generate HTML from template_config if provided
        $templateHtml = $request->template_html;
        if ($request->has('template_config') && !$request->has('template_html')) {
            $templateHtml = $this->generateHtmlFromConfig($templateConfig ?? [], $backgroundImageUrl);
        }

        // Extract variables from template_config
        $templateVariables = $this->extractVariablesFromConfig($templateConfig ?? []);

        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'template_html' => $templateHtml,
            'template_config' => $templateConfig,
            'template_variables' => $templateVariables,
            'background_image_url' => $backgroundImageUrl,
            'status' => $request->status,
        ]);

        return response()->json(['template' => $template], 201);
    }

    #[OA\Get(
        path: "/acc/certificate-templates/{id}",
        summary: "Get certificate template details",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Template retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found")
        ]
    )]
    public function show($id)
    {
        $template = CertificateTemplate::with('category')->findOrFail($id);
        return response()->json(['template' => $template]);
    }

    #[OA\Put(
        path: "/acc/certificate-templates/{id}",
        summary: "Update certificate template",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Template updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'template_config' => 'nullable|array',
            'template_html' => 'nullable|string',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'background_image_url' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $updateData = $request->only(['category_id', 'name', 'status']);

        // Handle background image upload
        if ($request->hasFile('background_image')) {
            $updateData['background_image_url'] = $this->handleBackgroundImageUpload($request, $template);
        } elseif ($request->has('background_image_url')) {
            $updateData['background_image_url'] = $request->background_image_url;
        }

        // Handle template_config
        if ($request->has('template_config')) {
            $templateConfig = $request->input('template_config');
            if (is_string($templateConfig)) {
                $templateConfig = json_decode($templateConfig, true);
            }

            $updateData['template_config'] = $templateConfig;

            if (!$request->has('template_html')) {
                $updateData['template_html'] = $this->generateHtmlFromConfig(
                    $templateConfig,
                    $updateData['background_image_url'] ?? $template->background_image_url
                );
            }

            $updateData['template_variables'] = $this->extractVariablesFromConfig($templateConfig);
        }

        if ($request->has('template_html')) {
            $updateData['template_html'] = $request->template_html;
        }

        $template->update($updateData);
        $template->refresh();

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template,
        ]);
    }

    #[OA\Delete(
        path: "/acc/certificate-templates/{id}",
        summary: "Delete certificate template",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Template deleted successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found")
        ]
    )]
    public function destroy($id)
    {
        $user = request()->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    #[OA\Post(
        path: "/acc/certificate-templates/{id}/preview",
        summary: "Preview certificate template",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Preview generated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found")
        ]
    )]
    public function preview(Request $request, $id)
    {
        $request->validate([
            'sample_data' => 'required|array',
        ]);

        $template = CertificateTemplate::findOrFail($id);
        $config = $template->template_config ?? [];

        if (empty($config)) {
            return response()->json([
                'message' => 'Template config is empty. Please use template_config to create templates.',
                'html' => $template->template_html ?? ''
            ], 400);
        }

        // Generate HTML from config (same as PDF generation)
        $html = $this->generateHtmlFromConfig($config, $template->background_image_url);

        // Replace variables with sample data
        $html = $this->replaceVariables($html, $request->sample_data);

        return response()->json([
            'html' => $html,
            'message' => 'Preview generated successfully'
        ]);
    }

    /**
     * Handle background image upload
     */
    private function handleBackgroundImageUpload(Request $request, $template = null)
    {
        if ($request->hasFile('background_image')) {
            try {
                // Delete old image if exists
                if ($template && $template->background_image_url) {
                    $oldPath = str_replace(Storage::disk('public')->url(''), '', $template->background_image_url);
                    Storage::disk('public')->delete($oldPath);
                }

                $file = $request->file('background_image');
                if (!$file->isValid()) {
                    throw new \Exception('Invalid file uploaded');
                }

                $path = $file->store('certificate-templates/backgrounds', 'public');
                return Storage::disk('public')->url($path);

            } catch (\Exception $e) {
                \Log::error('Background image upload failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Failed to upload background image: ' . $e->getMessage());
            }
        }

        return $request->background_image_url ?? null;
    }

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

        // Process background image
        $bgImageStyle = $this->processBackgroundImage($backgroundImageUrl);

        // Build HTML structure
        $html = $this->buildHtmlStructure($config, $width, $height, $orientation, $borderColor, $borderWidth, $backgroundColor, $bgImageStyle);

        return $html;
    }

    /**
     * Process background image URL
     */
    private function processBackgroundImage($backgroundImageUrl)
    {
        if (!$backgroundImageUrl) {
            return '';
        }

        if (!filter_var($backgroundImageUrl, FILTER_VALIDATE_URL)) {
            $backgroundImageUrl = url($backgroundImageUrl);
        }

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

        .verification {
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 9pt;
            color: #95a5a6;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

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
     * Convert font size from px to pt
     */
    private function convertFontSize($fontSize)
    {
        if (strpos($fontSize, 'px') !== false) {
            $pxValue = (float) str_replace('px', '', $fontSize);
            return ($pxValue * 0.75) . 'pt';
        }

        if (strpos($fontSize, 'pt') === false) {
            return '14pt';
        }

        return $fontSize;
    }

    /**
     * Get CSS text-align value from config
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
     * Extract variable names from template configuration
     */
    private function extractVariablesFromConfig($config)
    {
        $variables = [];

        if (isset($config['trainee_name']) && ($config['trainee_name']['show'] ?? true)) {
            $variables[] = 'trainee_name';
        }
        if (isset($config['course_name']) && ($config['course_name']['show'] ?? true)) {
            $variables[] = 'course_name';
        }
        if (isset($config['certificate_number']) && ($config['certificate_number']['show'] ?? true)) {
            $variables[] = 'certificate_number';
        }
        if (isset($config['issue_date']) && ($config['issue_date']['show'] ?? true)) {
            $variables[] = 'issue_date';
        }
        if (isset($config['verification_code']) && ($config['verification_code']['show'] ?? true)) {
            $variables[] = 'verification_code';
        }

        return $variables;
    }

    /**
     * Replace variables in HTML with sample data
     */
    private function replaceVariables($html, $sampleData)
    {
        $replacements = [
            '{{trainee_name}}' => $sampleData['trainee_name'] ?? 'John Doe',
            '{{trainee_id_number}}' => $sampleData['trainee_id_number'] ?? '123456',
            '{{course_name}}' => $sampleData['course_name'] ?? 'Sample Course',
            '{{course_code}}' => $sampleData['course_code'] ?? 'COURSE-001',
            '{{certificate_number}}' => $sampleData['certificate_number'] ?? 'CERT-123456',
            '{{verification_code}}' => $sampleData['verification_code'] ?? 'VERIFY123',
            '{{issue_date}}' => $sampleData['issue_date'] ?? date('Y-m-d'),
            '{{expiry_date}}' => $sampleData['expiry_date'] ?? '',
            '{{training_center_name}}' => $sampleData['training_center_name'] ?? 'Sample Training Center',
            '{{instructor_name}}' => $sampleData['instructor_name'] ?? 'Jane Instructor',
            '{{class_name}}' => $sampleData['class_name'] ?? 'Sample Class',
            '{{acc_name}}' => $sampleData['acc_name'] ?? 'Sample ACC',
        ];

        if (isset($sampleData['issue_date'])) {
            $replacements['{{issue_date_formatted}}'] = date('F d, Y', strtotime($sampleData['issue_date']));
        }
        if (isset($sampleData['expiry_date'])) {
            $replacements['{{expiry_date_formatted}}'] = date('F d, Y', strtotime($sampleData['expiry_date']));
        }

        foreach ($replacements as $variable => $value) {
            $html = str_replace($variable, htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $html);
        }

        return $html;
    }
}
