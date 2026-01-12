<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\CertificateTemplate;
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
                    new OA\Property(property: "category_id", type: "integer", example: 1, description: "Category ID this template belongs to"),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template", description: "Template name"),
                    new OA\Property(property: "template_config", type: "object", nullable: true, description: "Template design configuration (fields for each variable). If provided, HTML will be generated automatically.", properties: [
                        new OA\Property(property: "title", type: "object", nullable: true, properties: [
                            new OA\Property(property: "text", type: "string", example: "Certificate of Completion"),
                            new OA\Property(property: "show", type: "boolean", example: true),
                            new OA\Property(property: "position", type: "string", example: "top-center"),
                            new OA\Property(property: "font_size", type: "string", example: "48px"),
                            new OA\Property(property: "font_weight", type: "string", example: "bold"),
                            new OA\Property(property: "color", type: "string", example: "#2c3e50")
                        ]),
                        new OA\Property(property: "trainee_name", type: "object", nullable: true, properties: [
                            new OA\Property(property: "show", type: "boolean", example: true),
                            new OA\Property(property: "position", type: "string", example: "center"),
                            new OA\Property(property: "font_size", type: "string", example: "36px"),
                            new OA\Property(property: "font_weight", type: "string", example: "bold"),
                            new OA\Property(property: "color", type: "string", example: "#2c3e50")
                        ]),
                        new OA\Property(property: "course_name", type: "object", nullable: true, properties: [
                            new OA\Property(property: "show", type: "boolean", example: true),
                            new OA\Property(property: "position", type: "string", example: "center"),
                            new OA\Property(property: "font_size", type: "string", example: "24px"),
                            new OA\Property(property: "color", type: "string", example: "#34495e")
                        ]),
                        new OA\Property(property: "certificate_number", type: "object", nullable: true, properties: [
                            new OA\Property(property: "show", type: "boolean", example: true),
                            new OA\Property(property: "position", type: "string", example: "bottom-left")
                        ]),
                        new OA\Property(property: "issue_date", type: "object", nullable: true, properties: [
                            new OA\Property(property: "show", type: "boolean", example: true),
                            new OA\Property(property: "position", type: "string", example: "bottom-center")
                        ]),
                        new OA\Property(property: "verification_code", type: "object", nullable: true, properties: [
                            new OA\Property(property: "show", type: "boolean", example: true),
                            new OA\Property(property: "position", type: "string", example: "bottom-right")
                        ]),
                        new OA\Property(property: "layout", type: "object", nullable: true, properties: [
                            new OA\Property(property: "orientation", type: "string", enum: ["portrait", "landscape"], example: "landscape"),
                            new OA\Property(property: "border_color", type: "string", example: "#D4AF37"),
                            new OA\Property(property: "border_width", type: "string", example: "15px"),
                            new OA\Property(property: "background_color", type: "string", example: "#ffffff")
                        ])
                    ]),
                    new OA\Property(property: "template_html", type: "string", nullable: true, example: "<div>Certificate HTML content</div>", description: "HTML content (optional if template_config is provided)"),
                    new OA\Property(property: "background_image", type: "string", format: "binary", nullable: true, description: "Background image file (JPEG, PNG, JPG, GIF - max 5MB). If provided, background_image_url will be ignored."),
                    new OA\Property(property: "background_image_url", type: "string", nullable: true, example: "/storage/templates/background.jpg", description: "URL of background image (if not uploading a file)"),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active", description: "Template status")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Template created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "template", type: "object")
                    ]
                )
            ),
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
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'background_image_url' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        // Either template_config or template_html must be provided
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
        $backgroundImageUrl = $request->background_image_url;
        if ($request->hasFile('background_image')) {
            try {
                $file = $request->file('background_image');
                // Validate file
                if (!$file->isValid()) {
                    return response()->json([
                        'message' => 'Invalid file uploaded',
                        'errors' => ['background_image' => ['The uploaded file is not valid']]
                    ], 422);
                }
                $path = $file->store('certificate-templates/backgrounds', 'public');
                $backgroundImageUrl = Storage::disk('public')->url($path);
            } catch (\Exception $e) {
                \Log::error('Background image upload failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'Failed to upload background image',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        // Get template_config and ensure it's an array with all fields preserved
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
            'template_config' => $templateConfig, // Ensure all fields including position are preserved
            'template_variables' => $templateVariables,
            'background_image_url' => $backgroundImageUrl,
            'status' => $request->status,
        ]);

        return response()->json(['template' => $template], 201);
    }

    /**
     * Generate HTML from template configuration
     */
    private function generateHtmlFromConfig($config, $backgroundImageUrl = null)
    {
        $layout = $config['layout'] ?? [];
        $orientation = $layout['orientation'] ?? 'landscape';
        $borderColor = $layout['border_color'] ?? '#D4AF37';
        $borderWidth = $layout['border_width'] ?? '15px';
        $backgroundColor = $layout['background_color'] ?? '#ffffff';

        // A4 Landscape: 843pt × 596pt (297mm × 210mm)
        // A4 Portrait: 596pt × 843pt (210mm × 297mm)
        // Use points in CSS to match PDF dimensions exactly
        $width = $orientation === 'landscape' ? '843pt' : '596pt';
        $height = $orientation === 'landscape' ? '596pt' : '843pt';

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
            size: ' . $width . ' ' . $height . ';
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
            padding: 20px;
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
            // Convert px to pt for better PDF rendering
            $fontSize = $title['font_size'] ?? '42pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '42pt'; // Default size that fits A4 landscape
            }
            $html .= '
        .title {
            font-size: ' . $fontSize . ';
            font-weight: ' . ($title['font_weight'] ?? 'bold') . ';
            color: ' . ($title['color'] ?? '#2c3e50') . ';
            margin-bottom: 10px;
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
            $fontSize = $trainee['font_size'] ?? '32pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '32pt'; // Default size that fits A4 landscape
            }
            $html .= '
        .trainee-name {
            font-size: ' . $fontSize . ';
            font-weight: ' . ($trainee['font_weight'] ?? 'bold') . ';
            color: ' . ($trainee['color'] ?? '#2c3e50') . ';
            margin: 12px 0;
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
            $fontSize = $course['font_size'] ?? '22pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '22pt'; // Default size that fits A4 landscape
            }
            $html .= '
        .course-name {
            font-size: ' . $fontSize . ';
            color: ' . ($course['color'] ?? '#34495e') . ';
            margin: 10px 0;
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
            $fontSize = $subtitle['font_size'] ?? '16pt';
            if (strpos($fontSize, 'px') !== false) {
                $pxValue = (float) str_replace('px', '', $fontSize);
                $fontSize = ($pxValue * 0.75) . 'pt';
            } elseif (strpos($fontSize, 'pt') === false) {
                $fontSize = '16pt'; // Default size that fits A4 landscape
            }
            $html .= '
        .subtitle {
            font-size: ' . $fontSize . ';
            color: ' . ($subtitle['color'] ?? '#7f8c8d') . ';
            margin: 6px 0;
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
            font-size: 16pt;
            color: #7f8c8d;
            margin: 6px 0;
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
            padding-top: 15px;
            font-size: 12pt;
            color: #7f8c8d;
            width: 100%;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }
        .details p {
            margin: 3px 0;
            font-size: 12pt;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            orphans: 0;
            widows: 0;
        }
        .verification {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 10pt;
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
            overflow: hidden;
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

    #[OA\Get(
        path: "/acc/certificate-templates/{id}",
        summary: "Get certificate template details",
        description: "Get detailed information about a specific certificate template.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Template retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "template", type: "object")
                    ]
                )
            ),
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
        description: "Update an existing certificate template.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "category_id", type: "integer", nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
                    new OA\Property(property: "template_variables", type: "array", items: new OA\Items(type: "string"), nullable: true),
                    new OA\Property(property: "background_image_url", type: "string", nullable: true),
                    new OA\Property(property: "logo_positions", type: "object", nullable: true),
                    new OA\Property(property: "signature_positions", type: "object", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Template updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Template updated successfully"),
                        new OA\Property(property: "template", type: "object")
                    ]
                )
            ),
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
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'background_image_url' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $updateData = $request->only([
            'category_id', 'name', 'status'
        ]);

        // Handle background image upload
        if ($request->hasFile('background_image')) {
            try {
                // Delete old image if exists
                if ($template->background_image_url) {
                    $oldPath = str_replace(Storage::disk('public')->url(''), '', $template->background_image_url);
                    Storage::disk('public')->delete($oldPath);
                }
                $file = $request->file('background_image');
                // Validate file
                if (!$file->isValid()) {
                    return response()->json([
                        'message' => 'Invalid file uploaded',
                        'errors' => ['background_image' => ['The uploaded file is not valid']]
                    ], 422);
                }
                $path = $file->store('certificate-templates/backgrounds', 'public');
                $updateData['background_image_url'] = Storage::disk('public')->url($path);
            } catch (\Exception $e) {
                \Log::error('Background image upload failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'message' => 'Failed to upload background image',
                    'error' => $e->getMessage()
                ], 500);
            }
        } elseif ($request->has('background_image_url')) {
            $updateData['background_image_url'] = $request->background_image_url;
        }

        // Handle template_config
        if ($request->has('template_config')) {
            // Get template_config and ensure it's an array with all fields preserved
            $templateConfig = $request->input('template_config');
            
            // Ensure template_config is saved as-is with all fields including position
            // Convert to array if it's JSON string, otherwise use as-is
            if (is_string($templateConfig)) {
                $templateConfig = json_decode($templateConfig, true);
            }
            
            // Ensure all nested fields are preserved
            $updateData['template_config'] = $templateConfig;
            
            // Generate HTML from config if template_html not provided
            if (!$request->has('template_html')) {
                $updateData['template_html'] = $this->generateHtmlFromConfig(
                    $templateConfig, 
                    $updateData['background_image_url'] ?? $template->background_image_url
                );
            }
            // Extract variables
            $updateData['template_variables'] = $this->extractVariablesFromConfig($templateConfig);
        }

        // Handle template_html if provided
        if ($request->has('template_html')) {
            $updateData['template_html'] = $request->template_html;
        }

        $template->update($updateData);

        // Refresh template to ensure all data is loaded correctly
        $template->refresh();

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template,
        ]);
    }

    #[OA\Delete(
        path: "/acc/certificate-templates/{id}",
        summary: "Delete certificate template",
        description: "Delete a certificate template. This action cannot be undone.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Template deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Template deleted successfully")
                    ]
                )
            ),
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
        description: "Generate a preview of the certificate template with sample data.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["sample_data"],
                properties: [
                    new OA\Property(property: "sample_data", type: "object", example: ["trainee_name" => "John Doe", "course_name" => "Fire Safety"], description: "Sample data to use in the preview")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Preview generated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "preview_url", type: "string", example: "/preview/template_1.pdf"),
                        new OA\Property(property: "message", type: "string", example: "Preview generated successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function preview(Request $request, $id)
    {
        $request->validate([
            'sample_data' => 'required|array',
        ]);

        $template = CertificateTemplate::findOrFail($id);
        
        // Generate HTML preview with sample data
        $config = $template->template_config ?? [];
        
        if (empty($config)) {
            return response()->json([
                'message' => 'Template config is empty. Please use template_config to create templates.',
                'html' => $template->template_html ?? ''
            ], 400);
        }
        
        // Generate HTML from config
        $html = $this->generateHtmlFromConfig($config, $template->background_image_url);
        
        // Replace variables with sample data
        $sampleData = $request->sample_data;
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
        
        // Format dates
        if (isset($sampleData['issue_date'])) {
            $replacements['{{issue_date_formatted}}'] = date('F d, Y', strtotime($sampleData['issue_date']));
        }
        if (isset($sampleData['expiry_date'])) {
            $replacements['{{expiry_date_formatted}}'] = date('F d, Y', strtotime($sampleData['expiry_date']));
        }
        
        // Replace all variables
        foreach ($replacements as $variable => $value) {
            $html = str_replace($variable, htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $html);
        }
        
        // Background image URL is already handled in generateHtmlFromConfig
        // No need to replace it again here as it's already included in the HTML
        
        return response()->json([
            'html' => $html,
            'message' => 'Preview generated successfully'
        ]);
    }
}

