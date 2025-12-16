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

class CertificateController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'training_class_id' => 'required|exists:training_classes,id',
            'code_id' => 'required|exists:certificate_codes,id',
            'trainee_name' => 'required|string|max:255',
            'trainee_id_number' => 'nullable|string',
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
            'issue_date' => now(),
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

        // TODO: Generate PDF and store it
        // TODO: Send certificate to trainee

        return response()->json([
            'message' => 'Certificate generated successfully',
            'certificate' => $certificate,
        ], 201);
    }

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

    public function show($id)
    {
        $certificate = Certificate::with(['course', 'instructor', 'trainingCenter', 'template'])
            ->findOrFail($id);
        return response()->json(['certificate' => $certificate]);
    }
}

