<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CertificateTemplateController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    #[OA\Get(
        path: "/admin/certificate-templates",
        summary: "List group admin certificate templates",
        description: "Get all certificate templates created by group admin (not tied to any ACC).",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["active", "inactive"])),
            new OA\Parameter(name: "template_type", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["instructor"])),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 10)),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: "Templates retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = CertificateTemplate::where('is_group_admin_template', true)
            ->with(['createdBy:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('template_type')) {
            $validTypes = ['instructor'];
            if (in_array($request->template_type, $validTypes)) {
                $query->where('template_type', $request->template_type);
            }
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $perPage    = $request->get('per_page', 10);
        $templates  = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($templates);
    }

    #[OA\Post(
        path: "/admin/certificate-templates",
        summary: "Create group admin certificate template",
        description: "Create an instructor certificate template managed by the group admin. When an instructor gets authorized by at least 3 ACCs, this template is used to generate and email their certificate.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "status"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Group Instructor Excellence Certificate"),
                    new OA\Property(property: "orientation", type: "string", enum: ["landscape", "portrait"], example: "landscape"),
                    new OA\Property(property: "template_html", type: "string", nullable: true, description: "HTML template with {{variable}} placeholders"),
                    new OA\Property(property: "config_json", type: "object", nullable: true, description: "Template designer config with elements array"),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Template created successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'orientation'   => 'sometimes|in:landscape,portrait',
            'template_html' => 'nullable|string',
            'config_json'   => 'nullable',
            'status'        => 'required|in:active,inactive',
        ]);

        $user = $request->user();

        // Only one active group-admin instructor template at a time
        if ($request->status === 'active') {
            $existing = CertificateTemplate::where('is_group_admin_template', true)
                ->where('template_type', 'instructor')
                ->where('status', 'active')
                ->first();

            if ($existing) {
                return response()->json([
                    'message'              => 'An active group admin instructor certificate template already exists. Please deactivate it before creating a new one.',
                    'existing_template_id' => $existing->id,
                    'existing_template_name' => $existing->name,
                ], 422);
            }
        }

        $configJson = $request->config_json;
        if (is_string($configJson)) {
            $configJson = json_decode($configJson, true) ?: null;
        }

        $template = CertificateTemplate::create([
            'acc_id'                  => null,
            'created_by'              => $user->id,
            'is_group_admin_template' => true,
            'template_type'           => 'instructor',
            'orientation'             => $request->get('orientation', 'landscape'),
            'category_id'             => null,
            'course_id'               => null,
            'name'                    => $request->name,
            'template_html'           => $request->template_html,
            'config_json'             => $configJson,
            'status'                  => $request->status,
        ]);

        return response()->json([
            'message'  => 'Certificate template created successfully',
            'template' => $template->load('createdBy:id,name,email'),
        ], 201);
    }

    #[OA\Get(
        path: "/admin/certificate-templates/{id}",
        summary: "Get group admin certificate template",
        tags: ["Admin"],
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
        $template = CertificateTemplate::where('is_group_admin_template', true)
            ->with(['createdBy:id,name,email'])
            ->findOrFail($id);

        return response()->json(['template' => $template]);
    }

    #[OA\Put(
        path: "/admin/certificate-templates/{id}",
        summary: "Update group admin certificate template",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "orientation", type: "string", enum: ["landscape", "portrait"]),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
                    new OA\Property(property: "config_json", type: "object", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Template updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $template = CertificateTemplate::where('is_group_admin_template', true)->findOrFail($id);

        $request->validate([
            'name'          => 'sometimes|string|max:255',
            'orientation'   => 'sometimes|in:landscape,portrait',
            'template_html' => 'nullable|string',
            'config_json'   => 'nullable',
            'status'        => 'sometimes|in:active,inactive',
        ]);

        // Ensure only one active group-admin instructor template at a time
        if ($request->has('status') && $request->status === 'active' && $template->status !== 'active') {
            $existing = CertificateTemplate::where('is_group_admin_template', true)
                ->where('template_type', 'instructor')
                ->where('status', 'active')
                ->where('id', '!=', $template->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message'              => 'Another active group admin instructor template already exists. Please deactivate it first.',
                    'existing_template_id' => $existing->id,
                    'existing_template_name' => $existing->name,
                ], 422);
            }
        }

        $updateData = [];
        foreach (['name', 'template_html', 'status', 'orientation', 'config_json'] as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);
                if ($key === 'config_json' && is_string($value)) {
                    $value = json_decode($value, true);
                }
                $updateData[$key] = $value;
            }
        }

        if (!empty($updateData)) {
            $template->update($updateData);
        }

        return response()->json([
            'message'  => 'Template updated successfully',
            'template' => $template->fresh(['createdBy:id,name,email']),
        ]);
    }

    #[OA\Post(
        path: "/admin/certificate-templates/{id}/upload-background",
        summary: "Upload background image for group admin certificate template",
        tags: ["Admin"],
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
        $template = CertificateTemplate::where('is_group_admin_template', true)->findOrFail($id);

        $request->validate([
            'background_image' => 'required|image|mimetypes:image/jpeg,image/png|max:10240',
        ]);

        try {
            $file = $request->file('background_image');

            if ($template->background_image_url) {
                $this->deleteBackgroundImage($template->background_image_url);
            }

            $directory = 'certificate-templates/' . $template->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $fileName = time() . '_' . $template->id . '_background.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($directory, $fileName, 'public');
            $fileUrl  = Storage::disk('public')->url($filePath);

            $template->update(['background_image_url' => $fileUrl]);

            return response()->json([
                'message'              => 'Background image uploaded successfully',
                'background_image_url' => $fileUrl,
                'template'             => $template->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading background image for group admin template', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload background image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: "/admin/certificate-templates/{id}/config",
        summary: "Update group admin certificate template configuration",
        description: "Update the template designer configuration (config_json) with placeholders, coordinates, and styling.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["config_json"],
                properties: [
                    new OA\Property(property: "config_json", description: "Object with 'elements' array or direct array. Each element: id, type (text|image), variable, x, y, width, height (required for images)")
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
        $template = CertificateTemplate::where('is_group_admin_template', true)->findOrFail($id);

        $configJson = $request->config_json;
        if (is_string($configJson)) {
            $configJson = json_decode($configJson, true);
        }

        $elements = isset($configJson['elements']) ? $configJson['elements'] : $configJson;
        if (!is_array($elements)) {
            return response()->json([
                'message' => 'config_json must be an object with elements array or an array of elements',
                'errors'  => ['config_json' => ['Invalid structure']],
            ], 422);
        }

        foreach ($elements as $i => $el) {
            $type = $el['type'] ?? 'text';
            if (!in_array($type, ['text', 'image'])) {
                return response()->json([
                    'message' => "Element {$i}: type must be 'text' or 'image'",
                    'errors'  => ['config_json' => ["Invalid type at index {$i}"]],
                ], 422);
            }
            if (empty($el['variable'])) {
                return response()->json([
                    'message' => "Element {$i}: variable is required",
                    'errors'  => ['config_json' => ["Missing variable at index {$i}"]],
                ], 422);
            }
            $x = $el['x'] ?? null;
            $y = $el['y'] ?? null;
            if ($x === null || $y === null || $x < 0 || $x > 1 || $y < 0 || $y > 1) {
                return response()->json([
                    'message' => "Element {$i}: x and y must be between 0 and 1",
                    'errors'  => ['config_json' => ["Invalid coordinates at index {$i}"]],
                ], 422);
            }
            if ($type === 'image') {
                if (!isset($el['width']) || !isset($el['height']) || $el['width'] < 0 || $el['width'] > 1 || $el['height'] < 0 || $el['height'] > 1) {
                    return response()->json([
                        'message' => "Element {$i}: width and height (0-1) are required for image elements",
                        'errors'  => ['config_json' => ["Invalid dimensions at index {$i}"]],
                    ], 422);
                }
            }
        }

        $template->update(['config_json' => ['elements' => $elements]]);

        return response()->json([
            'message'  => 'Template configuration updated successfully',
            'template' => $template->fresh(),
        ]);
    }

    #[OA\Delete(
        path: "/admin/certificate-templates/{id}",
        summary: "Delete group admin certificate template",
        tags: ["Admin"],
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
        $template = CertificateTemplate::where('is_group_admin_template', true)->findOrFail($id);

        $certificateCount = $template->certificates()->count();

        try {
            DB::beginTransaction();

            if ($template->background_image_url) {
                $this->deleteBackgroundImage($template->background_image_url);
            }

            $template->delete();

            DB::commit();

            $message = $certificateCount > 0
                ? "Template deleted successfully. {$certificateCount} certificate(s) using this template have been preserved."
                : 'Template deleted successfully';

            return response()->json([
                'message'               => $message,
                'certificates_preserved' => $certificateCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete group admin certificate template', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete certificate template',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    private function deleteBackgroundImage(string $imageUrl): bool
    {
        try {
            $urlParts = parse_url($imageUrl);
            $path     = ltrim($urlParts['path'] ?? '', '/');

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
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }
}
