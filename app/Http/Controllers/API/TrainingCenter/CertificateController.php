<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateCode;
use App\Models\TrainingClass;
use App\Models\ClassCompletion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CertificateController extends Controller
{
    #[OA\Post(
        path: "/training-center/certificates/generate",
        summary: "Generate certificate",
        description: "Generate a certificate for a trainee using a certificate code. Class must be completed first.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["training_class_id", "code_id", "trainee_name"],
                properties: [
                    new OA\Property(property: "training_class_id", type: "integer", example: 1),
                    new OA\Property(property: "code_id", type: "integer", example: 1),
                    new OA\Property(property: "trainee_name", type: "string", example: "John Doe"),
                    new OA\Property(property: "trainee_id_number", type: "string", nullable: true, example: "ID123456"),
                    new OA\Property(property: "issue_date", type: "string", format: "date", nullable: true, example: "2024-01-15"),
                    new OA\Property(property: "expiry_date", type: "string", format: "date", nullable: true, example: "2025-01-15")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Certificate generated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Certificate generated successfully"),
                        new OA\Property(property: "certificate", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Class must be completed first or invalid request"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center, class, code, or template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function generate(Request $request)
    {
        $request->validate([
            'training_class_id' => 'required|exists:training_classes,id',
            'code_id' => 'required|exists:certificate_codes,id',
            'trainee_name' => 'required|string|max:255',
            'trainee_id_number' => 'nullable|string',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
        ]);

        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $trainingClass = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->findOrFail($request->training_class_id);

        // Check if class is completed
        $completion = ClassCompletion::where('training_class_id', $trainingClass->id)->first();
        if (!$completion) {
            return response()->json(['message' => 'Class must be completed before generating certificates'], 400);
        }

        // Get and validate code
        $code = CertificateCode::where('training_center_id', $trainingCenter->id)
            ->where('id', $request->code_id)
            ->where('status', 'available')
            ->firstOrFail();

        // Get certificate template
        $template = \App\Models\CertificateTemplate::where('acc_id', $code->acc_id)
            ->where('category_id', $trainingClass->course->subCategory->category_id)
            ->where('status', 'active')
            ->first();

        if (!$template) {
            return response()->json(['message' => 'Certificate template not found'], 404);
        }

        // Generate certificate
        $certificate = Certificate::create([
            'certificate_number' => 'CERT-' . strtoupper(Str::random(10)),
            'course_id' => $trainingClass->course_id,
            'class_id' => $trainingClass->class_id,
            'training_center_id' => $trainingCenter->id,
            'instructor_id' => $trainingClass->instructor_id,
            'trainee_name' => $request->trainee_name,
            'trainee_id_number' => $request->trainee_id_number,
            'issue_date' => $request->issue_date ?? now(),
            'expiry_date' => $request->expiry_date,
            'template_id' => $template->id,
            'certificate_pdf_url' => '/certificates/' . Str::random(20) . '.pdf', // TODO: Generate actual PDF
            'verification_code' => strtoupper(Str::random(12)),
            'status' => 'valid',
            'code_used_id' => $code->id,
        ]);

        // Update code status
        $code->update([
            'status' => 'used',
            'used_at' => now(),
            'used_for_certificate_id' => $certificate->id,
        ]);

        // Update completion count
        $completion->increment('certificates_generated_count');

        // Send notifications
        $notificationService = new \App\Services\NotificationService();
        $certificate->load(['course', 'instructor', 'trainingCenter']);
        $course = $certificate->course;
        $instructor = $certificate->instructor;
        $acc = $course->acc ?? null;

        // Notify ACC Admin
        if ($acc) {
            $accUser = \App\Models\User::where('email', $acc->email)->where('role', 'acc_admin')->first();
            if ($accUser) {
                $notificationService->notifyCertificateGenerated(
                    $accUser->id,
                    $certificate->id,
                    $certificate->certificate_number,
                    $certificate->trainee_name,
                    $course->name,
                    $trainingCenter->name
                );
            }
        }

        // Notify Instructor
        if ($instructor) {
            $instructorUser = \App\Models\User::where('email', $instructor->email)->first();
            if ($instructorUser) {
                $notificationService->notifyInstructorCertificateGenerated(
                    $instructorUser->id,
                    $certificate->id,
                    $certificate->certificate_number,
                    $certificate->trainee_name,
                    $course->name,
                    $trainingCenter->name
                );
            }
        }

        // Notify Group Admin
        if ($acc) {
            $notificationService->notifyAdminCertificateGenerated(
                $certificate->id,
                $certificate->certificate_number,
                $certificate->trainee_name,
                $course->name,
                $trainingCenter->name,
                $acc->name
            );
        }

        // TODO: Generate PDF and store it
        // TODO: Send certificate to trainee

        return response()->json([
            'message' => 'Certificate generated successfully',
            'certificate' => $certificate,
        ], 201);
    }

    #[OA\Get(
        path: "/training-center/certificates",
        summary: "List certificates",
        description: "Get all certificates generated by the training center with optional filtering.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["valid", "expired", "revoked"]), example: "valid"),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificates retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "certificates", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = Certificate::where('training_center_id', $trainingCenter->id)
            ->with(['course', 'instructor', 'template']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $certificates = $query->paginate($perPage);

        return response()->json($certificates);
    }

    #[OA\Get(
        path: "/training-center/certificates/{id}",
        summary: "Get certificate details",
        description: "Get detailed information about a specific certificate.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificate retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "certificate", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Certificate not found")
        ]
    )]
    public function show($id)
    {
        $certificate = Certificate::with(['course', 'instructor', 'trainingCenter', 'template'])
            ->findOrFail($id);
        return response()->json(['certificate' => $certificate]);
    }
}

