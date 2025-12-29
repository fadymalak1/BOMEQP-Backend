<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CertificateController extends Controller
{
    #[OA\Get(
        path: "/api/certificates/verify/{code}",
        summary: "Verify certificate",
        description: "Verify a certificate using its verification code. This is a public endpoint.",
        tags: ["Certificates"],
        parameters: [
            new OA\Parameter(
                name: "code",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string"),
                example: "VERIFY-ABC123"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificate verified successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "certificate", type: "object", properties: [
                            new OA\Property(property: "certificate_number", type: "string", example: "CERT-2024-001"),
                            new OA\Property(property: "trainee_name", type: "string", example: "John Doe"),
                            new OA\Property(property: "course", type: "string", example: "Fire Safety Training"),
                            new OA\Property(property: "issue_date", type: "string", format: "date", example: "2024-01-15"),
                            new OA\Property(property: "expiry_date", type: "string", format: "date", example: "2026-01-15"),
                            new OA\Property(property: "status", type: "string", example: "valid"),
                            new OA\Property(property: "training_center", type: "string", example: "ABC Training Center")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Certificate revoked or expired"),
            new OA\Response(response: 404, description: "Certificate not found")
        ]
    )]
    public function verify($code)
    {
        $certificate = Certificate::where('verification_code', $code)->first();

        if (!$certificate) {
            return response()->json(['message' => 'Certificate not found'], 404);
        }

        if ($certificate->status === 'revoked') {
            return response()->json(['message' => 'Certificate has been revoked'], 400);
        }

        if ($certificate->status === 'expired') {
            return response()->json(['message' => 'Certificate has expired'], 400);
        }

        return response()->json([
            'certificate' => [
                'certificate_number' => $certificate->certificate_number,
                'trainee_name' => $certificate->trainee_name,
                'course' => $certificate->course->name,
                'issue_date' => $certificate->issue_date,
                'expiry_date' => $certificate->expiry_date,
                'status' => $certificate->status,
                'training_center' => $certificate->trainingCenter->name,
            ],
        ]);
    }
}

