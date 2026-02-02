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
        description: "Get all certificate templates for the authenticated ACC with pagination and search.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by template name"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["active", "inactive"]), description: "Filter by template status"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 10), example: 10),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Templates retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "last_page", type: "integer"),
                        new OA\Property(property: "from", type: "integer", nullable: true),
                        new OA\Property(property: "to", type: "integer", nullable: true)
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

        $query = CertificateTemplate::where('acc_id', $acc->id)
            ->with(['category', 'course', 'courses']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        $perPage = $request->get('per_page', 10);
        $templates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($templates);
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
                required: ["name", "status", "course_ids"],
                properties: [
                    new OA\Property(property: "course_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3], description: "Array of course IDs - applies template to these specific courses. At least one course_id must be provided."),
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
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'template_html' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Verify all courses belong to this ACC
        $courses = \App\Models\Course::whereIn('id', $request->course_ids)->get();
        
        if ($courses->count() !== count($request->course_ids)) {
            $foundIds = $courses->pluck('id')->toArray();
            $missingIds = array_diff($request->course_ids, $foundIds);
            return response()->json([
                'message' => 'One or more courses were not found',
                'errors' => [
                    'course_ids' => ['The following course IDs do not exist: ' . implode(', ', $missingIds)]
                ],
                'missing_course_ids' => array_values($missingIds)
            ], 422);
        }

        $unauthorizedCourses = [];
        foreach ($courses as $course) {
            if ($course->acc_id !== $acc->id) {
                $unauthorizedCourses[] = [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                ];
            }
        }

        if (!empty($unauthorizedCourses)) {
            $courseNames = array_map(function ($c) {
                return "'{$c['course_name']}' (ID: {$c['course_id']})";
            }, $unauthorizedCourses);
            
            $errorMessage = count($unauthorizedCourses) === 1
                ? "The course {$courseNames[0]} does not belong to your ACC. You can only create templates for your own courses."
                : "The following courses do not belong to your ACC. You can only create templates for your own courses: " . implode(', ', $courseNames);

            return response()->json([
                'message' => $errorMessage,
                'errors' => [
                    'course_ids' => array_map(function ($c) {
                        return "Course '{$c['course_name']}' (ID: {$c['course_id']}) does not belong to your ACC";
                    }, $unauthorizedCourses)
                ],
                'unauthorized_courses' => $unauthorizedCourses
            ], 403);
        }

        // Check if any of the courses already have a template (check both new many-to-many and legacy course_id)
        $conflictingCourses = [];
        $conflictingDetails = [];

        foreach ($request->course_ids as $courseId) {
            // Check if course is in any template via many-to-many relationship
            $templateViaPivot = CertificateTemplate::where('acc_id', $acc->id)
                ->whereHas('courses', function ($query) use ($courseId) {
                    $query->where('courses.id', $courseId);
                })
                ->with('courses')
                ->first();

            // Check if course has a template via legacy course_id field
            $templateViaLegacy = CertificateTemplate::where('acc_id', $acc->id)
                ->where('course_id', $courseId)
                ->first();

            if ($templateViaPivot) {
                $course = \App\Models\Course::find($courseId);
                $conflictingCourses[] = $courseId;
                $conflictingDetails[] = [
                    'course_id' => $courseId,
                    'course_name' => $course ? $course->name : "Course #{$courseId}",
                    'existing_template_id' => $templateViaPivot->id,
                    'existing_template_name' => $templateViaPivot->name,
                ];
            } elseif ($templateViaLegacy) {
                $course = \App\Models\Course::find($courseId);
                $conflictingCourses[] = $courseId;
                $conflictingDetails[] = [
                    'course_id' => $courseId,
                    'course_name' => $course ? $course->name : "Course #{$courseId}",
                    'existing_template_id' => $templateViaLegacy->id,
                    'existing_template_name' => $templateViaLegacy->name,
                ];
            }
        }

        if (!empty($conflictingCourses)) {
            $courseNames = array_map(function ($detail) {
                return $detail['course_name'];
            }, $conflictingDetails);
            
            $errorMessage = count($conflictingCourses) === 1 
                ? "The course '{$courseNames[0]}' already has a certificate template. Each course can only have one template."
                : "The following courses already have certificate templates. Each course can only have one template: " . implode(', ', $courseNames);

            return response()->json([
                'message' => $errorMessage,
                'errors' => [
                    'course_ids' => array_map(function ($courseId) use ($conflictingDetails) {
                        $detail = collect($conflictingDetails)->firstWhere('course_id', $courseId);
                        return "Course '{$detail['course_name']}' (ID: {$courseId}) already has a template: '{$detail['existing_template_name']}' (Template ID: {$detail['existing_template_id']})";
                    }, $conflictingCourses)
                ],
                'conflicting_courses' => $conflictingDetails
            ], 422);
        }

        // Create template without course_id or category_id
        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'category_id' => null,
            'course_id' => null,
            'name' => $request->name,
            'template_html' => $request->template_html,
            'status' => $request->status,
        ]);

        // Attach courses to template
        $template->courses()->attach($request->course_ids);

        return response()->json(['template' => $template->load(['category', 'courses'])], 201);
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
        $template = CertificateTemplate::with(['category', 'course', 'courses'])->findOrFail($id);
        return response()->json(['template' => $template]);
    }

    #[OA\Put(
        path: "/acc/certificate-templates/{id}",
        summary: "Update certificate template",
        description: "Update a certificate template. Provide course_ids array to update the courses associated with this template.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "course_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3], description: "Array of course IDs - applies template to these specific courses. At least one course_id must be provided."),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template"),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active"),
                    
