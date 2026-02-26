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
            new OA\Parameter(name: "template_type", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["course", "training_center", "instructor"]), description: "Filter by template type"),
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

        // Filter by template_type if provided
        if ($request->has('template_type')) {
            $validTypes = ['course', 'training_center', 'instructor'];
            if (in_array($request->template_type, $validTypes)) {
                $query->where('template_type', $request->template_type);
            }
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
                required: ["name", "status", "template_type"],
                properties: [
                    new OA\Property(property: "template_type", type: "string", enum: ["course", "training_center", "instructor"], example: "course", description: "Type of certificate template: 'course' for course certificates, 'training_center' for training center authorization certificates, 'instructor' for instructor authorization certificates"),
                    new OA\Property(property: "orientation", type: "string", enum: ["landscape", "portrait"], example: "landscape", description: "Page orientation: 'landscape' (1200x848px) or 'portrait' (848x1200px)"),
                    new OA\Property(property: "course_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3], description: "Array of course IDs - required only if template_type is 'course'. Applies template to these specific courses."),
                    new OA\Property(property: "name", type: "string", example: "Fire Safety Certificate Template"),
                    new OA\Property(property: "template_html", type: "string", nullable: true),
                    new OA\Property(property: "config_json", type: "object", nullable: true, description: "Object with elements array for template designer"),
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
            'template_type' => 'required|in:course,training_center,instructor',
            'orientation' => 'sometimes|in:landscape,portrait',
            'course_ids' => 'required_if:template_type,course|array|min:1',
            'course_ids.*' => 'required_if:template_type,course|exists:courses,id',
            'name' => 'required|string|max:255',
            'template_html' => 'nullable|string',
            'config_json' => 'nullable',
            'include_card' => 'sometimes|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        // Validate courses only if template_type is 'course'
        if ($request->template_type === 'course') {
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
                    ->where('template_type', 'course')
                    ->whereHas('courses', function ($query) use ($courseId) {
                        $query->where('courses.id', $courseId);
                    })
                    ->with('courses')
                    ->first();

                // Check if course has a template via legacy course_id field
                $templateViaLegacy = CertificateTemplate::where('acc_id', $acc->id)
                    ->where('template_type', 'course')
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
        }

        // Check if ACC already has a template of this type (for training_center and instructor)
        if (in_array($request->template_type, ['training_center', 'instructor'])) {
            $existingTemplate = CertificateTemplate::where('acc_id', $acc->id)
                ->where('template_type', $request->template_type)
                ->where('status', 'active')
                ->first();

            if ($existingTemplate) {
                $typeName = $request->template_type === 'training_center' ? 'training center' : 'instructor';
                return response()->json([
                    'message' => "You already have an active {$typeName} certificate template. Please update the existing template.",
                    'errors' => [
                        'template_type' => ["A {$typeName} template already exists (Template ID: {$existingTemplate->id})"]
                    ],
                    'existing_template_id' => $existingTemplate->id,
                    'existing_template_name' => $existingTemplate->name,
                ], 422);
            }
        }

        // Create template
        $configJson = $request->config_json;
        if (is_string($configJson)) {
            $configJson = json_decode($configJson, true) ?: null;
        }
        $template = CertificateTemplate::create([
            'acc_id' => $acc->id,
            'template_type' => $request->template_type,
            'orientation' => $request->get('orientation', 'landscape'),
            'category_id' => null,
            'course_id' => null,
            'name' => $request->name,
            'template_html' => $request->template_html,
            'config_json' => $configJson,
            'include_card' => (bool) $request->get('include_card', false),
            'status' => $request->status,
        ]);

        // Attach courses to template only if template_type is 'course'
        if ($request->template_type === 'course' && !empty($request->course_ids)) {
            $template->courses()->attach($request->course_ids);
        }

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
                    new OA\Property(property: "orientation", type: "string", enum: ["landscape", "portrait"], example: "landscape", description: "Page orientation: 'landscape' (1200x848px) or 'portrait' (848x1200px)"),
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
            'orientation' => 'sometimes|in:landscape,portrait',
            'name' => 'sometimes|string|max:255',
            'template_html' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'config_json' => 'nullable',
            'include_card' => 'sometimes|boolean',
        ]);

        // Only allow course_ids updates for course templates
        if ($request->has('course_ids') && $template->template_type !== 'course') {
            return response()->json([
                'message' => 'Course IDs can only be updated for course templates',
                'errors' => [
                    'course_ids' => ['This template type does not support course associations']
                ]
            ], 422);
        }

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
                    ->where('template_type', 'course')
                    ->whereHas('courses', function ($query) use ($courseId) {
                        $query->where('courses.id', $courseId);
                    })
                    ->with('courses')
                    ->first();

                // Check if course has a different template via legacy course_id field
                $templateViaLegacy = CertificateTemplate::where('acc_id', $acc->id)
                    ->where('id', '!=', $template->id)
                    ->where('template_type', 'course')
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

        // Update other fields (config_json can be object with elements or array)
        $updateData = [];
        foreach (['name', 'template_html', 'status', 'orientation', 'config_json', 'include_card'] as $key) {
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
                        description: "Object with 'elements' array, or array of elements. Each element: id, type (text|image), variable, x, y, width, height (required for images), font_family, font_size, color, font_weight, text_align (for text)",
                        properties: [
                            new OA\Property(property: "config_json", description: "Object with 'elements' array or direct array. Each element: id, type (text|image), variable, x, y, width, height (required for images)")
                        ]
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

        $configJson = $request->config_json;
        if (is_string($configJson)) {
            $configJson = json_decode($configJson, true);
        }

        // Support both { elements: [...] } and direct array
        $elements = isset($configJson['elements']) ? $configJson['elements'] : $configJson;
        if (!is_array($elements)) {
            return response()->json([
                'message' => 'config_json must be an object with elements array or an array of elements',
                'errors' => ['config_json' => ['Invalid structure']]
            ], 422);
        }

        // Validate each element
        foreach ($elements as $i => $el) {
            $type = $el['type'] ?? 'text';
            if (!in_array($type, ['text', 'image'])) {
                return response()->json([
                    'message' => "Element {$i}: type must be 'text' or 'image'",
                    'errors' => ['config_json' => ["Invalid type at index {$i}"]]
                ], 422);
            }
            if (empty($el['variable'])) {
                return response()->json([
                    'message' => "Element {$i}: variable is required",
                    'errors' => ['config_json' => ["Missing variable at index {$i}"]]
                ], 422);
            }
            $x = $el['x'] ?? null;
            $y = $el['y'] ?? null;
            if ($x === null || $y === null || $x < 0 || $x > 1 || $y < 0 || $y > 1) {
                return response()->json([
                    'message' => "Element {$i}: x and y must be between 0 and 1",
                    'errors' => ['config_json' => ["Invalid coordinates at index {$i}"]]
                ], 422);
            }
            if ($type === 'image') {
                if (!isset($el['width']) || !isset($el['height']) || $el['width'] < 0 || $el['width'] > 1 || $el['height'] < 0 || $el['height'] > 1) {
                    return response()->json([
                        'message' => "Element {$i}: width and height (0-1) are required for image elements",
                        'errors' => ['config_json' => ["Invalid dimensions at index {$i}"]]
                    ], 422);
                }
            }
        }

        // Store as { elements: [...] } for consistency
        $template->update(['config_json' => ['elements' => $elements]]);

        return response()->json([
            'message' => 'Template configuration updated successfully',
            'template' => $template->fresh(),
        ]);
    }

    // =========================================================================
    // CARD TEMPLATE ENDPOINTS
    // Each ACC can have at most ONE card template, stored on the certificate
    // template record itself (card_* columns). The "include_card" toggle on a
    // template drives whether the generated PDF has a 2nd page (the card).
    // =========================================================================

    #[OA\Get(
        path: "/acc/card-template",
        summary: "Get all card templates for this ACC",
        description: "Returns all certificate templates belonging to the authenticated ACC that have a card design configured (card_template_html or card_background_image_url is set). Multiple certificate templates can each have their own card.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Card templates retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "card_templates",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "name", type: "string"),
                                    new OA\Property(property: "include_card", type: "boolean"),
                                    new OA\Property(property: "card_template_html", type: "string", nullable: true),
                                    new OA\Property(property: "card_background_image_url", type: "string", nullable: true),
                                    new OA\Property(property: "card_config_json", type: "object", nullable: true),
                                    new OA\Property(property: "status", type: "string"),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function getCardTemplate(Request $request)
    {
        $user = $request->user();
        $acc  = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $cardTemplates = CertificateTemplate::where('acc_id', $acc->id)
            ->where(function ($q) {
                $q->whereNotNull('card_template_html')
                  ->orWhereNotNull('card_background_image_url');
            })
            ->select(['id', 'name', 'include_card', 'card_template_html', 'card_background_image_url', 'card_config_json', 'status'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['card_templates' => $cardTemplates]);
    }

    #[OA\Put(
        path: "/acc/certificate-templates/{id}/card",
        summary: "Create or update card template on a certificate template",
        description: "Save the card design (HTML template or config) onto an existing certificate template. Multiple certificate templates can each have their own card design. The 'include_card' flag controls whether this specific template's PDF output will contain a 2nd page (the card).",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "include_card", type: "boolean", example: true, description: "Toggle: when true the generated PDF will have a 2nd page (the card)"),
                    new OA\Property(property: "card_template_html", type: "string", nullable: true, description: "Full HTML for the card page"),
                    new OA\Property(property: "card_config_json", type: "object", nullable: true, description: "Designer config (elements array) for the card"),
                    new OA\Property(property: "name", type: "string", nullable: true, description: "Optional — update the template name at the same time"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Card template saved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function upsertCardTemplate(Request $request, $id)
    {
        $user = $request->user();
        $acc  = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'include_card'       => 'sometimes|boolean',
            'card_template_html' => 'nullable|string',
            'card_config_json'   => 'nullable',
            'name'               => 'sometimes|string|max:255',
        ]);

        $updateData = [];

        if ($request->has('include_card')) {
            $updateData['include_card'] = (bool) $request->include_card;
        }
        if ($request->has('card_template_html')) {
            $updateData['card_template_html'] = $request->card_template_html;
        }
        if ($request->has('card_config_json')) {
            $val = $request->card_config_json;
            if (is_string($val)) {
                $val = json_decode($val, true) ?: null;
            }
            $updateData['card_config_json'] = $val;
        }
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }

        $template->update($updateData);

        return response()->json([
            'message'  => 'Card template saved successfully',
            'template' => $template->fresh(['category', 'courses']),
        ]);
    }

    #[OA\Post(
        path: "/acc/certificate-templates/{id}/upload-card-background",
        summary: "Upload background image for the card page",
        description: "Upload a JPG/PNG background image used as the card page background for this certificate template. Max 10 MB. Automatically sets include_card to true.",
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
                        new OA\Property(property: "card_background_image", type: "string", format: "binary")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Card background image uploaded successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function uploadCardBackgroundImage(Request $request, $id)
    {
        $user = $request->user();
        $acc  = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $request->validate([
            'card_background_image' => 'required|image|mimetypes:image/jpeg,image/png|max:10240',
        ]);

        try {
            $file = $request->file('card_background_image');

            // Delete old card background if exists
            if ($template->card_background_image_url) {
                $this->deleteBackgroundImage($template->card_background_image_url);
            }

            $directory = 'certificate-templates/' . $template->id . '/card';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $fileName = time() . '_' . $template->id . '_card_background.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($directory, $fileName, 'public');
            $fileUrl  = Storage::disk('public')->url($filePath);

            $template->update([
                'card_background_image_url' => $fileUrl,
                'include_card'              => true,
            ]);

            return response()->json([
                'message'                    => 'Card background image uploaded successfully',
                'card_background_image_url'  => $fileUrl,
                'template'                   => $template->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error uploading card background image', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload card background image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: "/acc/certificate-templates/{id}/card-config",
        summary: "Update card template configuration",
        description: "Update the card designer configuration (card_config_json) with placeholders, coordinates, and styling. Same element schema as the certificate config.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["card_config_json"],
                properties: [
                    new OA\Property(
                        property: "card_config_json",
                        description: "Object with 'elements' array or direct array. Each element: id, type (text|image), variable, x, y, width, height (required for images), font_family, font_size, color, text_align"
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Card configuration updated successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function updateCardConfig(Request $request, $id)
    {
        $user = $request->user();
        $acc  = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($id);

        $configJson = $request->card_config_json;
        if (is_string($configJson)) {
            $configJson = json_decode($configJson, true);
        }

        $elements = isset($configJson['elements']) ? $configJson['elements'] : $configJson;
        if (!is_array($elements)) {
            return response()->json([
                'message' => 'card_config_json must be an object with elements array or an array of elements',
                'errors'  => ['card_config_json' => ['Invalid structure']],
            ], 422);
        }

        foreach ($elements as $i => $el) {
            $type = $el['type'] ?? 'text';
            if (!in_array($type, ['text', 'image'])) {
                return response()->json([
                    'message' => "Element {$i}: type must be 'text' or 'image'",
                    'errors'  => ['card_config_json' => ["Invalid type at index {$i}"]],
                ], 422);
            }
            if (empty($el['variable'])) {
                return response()->json([
                    'message' => "Element {$i}: variable is required",
                    'errors'  => ['card_config_json' => ["Missing variable at index {$i}"]],
                ], 422);
            }
            $x = $el['x'] ?? null;
            $y = $el['y'] ?? null;
            if ($x === null || $y === null || $x < 0 || $x > 1 || $y < 0 || $y > 1) {
                return response()->json([
                    'message' => "Element {$i}: x and y must be between 0 and 1",
                    'errors'  => ['card_config_json' => ["Invalid coordinates at index {$i}"]],
                ], 422);
            }
            if ($type === 'image') {
                if (!isset($el['width']) || !isset($el['height']) || $el['width'] < 0 || $el['width'] > 1 || $el['height'] < 0 || $el['height'] > 1) {
                    return response()->json([
                        'message' => "Element {$i}: width and height (0-1) are required for image elements",
                        'errors'  => ['card_config_json' => ["Invalid dimensions at index {$i}"]],
                    ], 422);
                }
            }
        }

        $template->update(['card_config_json' => ['elements' => $elements]]);

        return response()->json([
            'message'  => 'Card configuration updated successfully',
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
