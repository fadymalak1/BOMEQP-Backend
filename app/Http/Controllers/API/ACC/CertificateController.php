<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CertificateController extends Controller
{
    #[OA\Get(
        path: "/acc/certificates",
        summary: "List ACC certificates",
        description: "Get all certificates issued for courses belonging to the authenticated ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by trainee name, certificate number, verification code, course name, or training center name"),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["valid", "expired", "revoked"]), example: "valid"),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["instructor", "trainee"]), example: "trainee", description: "Filter by certificate type: instructor or trainee"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificates retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 50)
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

        $query = Certificate::whereHas('course', function ($q) use ($acc) {
            $q->where('acc_id', $acc->id);
        })->with(['course', 'trainingCenter', 'instructor']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by type (instructor or trainee)
        if ($request->has('type') && in_array($request->type, ['instructor', 'trainee'])) {
            $query->where('type', $request->type);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('trainee_name', 'like', "%{$searchTerm}%")
                    ->orWhere('certificate_number', 'like', "%{$searchTerm}%")
                    ->orWhere('verification_code', 'like', "%{$searchTerm}%")
                    ->orWhereHas('course', function ($courseQuery) use ($searchTerm) {
                        $courseQuery->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('trainingCenter', function ($tcQuery) use ($searchTerm) {
                        $tcQuery->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $certificates = $query->paginate($perPage);

        // Transform certificates data
        $transformedCertificates = $certificates->getCollection()->map(function ($certificate) {
            $data = $certificate->toArray();
            
            // Change trainee_name to name
            if (isset($data['trainee_name'])) {
                $data['name'] = $data['trainee_name'];
                unset($data['trainee_name']);
            }
            
            // Type is already stored in the database, use it directly
            // Ensure type is set (fallback to trainee for old records)
            if (!isset($data['type']) || empty($data['type'])) {
                $data['type'] = 'trainee';
            }
            
            return $data;
        });

        // Replace the collection in paginator
        $certificates->setCollection($transformedCertificates);

        return response()->json($certificates);
    }

    #[OA\Post(
        path: "/acc/certificates/generate",
        summary: "Generate certificate from template",
        description: "Generate a certificate PDF from a template with provided data. Supports template variables including training_center_logo, acc_logo, qr_code, expiry_date, etc.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["template_id"],
                properties: [
                    new OA\Property(property: "template_id", type: "integer", example: 123, description: "Certificate template ID"),
                    new OA\Property(
                        property: "data",
                        type: "object",
                        description: "Template variable values",
                        properties: [
                            new OA\Property(property: "instructor_name", type: "string", example: "John Doe"),
                            new OA\Property(property: "expiry_date", type: "string", format: "date", example: "2027-01-15"),
                            new OA\Property(property: "training_center_logo_url", type: "string", format: "uri", example: "https://example.com/logos/tc.png"),
                            new OA\Property(property: "acc_logo_url", type: "string", format: "uri", example: "https://example.com/logos/acc.png"),
                            new OA\Property(property: "qr_code_url", type: "string", format: "uri", example: "https://example.com/qrc/cert.png"),
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificate generated successfully. When the template includes a card, two PDFs are produced: pdf_url (certificate) and card_pdf_url (card).",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "certificate_id", type: "string", example: "CERT-001"),
                        new OA\Property(property: "pdf_url", type: "string", format: "uri", description: "URL to the certificate PDF"),
                        new OA\Property(property: "preview_url", type: "string", format: "uri", description: "Same as pdf_url (certificate)"),
                        new OA\Property(property: "card_pdf_url", type: "string", format: "uri", nullable: true, description: "URL to the card PDF when template has a card; omitted otherwise"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or template not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function generate(Request $request, CertificateGenerationService $certificateService)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $request->validate([
            'template_id' => 'required|exists:certificate_templates,id',
            'data' => 'sometimes|array',
        ]);

        $template = CertificateTemplate::where('acc_id', $acc->id)->findOrFail($request->template_id);
        $data = $request->input('data', []);

        $result = $certificateService->generate($template, $data, 'pdf');

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Certificate generation failed',
            ], 422);
        }

        $response = [
            'certificate_id' => 'CERT-' . \Illuminate\Support\Str::random(8),
            'pdf_url' => $result['file_url'] ?? null,
            'preview_url' => $result['file_url'] ?? null,
        ];
        if (!empty($result['card_file_url'])) {
            $response['card_pdf_url'] = $result['card_file_url'];
        }
        return response()->json($response);
    }
}