new OA\Property(
    property: "config_json",
    type: "array",
    nullable: true,
    items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "variable", type: "string"),
            new OA\Property(property: "x", type: "number", format: "float"),
            new OA\Property(property: "y", type: "number", format: "float"),
            new OA\Property(property: "font_family", type: "string", nullable: true),
            new OA\Property(property: "fontFamily", type: "string", nullable: true),
            new OA\Property(property: "font_size", type: "integer", nullable: true),
            new OA\Property(property: "fontSize", type: "integer", nullable: true),
            new OA\Property(property: "color", type: "string", nullable: true),
            new OA\Property(property: "text_align", type: "string", nullable: true),
            new OA\Property(property: "textAlign", type: "string", nullable: true),
        ]
    )
)
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
            'course_ids' => 'sometimes|array|min:1',
            'course_ids.*' => 'required|exists:courses,id',
            'name' => 'sometimes|string|max:255',
            'template_html' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'config_json' => 'nullable|array',
        ]);

        // If course_ids is provided, validate and update
        if ($request->has('course_ids')) {
            // Verify all courses belong to this ACC
            $courses = \App\Models\Course::whereIn('id', $request->course_ids)->get();
            
            if ($courses->count() !== count($request->course_ids)) {
                $foundIds = $courses->pluck('id')->toArray();
                $missingIds = array_diff($request->course_ids, $foundIds);
                return response()->json([
                    'message' => 'One or more courses were not found',
                    'errors' => [
                        'course_ids' => ['The following course IDs do not exist: ' . implode(', ', $missingIds)]
                    ],
                    'missing_course_ids' => array_values($missingIds)
                ], 422);
            }

            $unauthorizedCourses = [];
            foreach ($courses as $course) {
                if ($course->acc_id !== $acc->id) {
                    $unauthorizedCourses[] = [
                        'course_id' => $course->id,
                        'course_name' => $course->name,
                    ];
                }
            }

            if (!empty($unauthorizedCourses)) {
                $courseNames = array_map(function ($c) {
                    return "'{$c['course_name']}' (ID: {$c['course_id']})";
                }, $unauthorizedCourses);
                
                $errorMessage = count($unauthorizedCourses) === 1
                    ? "The course {$courseNames[0]} does not belong to your ACC. You can only update templates with your own courses."
                    : "The following courses do not belong to your ACC. You can only update templates with your own courses: " . implode(', ', $courseNames);

                return response()->json([
                    'message' => $errorMessage,
                    'errors' => [
                        'course_ids' => array_map(function ($c) {
                            return "Course '{$c['course_name']}' (ID: {$c['course_id']}) does not belong to your ACC";
                        }, $unauthorizedCourses)
                    ],
                    'unauthorized_courses' => $unauthorizedCourses
                ], 403);
            }

            // Check if any of the courses already have a different template (check both new many-to-many and legacy course_id)
            $conflictingCourses = [];
            $conflictingDetails = [];

            foreach ($request->course_ids as $courseId) {
                // Check if course is in any other template via many-to-many relationship
                $templateViaPivot = CertificateTemplate::where('acc_id', $acc->id)
                    ->where('id', '!=', $template->id)
                    ->whereHas('courses', function ($query) use ($courseId) {
                        $query->where('courses.id', $courseId);
                    })
                    ->with('courses')
                    ->first();

                // Check if course has a different template via legacy course_id field
                $templateViaLegacy = CertificateTemplate::where('acc_id', $acc->id)
                    ->where('id', '!=', $template->id)
                    ->where('course_id', $courseId)
                    ->first();

                if ($templateViaPivot) {
                    $course = \App\Models\Course::find($courseId);
                    $conflictingCourses[] = $courseId;
                    $conflictingDetails[] = [
                        'course_id' => $courseId,
                        'course_name' => $course ? $course->name : "Course #{$courseId}",
                        'existing_template_id' => $templateViaPivot->id,
                        'existing_template_name' => $templateViaPivot->name,
                    ];
                } elseif ($templateViaLegacy) {
                    $course = \App\Models\Course::find($courseId);
                    $conflictingCourses[] = $courseId;
                    $conflictingDetails[] = [
                        'course_id' => $courseId,
                        'course_name' => $course ? $course->name : "Course #{$courseId}",
                        'existing_template_id' => $templateViaLegacy->id,
                        'existing_template_name' => $templateViaLegacy->name,
                    ];
                }
            }

            if (!empty($conflictingCourses)) {
                $courseNames = array_map(function ($detail) {
                    return $detail['course_name'];
                }, $conflictingDetails);
                
                $errorMessage = count($conflictingCourses) === 1 
                    ? "The course '{$courseNames[0]}' already has a different certificate template. Each course can only have one template."
                    : "The following courses already have different certificate templates. Each course can only have one template: " . implode(', ', $courseNames);

                return response()->json([
                    'message' => $errorMessage,
                    'errors' => [
                        'course_ids' => array_map(function ($courseId) use ($conflictingDetails) {
                            $detail = collect($conflictingDetails)->firstWhere('course_id', $courseId);
                            return "Course '{$detail['course_name']}' (ID: {$courseId}) already has a template: '{$detail['existing_template_name']}' (Template ID: {$detail['existing_template_id']})";
                        }, $conflictingCourses)
                    ],
                    'conflicting_courses' => $conflictingDetails
                ], 422);
            }

            // Sync courses (replace existing associations)
            $template->courses()->sync($request->course_ids);
        }

        // Update other fields
        $updateData = $request->only(['name', 'template_html', 'status', 'config_json']);
        if (!empty($updateData)) {
            $template->update($updateData);
        }

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template->fresh(['category', 'course', 'courses']),
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
            'background_image' => 'required|image|mimetypes:image/jpeg,image/png|max:10240', // 10MB max
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
