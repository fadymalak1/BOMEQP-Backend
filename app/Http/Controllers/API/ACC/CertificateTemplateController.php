<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\CertificateTemplate;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CertificateTemplateController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
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
        description: "Create a new certificate template for the authenticated ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id", "name", "status"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template"),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
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
            'template_html' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'template_html' => $request->template_html,
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
            'template_html' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'config_json' => 'nullable|array',
        ]);

        $updateData = $request->only(['category_id', 'name', 'template_html', 'status', 'config_json']);
        $template->update($updateData);

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template->fresh(),
        ]);
    }

    #[OA\Post(
        path: "/acc/certificate-templates/{id}/upload-background",
        summary: "Upload background image for certificate template",
        description: "Upload a high-resolution background image (JPG/PNG) for the certificate template.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "background_image", type: "string", format: "binary")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Background image uploaded successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function uploadBackgroundImage(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'background_image' => 'required|image|mimes:jpeg,jpg,png|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('background_image');
            
            // Delete old background image if exists
            if ($template->background_image_url) {
                $this->deleteBackgroundImage($template->background_image_url);
            }

            // Store in public storage
            $directory = 'certificate-templates/' . $template->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $fileName = time() . '_' . $template->id . '_background.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($directory, $fileName, 'public');
            $fileUrl = Storage::disk('public')->url($filePath);

            $template->update(['background_image_url' => $fileUrl]);

            return response()->json([
                'message' => 'Background image uploaded successfully',
                'background_image_url' => $fileUrl,
                'template' => $template->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading background image', [
                'template_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload background image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: "/acc/certificate-templates/{id}/config",
        summary: "Update certificate template configuration",
        description: "Update the template designer configuration (config_json) with placeholders, coordinates, and styling.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["config_json"],
                properties: [
                    new OA\Property(
                        property: "config_json",
                        type: "array",
                        description: "Array of placeholder configurations with coordinates (as percentages), styling, etc.",
                        items: new OA\Items(type: "object")
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Configuration updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function updateConfig(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'config_json' => 'required|array',
            'config_json.*.variable' => 'required|string',
            'config_json.*.x' => 'required|numeric|min:0|max:1',
            'config_json.*.y' => 'required|numeric|min:0|max:1',
            'config_json.*.font_family' => 'nullable|string',
            'config_json.*.fontFamily' => 'nullable|string', // Accept camelCase from frontend
            'config_json.*.font_size' => 'nullable|integer|min:8|max:200',
            'config_json.*.fontSize' => 'nullable|integer|min:8|max:200', // Accept camelCase from frontend
            'config_json.*.color' => 'nullable|string',
            'config_json.*.text_align' => 'nullable|in:left,center,right',
            'config_json.*.textAlign' => 'nullable|in:left,center,right', // Accept camelCase from frontend
        ]);

        $template->update(['config_json' => $request->config_json]);

        return response()->json([
            'message' => 'Template configuration updated successfully',
            'template' => $template->fresh(),
        ]);
    }

    #[OA\Delete(
        path: "/acc/certificate-templates/{id}",
        summary: "Delete certificate template",
        description: "Delete a certificate template. Cannot delete if certificates are using this template unless force=true parameter is provided.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "force", in: "query", required: false, schema: new OA\Schema(type: "boolean", default: false), description: "If true, deletes all certificates using this template before deleting the template")
        ],
        responses: [
            new OA\Response(response: 200, description: "Template deleted successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 409, description: "Cannot delete template: certificates are using this template")
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);
        
        // Check if there are certificates using this template
        $certificateCount = $template->certificates()->count();
        $force = $request->boolean('force', false);
        
        if ($certificateCount > 0 && !$force) {
            return response()->json([
                'message' => 'Cannot delete certificate template',
                'error' => "This template is being used by {$certificateCount} certificate(s). Please reassign or delete the certificates first before deleting this template, or use force=true to delete the template and all associated certificates.",
                'certificate_count' => $certificateCount,
                'hint' => 'Add ?force=true to delete this template and all associated certificates'
            ], 409);
        }

        try {
            DB::beginTransaction();

            // If force delete, delete all associated certificates first
            if ($force && $certificateCount > 0) {
                $certificates = $template->certificates()->get();
                
                // Delete certificate PDF files from storage if they exist
                foreach ($certificates as $certificate) {
                    if ($certificate->certificate_pdf_url) {
                        try {
                            $urlParts = parse_url($certificate->certificate_pdf_url);
                            $path = ltrim($urlParts['path'] ?? '', '/');
                            
                            // Extract file path and delete from storage
                            if (Storage::disk('public')->exists($path)) {
                                Storage::disk('public')->delete($path);
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to delete certificate PDF file', [
                                'certificate_id' => $certificate->id,
                                'pdf_url' => $certificate->certificate_pdf_url,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // Delete all certificates
                $template->certificates()->delete();
                
                Log::info('Force deleted certificates with template', [
                    'template_id' => $template->id,
                    'certificate_count' => $certificateCount,
                ]);
            }
            
            // Delete background image if exists
            if ($template->background_image_url) {
                $this->deleteBackgroundImage($template->background_image_url);
            }

            // Delete the template
            $template->delete();

            DB::commit();

            $message = $force && $certificateCount > 0 
                ? "Template and {$certificateCount} associated certificate(s) deleted successfully"
                : 'Template deleted successfully';

            return response()->json([
                'message' => $message,
                'certificates_deleted' => $force ? $certificateCount : 0
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete certificate template', [
                'template_id' => $id,
                'force' => $force,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to delete certificate template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete background image from storage
     */
    private function deleteBackgroundImage(string $imageUrl): bool
    {
        try {
            $urlParts = parse_url($imageUrl);
            $path = ltrim($urlParts['path'] ?? '', '/');
            
            // Extract file path from URL
            $pattern = '#certificate-templates/\d+/(.+)$#';
            if (preg_match($pattern, $path, $matches)) {
                $filePath = 'certificate-templates/' . preg_replace('#certificate-templates/(\d+)/.*#', '$1', $path) . '/' . $matches[1];
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to delete background image', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
