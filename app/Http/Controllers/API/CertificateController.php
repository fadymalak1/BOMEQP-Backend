<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
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

