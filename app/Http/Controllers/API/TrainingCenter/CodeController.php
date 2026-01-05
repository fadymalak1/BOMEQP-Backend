<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\CertificateCode;
use App\Models\CodeBatch;
use App\Models\TrainingCenter;
use App\Services\CodePurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class CodeController extends Controller
{
    protected CodePurchaseService $codePurchaseService;

    public function __construct(CodePurchaseService $codePurchaseService)
    {
        $this->codePurchaseService = $codePurchaseService;
    }

    #[OA\Post(
        path: "/training-center/codes/create-payment-intent",
        summary: "Create payment intent for code purchase",
        description: "Create a Stripe payment intent for purchasing certificate codes. Calculates pricing including discounts. Also returns information about available payment methods including manual payment option.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["acc_id", "course_id", "quantity"],
                properties: [
                    new OA\Property(property: "acc_id", type: "integer", example: 1),
                    new OA\Property(property: "course_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 10, minimum: 1),
                    new OA\Property(property: "discount_code", type: "string", nullable: true, example: "DISCOUNT10")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment intent created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "client_secret", type: "string", example: "pi_xxx_secret_xxx"),
                        new OA\Property(property: "payment_intent_id", type: "string", example: "pi_xxx"),
                        new OA\Property(property: "amount", type: "number", example: 1000.00),
                        new OA\Property(property: "currency", type: "string", example: "USD"),
                        new OA\Property(property: "total_amount", type: "string", example: "1000.00"),
                        new OA\Property(property: "discount_amount", type: "string", nullable: true, example: "100.00"),
                        new OA\Property(property: "final_amount", type: "string", example: "900.00"),
                        new OA\Property(property: "unit_price", type: "string", example: "100.00"),
                        new OA\Property(property: "quantity", type: "integer", example: 10),
                        new OA\Property(property: "payment_methods_available", type: "array", items: new OA\Items(type: "string"), example: ["credit_card", "manual_payment"]),
                        new OA\Property(property: "manual_payment_info", type: "object", description: "Information about manual payment option")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid request or pricing not found"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center, ACC, or course not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to create payment intent")
        ]
    )]
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'acc_id' => 'required|integer|exists:accs,id',
            'course_id' => 'required|integer|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Validate purchase request
        $validationResult = $this->codePurchaseService->validatePurchaseRequest($request, $trainingCenter);
        if (!$validationResult['valid']) {
            return response()->json(['message' => $validationResult['message']], $validationResult['code']);
        }

        // Calculate price with discount
        $priceCalculation = $this->codePurchaseService->calculatePrice(
            $validationResult['pricing'],
            $request->quantity,
            $request->discount_code,
            $request->acc_id,
            $request->course_id
        );

        if (!$priceCalculation['success']) {
            return response()->json(['message' => $priceCalculation['message']], 422);
        }

        // Create payment intent
        $paymentIntentResult = $this->codePurchaseService->createPaymentIntent(
            $request,
            $trainingCenter,
            $validationResult,
            $priceCalculation
        );

        if (!$paymentIntentResult['success']) {
            return response()->json([
                'message' => $paymentIntentResult['message'],
                'error' => $paymentIntentResult['error'] ?? null,
                'error_code' => $paymentIntentResult['error_code'] ?? null
            ], $paymentIntentResult['code'] ?? 500);
        }

        return response()->json([
            'success' => true,
            'client_secret' => $paymentIntentResult['client_secret'],
            'payment_intent_id' => $paymentIntentResult['payment_intent_id'],
            'amount' => $paymentIntentResult['amount'],
            'currency' => $paymentIntentResult['currency'],
            'total_amount' => number_format($priceCalculation['total_amount'], 2, '.', ''),
            'discount_amount' => number_format($priceCalculation['discount_amount'], 2, '.', ''),
            'final_amount' => number_format($priceCalculation['final_amount'], 2, '.', ''),
            'unit_price' => number_format($priceCalculation['unit_price'], 2, '.', ''),
            'quantity' => $request->quantity,
            'commission_amount' => number_format($paymentIntentResult['commission_amount'], 2, '.', ''),
            'provider_amount' => $paymentIntentResult['provider_amount'] ? number_format($paymentIntentResult['provider_amount'], 2, '.', '') : null,
            'payment_type' => $paymentIntentResult['payment_type'],
            'payment_methods_available' => ['credit_card', 'manual_payment'],
            'manual_payment_info' => [
                'available' => true,
                'requires_receipt' => true,
                'receipt_formats' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_receipt_size_mb' => 10,
                'status_after_submission' => 'pending',
                'approval_required' => true,
            ],
        ], 200);
    }

    #[OA\Post(
        path: "/training-center/codes/purchase",
        summary: "Purchase certificate codes",
        description: "Purchase certificate codes after payment intent is confirmed. Generates codes and creates batch. Use multipart/form-data when uploading payment_receipt for manual payment. DO NOT manually set Content-Type header when using FormData - let the browser set it automatically.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: "multipart/form-data",
                    schema: new OA\Schema(
                        required: ["acc_id", "course_id", "quantity", "payment_method"],
                        properties: [
                            new OA\Property(property: "acc_id", type: "integer", example: 1),
                            new OA\Property(property: "course_id", type: "integer", example: 1),
                            new OA\Property(property: "quantity", type: "integer", example: 10, minimum: 1),
                            new OA\Property(property: "discount_code", type: "string", nullable: true, example: "DISCOUNT10"),
                            new OA\Property(property: "payment_method", type: "string", enum: ["credit_card", "manual_payment"], example: "credit_card"),
                            new OA\Property(property: "payment_intent_id", type: "string", nullable: true, example: "pi_xxx", description: "Required if payment_method is credit_card"),
                            new OA\Property(property: "payment_method_id", type: "string", nullable: true, example: "pm_xxx"),
                            new OA\Property(property: "payment_receipt", type: "string", format: "binary", nullable: true, description: "Required if payment_method is manual_payment. File must be PDF, JPG, JPEG, or PNG, max 10MB. IMPORTANT: When using FormData, do NOT manually set Content-Type header - let browser set it automatically."),
                            new OA\Property(property: "payment_amount", type: "number", nullable: true, example: 1000.00, description: "Required if payment_method is manual_payment"),
                        ]
                    )
                ),
                new OA\MediaType(
                    mediaType: "application/json",
                    schema: new OA\Schema(
                        required: ["acc_id", "course_id", "quantity", "payment_method"],
                        properties: [
                            new OA\Property(property: "acc_id", type: "integer", example: 1),
                            new OA\Property(property: "course_id", type: "integer", example: 1),
                            new OA\Property(property: "quantity", type: "integer", example: 10, minimum: 1),
                            new OA\Property(property: "discount_code", type: "string", nullable: true, example: "DISCOUNT10"),
                            new OA\Property(property: "payment_method", type: "string", enum: ["credit_card"], example: "credit_card", description: "Note: manual_payment requires multipart/form-data for file upload"),
                            new OA\Property(property: "payment_intent_id", type: "string", example: "pi_xxx", description: "Required if payment_method is credit_card"),
                            new OA\Property(property: "payment_method_id", type: "string", nullable: true, example: "pm_xxx"),
                        ]
                    )
                )
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Codes purchased successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Codes purchased successfully"),
                        new OA\Property(property: "batch", type: "object"),
                        new OA\Property(property: "codes", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "transaction", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Payment verification failed or invalid request"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "ACC not active or authorization required"),
            new OA\Response(response: 404, description: "Training center, ACC, course, or pricing not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function purchase(Request $request)
    {
        // Check Content-Type header for manual payment with file upload
        $contentType = $request->header('Content-Type', '');
        $isManualPayment = $request->input('payment_method') === 'manual_payment';
        
        if ($isManualPayment && $request->hasFile('payment_receipt')) {
            // Validate Content-Type is multipart/form-data
            if (!str_contains($contentType, 'multipart/form-data')) {
                Log::warning('Code purchase: Invalid Content-Type for file upload', [
                    'content_type' => $contentType,
                    'payment_method' => $request->input('payment_method'),
                    'has_file' => $request->hasFile('payment_receipt'),
                    'user_id' => $request->user()->id ?? null,
                ]);
                
                return response()->json([
                    'message' => 'Invalid Content-Type header. When uploading payment_receipt, you must use multipart/form-data. Do NOT manually set Content-Type header when using FormData - let the browser set it automatically.',
                    'error' => 'invalid_content_type',
                    'received_content_type' => $contentType,
                    'expected_content_type' => 'multipart/form-data',
                    'hint' => 'Remove the Content-Type header from your request when using FormData. The browser will automatically set it to multipart/form-data with the correct boundary.'
                ], 400);
            }
        }

        $request->validate([
            'acc_id' => 'required|integer|exists:accs,id',
            'course_id' => 'required|integer|exists:courses,id',
            'quantity' => 'required|integer|min:1',
            'discount_code' => 'nullable|string|max:255',
            'payment_method' => 'required|in:credit_card,manual_payment',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string|max:255',
            'payment_method_id' => 'nullable|string|max:255',
            'payment_receipt' => 'required_if:payment_method,manual_payment|nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'payment_amount' => 'required_if:payment_method,manual_payment|nullable|numeric|min:0',
        ], [
            'payment_receipt.required_if' => 'Payment receipt is required for manual payment. Please ensure you are sending the file as multipart/form-data. Do NOT manually set Content-Type header when using FormData.',
            'payment_receipt.file' => 'Payment receipt must be a valid file upload. Ensure you are using multipart/form-data and not manually setting Content-Type header.',
            'payment_receipt.mimes' => 'Payment receipt must be a PDF, JPG, JPEG, or PNG file.',
            'payment_receipt.max' => 'Payment receipt file size must not exceed 10MB.',
            'payment_amount.required_if' => 'Payment amount is required for manual payment.',
            'payment_amount.numeric' => 'Payment amount must be a valid number.',
            'payment_amount.min' => 'Payment amount must be greater than 0.',
        ]);

        $user = $request->user();
        $trainingCenter = TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Validate purchase request
        $validationResult = $this->codePurchaseService->validatePurchaseRequest($request, $trainingCenter);
        if (!$validationResult['valid']) {
            return response()->json(['message' => $validationResult['message']], $validationResult['code']);
        }

        // Calculate price with discount
        $priceCalculation = $this->codePurchaseService->calculatePrice(
            $validationResult['pricing'],
            $request->quantity,
            $request->discount_code,
            $request->acc_id,
            $request->course_id
        );

        if (!$priceCalculation['success']) {
            return response()->json(['message' => $priceCalculation['message']], 422);
        }

        // Process purchase using service
        try {
            $result = $this->codePurchaseService->processPurchase(
                $request,
                $trainingCenter,
                $validationResult,
                $priceCalculation
            );

            $paymentStatus = $result['payment_status'] ?? 'completed';
            $batch = $result['batch'];
            $codes = $result['codes'] ?? [];
            $transaction = $result['transaction'];

            // Format response
            $response = [
                'message' => $paymentStatus === 'completed'
                    ? 'Codes purchased successfully'
                    : 'Payment request submitted successfully. Waiting for approval.',
                'batch' => [
                    'id' => $batch->id,
                    'training_center_id' => $batch->training_center_id,
                    'acc_id' => $batch->acc_id,
                    'course_id' => $batch->course_id,
                    'quantity' => $batch->quantity,
                    'total_amount' => number_format($priceCalculation['total_amount'], 2, '.', ''),
                    'discount_amount' => number_format($priceCalculation['discount_amount'], 2, '.', ''),
                    'final_amount' => number_format($priceCalculation['final_amount'], 2, '.', ''),
                    'payment_method' => $batch->payment_method,
                    'payment_status' => $batch->payment_status,
                    'created_at' => $batch->created_at->toIso8601String(),
                ],
            ];

            if ($paymentStatus === 'completed' && !empty($codes)) {
                $response['codes'] = array_map(function($code) {
                    return [
                        'id' => $code->id ?? null,
                        'code' => is_string($code) ? $code : $code->code,
                        'status' => is_string($code) ? 'available' : ($code->status ?? 'available'),
                    ];
                }, $codes);
            }

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Code purchase failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'training_center_id' => $trainingCenter->id ?? null,
            ]);

            $errorMessage = $e->getMessage();
            $errorCode = 'internal_server_error';
            $statusCode = 500;

            // Handle specific error types
            if (strpos($errorMessage, 'Payment') !== false || strpos($errorMessage, 'payment') !== false) {
                $statusCode = 400;
                $errorCode = 'payment_error';
            } elseif (strpos($errorMessage, 'Invalid') !== false || strpos($errorMessage, 'validation') !== false) {
                $statusCode = 422;
                $errorCode = 'validation_error';
            }

            return response()->json([
                'message' => $statusCode === 422 ? $errorMessage : 'Purchase failed. Please try again.',
                'error' => config('app.debug') ? $errorMessage : ($statusCode === 422 ? $errorMessage : 'Internal server error'),
                'error_code' => $errorCode
            ], $statusCode);
        }
    }

    #[OA\Get(
        path: "/training-center/codes/inventory",
        summary: "Get certificate codes inventory",
        description: "Get all certificate codes owned by the training center with optional filtering.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "course_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["available", "used", "expired"]), example: "available"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Inventory retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "codes", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function inventory(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $query = CertificateCode::where('training_center_id', $trainingCenter->id)
            ->with('course');

        if ($request->has('acc_id')) {
            $query->where('acc_id', $request->acc_id);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $codes = $query->get();

        $summary = [
            'total' => CertificateCode::where('training_center_id', $trainingCenter->id)->count(),
            'available' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'available')->count(),
            'used' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'used')->count(),
            'expired' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'expired')->count(),
        ];

        return response()->json([
            'codes' => $codes,
            'summary' => $summary,
        ]);
    }

    #[OA\Get(
        path: "/training-center/codes/batches",
        summary: "Get code purchase batches",
        description: "Get all code purchase batches for the training center.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "acc_id", in: "query", schema: new OA\Schema(type: "integer"), example: 1),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Batches retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "batches", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function batches(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        $batches = CodeBatch::where('training_center_id', $trainingCenter->id)
            ->with('certificateCodes')
            ->orderBy('purchase_date', 'desc')
            ->get();

        return response()->json(['batches' => $batches]);
    }
}

