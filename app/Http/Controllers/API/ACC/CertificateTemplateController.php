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
            ->with(['category', 'course'])
            ->orderBy('created_at', 'desc')
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
                required: ["name", "status"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", nullable: true, example: 1, description: "Category ID - applies template to all courses in this category. Either category_id OR course_id must be provided."),
                    new OA\Property(property: "course_id", type: "integer", nullable: true, example: 5, description: "Course ID - applies template to this specific course only. Either category_id OR course_id must be provided."),
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
            'category_id' => 'nullable|exists:categories,id',
            'course_id' => 'nullable|exists:courses,id',
            'name' => 'required|string|max:255',
            'template_html' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        // Validate that either category_id or course_id is provided, but not both
        if (!$request->has('category_id') && !$request->has('course_id')) {
            return response()->json([
                'message' => 'Either category_id or course_id must be provided',
                'errors' => [
                    'category_id' => ['Either category_id or course_id must be provided'],
                    'course_id' => ['Either category_id or course_id must be provided'],
                ]
            ], 422);
        }

        if ($request->has('category_id') && $request->has('course_id')) {
            return response()->json([
                'message' => 'Cannot specify both category_id and course_id. Please provide either category_id OR course_id',
                'errors' => [
                    'category_id' => ['Cannot specify both category_id and course_id'],
                    'course_id' => ['Cannot specify both category_id and course_id'],
                ]
            ], 422);
        }

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // If course_id is provided, verify the course belongs to this ACC
        if ($request->has('course_id')) {
            $course = \App\Models\Course::findOrFail($request->course_id);
            if ($course->acc_id !== $acc->id) {
                return response()->json([
                    'message' => 'Course does not belong to this ACC'
                ], 403);
            }

            // Check if a template already exists for this course
            $existingTemplate = CertificateTemplate::where('acc_id', $acc->id)
                ->where('course_id', $request->course_id)
                ->first();

            if ($existingTemplate) {
                return response()->json([
                    'message' => 'A certificate template already exists for this course',
                    'errors' => [
                        'course_id' => ['A certificate template already exists for this course. Please update the existing template or delete it first.']
                    ],
                    'existing_template' => [
                        'id' => $existingTemplate->id,
                        'name' => $existingTemplate->name,
                    ]
                ], 422);
            }
        }

        // If category_id is provided, check if a template already exists for this category
        if ($request->has('category_id')) {
            $existingTemplate = CertificateTemplate::where('acc_id', $acc->id)
                ->where('category_id', $request->category_id)
                ->whereNull('course_id') // Only check category-level templates
                ->first();

            if ($existingTemplate) {
                return response()->json([
                    'message' => 'A certificate template already exists for this category',
                    'errors' => [
                        'category_id' => ['A certificate template already exists for this category. Please update the existing template or delete it first.']
                    ],
                    'existing_template' => [
                        'id' => $existingTemplate->id,
                        'name' => $existingTemplate->name,
                    ]
                ], 422);
            }
        }

        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'category_id' => $request->category_id,
            'course_id' => $request->course_id,
            'name' => $request->name,
            'template_html' => $request->template_html,
            'status' => $request->status,
        ]);

        return response()->json(['template' => $template->load(['category', 'course'])], 201);
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
        $template = CertificateTemplate::with(['category', 'course'])->findOrFail($id);
        return response()->json(['template' => $template]);
    }

    #[OA\Put(
        path: "/acc/certificate-templates/{id}",
        summary: "Update certificate template",
        description: "Update a certificate template. Either category_id OR course_id can be provided, but not both.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "category_id", type: "integer", nullable: true, example: 1, description: "Category ID - applies template to all courses in this category. Either category_id OR course_id must be provided."),
                    new OA\Property(property: "course_id", type: "integer", nullable: true, example: 5, description: "Course ID - applies template to this specific course only. Either category_id OR course_id must be provided."),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template"),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active"),
                    new OA\Property(property: "config_json", type: "array", nullable: true, items: new OA\Items(type: "object"))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Template updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Course does not belong to this ACC"),
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
            'category_id' => 'nullable|exists:categories,id',
            'course_id' => 'nullable|exists:courses,id',
            'name' => 'sometimes|string|max:255',
            'template_html' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'config_json' => 'nullable|array',
        ]);

        // Determine what the final values will be after update
        $finalCategoryId = $request->has('category_id') ? $request->category_id : $template->category_id;
        $finalCourseId = $request->has('course_id') ? $request->course_id : $template->course_id;

        // Validate that either category_id or course_id is provided, but not both
        if ($request->has('category_id') && $request->has('course_id')) {
            return response()->json([
                'message' => 'Cannot specify both category_id and course_id. Please provide either category_id OR course_id',
                'errors' => [
                    'category_id' => ['Cannot specify both category_id and course_id'],
                    'course_id' => ['Cannot specify both category_id and course_id'],
                ]
            ], 422);
        }

        // Ensure at least one of category_id or course_id is set after update
        if (!$finalCategoryId && !$finalCourseId) {
            return response()->json([
                'message' => 'Either category_id or course_id must be set. Cannot clear both fields.',
                'errors' => [
                    'category_id' => ['Either category_id or course_id must be set'],
                    'course_id' => ['Either category_id or course_id must be set'],
                ]
            ], 422);
        }

        // If course_id is provided, verify the course belongs to this ACC
        if ($request->has('course_id') && $request->course_id) {
            $course = \App\Models\Course::findOrFail($request->course_id);
            if ($course->acc_id !== $acc->id) {
                return response()->json([
                    'message' => 'Course does not belong to this ACC'
                ], 403);
            }
        }

        // Check for duplicate templates based on final values (after update)
        // Only check if the values are actually changing
        $categoryChanged = $request->has('category_id') && $request->category_id != $template->category_id;
        $courseChanged = $request->has('course_id') && $request->course_id != $template->course_id;

        // If final course_id is set, check for duplicate course template
        if ($finalCourseId && ($courseChanged || ($request->has('course_id') && $template->category_id))) {
            $existingTemplate = CertificateTemplate::where('acc_id', $acc->id)
                ->where('course_id', $finalCourseId)
                ->where('id', '!=', $template->id)
                ->first();

            if ($existingTemplate) {
                return response()->json([
                    'message' => 'A certificate template already exists for this course',
                    'errors' => [
                        'course_id' => ['A certificate template already exists for this course. Please update the existing template or delete it first.']
                    ],
                    'existing_template' => [
                        'id' => $existingTemplate->id,
                        'name' => $existingTemplate->name,
                    ]
                ], 422);
            }
        }

        // If final category_id is set (and no course_id), check for duplicate category template
        if ($finalCategoryId && !$finalCourseId && ($categoryChanged || ($request->has('category_id') && $template->course_id))) {
            $existingTemplate = CertificateTemplate::where('acc_id', $acc->id)
                ->where('category_id', $finalCategoryId)
                ->whereNull('course_id') // Only check category-level templates
                ->where('id', '!=', $template->id)
                ->first();

            if ($existingTemplate) {
                return response()->json([
                    'message' => 'A certificate template already exists for this category',
                    'errors' => [
                        'category_id' => ['A certificate template already exists for this category. Please update the existing template or delete it first.']
                    ],
                    'existing_template' => [
                        'id' => $existingTemplate->id,
                        'name' => $existingTemplate->name,
                    ]
                ], 422);
            }
        }

        $updateData = $request->only(['category_id', 'course_id', 'name', 'template_html', 'status', 'config_json']);
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
        description: "Delete a certificate template. Certificates using this template will remain but their template_id will be set to null.",
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
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);
        
        // Get count of certificates using this template (for informational purposes)
        $certificateCount = $template->certificates()->count();

        try {
            DB::beginTransaction();
            
            // Delete background image if exists
            if ($template->background_image_url) {
                $this->deleteBackgroundImage($template->background_image_url);
            }

            // Delete the template
            // The foreign key constraint will automatically set template_id to null in certificates
            $template->delete();

            DB::commit();

            $message = $certificateCount > 0 
                ? "Template deleted successfully. {$certificateCount} certificate(s) that were using this template have been preserved, but their template reference has been removed."
                : 'Template deleted successfully';

            return response()->json([
                'message' => $message,
                'certificates_preserved' => $certificateCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete certificate template', [
                'template_id' => $id,
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
