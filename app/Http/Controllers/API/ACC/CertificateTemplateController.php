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
            $file = $request->file('background_image');
            $path = $file->store('certificate-templates/backgrounds', 'public');
            $backgroundImageUrl = Storage::disk('public')->url($path);
        }

        // Generate HTML from template_config if provided
        $templateHtml = $request->template_html;
        if ($request->has('template_config') && !$request->has('template_html')) {
            $templateHtml = $this->generateHtmlFromConfig($request->template_config, $backgroundImageUrl);
        }

        // Extract variables from template_config
        $templateVariables = $this->extractVariablesFromConfig($request->template_config ?? []);

        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'template_html' => $templateHtml,
            'template_config' => $request->template_config,
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
            // Delete old image if exists
            if ($template->background_image_url) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $template->background_image_url);
                Storage::disk('public')->delete($oldPath);
            }
            $file = $request->file('background_image');
            $path = $file->store('certificate-templates/backgrounds', 'public');
            $updateData['background_image_url'] = Storage::disk('public')->url($path);
        } elseif ($request->has('background_image_url')) {
            $updateData['background_image_url'] = $request->background_image_url;
        }

        // Handle template_config
        if ($request->has('template_config')) {
            $updateData['template_config'] = $request->template_config;
            // Generate HTML from config if template_html not provided
            if (!$request->has('template_html')) {
                $updateData['template_html'] = $this->generateHtmlFromConfig(
                    $request->template_config, 
                    $updateData['background_image_url'] ?? $template->background_image_url
                );
            }
            // Extract variables
            $updateData['template_variables'] = $this->extractVariablesFromConfig($request->template_config);
        }

        // Handle template_html if provided
        if ($request->has('template_html')) {
            $updateData['template_html'] = $request->template_html;
        }

        $template->update($updateData);

        return response()->json(['message' => 'Template updated successfully', 'template' => $template]);
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
        
        // TODO: Generate PDF preview with sample data
        // For now, return a placeholder URL
        
        return response()->json([
            'preview_url' => '/preview/template_' . $id . '.pdf',
            'message' => 'Preview generated successfully'
        ]);
    }
}

