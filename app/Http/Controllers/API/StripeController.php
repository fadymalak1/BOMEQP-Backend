<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\StripeService;
use App\Services\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class StripeController extends Controller
{
    protected StripeService $stripeService;
    protected StripeWebhookService $webhookService;

    public function __construct(StripeService $stripeService, StripeWebhookService $webhookService)
    {
        $this->stripeService = $stripeService;
        $this->webhookService = $webhookService;
    }

    #[OA\Get(
        path: "/stripe/config",
        summary: "Get Stripe configuration",
        description: "Get Stripe publishable key and configuration status.",
        tags: ["Stripe"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Configuration retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "publishable_key", type: "string", example: "pk_test_xxx"),
                        new OA\Property(property: "is_configured", type: "boolean", example: true)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Stripe is not configured")
        ]
    )]
    public function getConfig()
    {
        $publishableKey = $this->stripeService->getPublishableKey();

        if (!$publishableKey) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not configured',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'publishable_key' => $publishableKey,
            'is_configured' => $this->stripeService->isConfigured(),
        ]);
    }

    #[OA\Post(
        path: "/stripe/create-payment-intent",
        summary: "Create payment intent",
        description: "Create a Stripe payment intent for a transaction.",
        tags: ["Stripe"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount", "currency", "transaction_type", "payer_type", "payer_id", "payee_type", "payee_id"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 100.00, minimum: 0.01),
                    new OA\Property(property: "currency", type: "string", example: "USD", minLength: 3, maxLength: 3),
                    new OA\Property(property: "transaction_type", type: "string", example: "subscription"),
                    new OA\Property(property: "payer_type", type: "string", example: "acc"),
                    new OA\Property(property: "payer_id", type: "integer", example: 1),
                    new OA\Property(property: "payee_type", type: "string", example: "group"),
                    new OA\Property(property: "payee_id", type: "integer", example: 1),
                    new OA\Property(property: "description", type: "string", nullable: true),
                    new OA\Property(property: "reference_id", type: "integer", nullable: true),
                    new OA\Property(property: "reference_type", type: "string", nullable: true)
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
                        new OA\Property(property: "amount", type: "number", example: 100.00),
                        new OA\Property(property: "currency", type: "string", example: "USD")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to create payment intent")
        ]
    )]
    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'transaction_type' => 'required|string',
            'payer_type' => 'required|string',
            'payer_id' => 'required|numeric',
            'payee_type' => 'required|string',
            'payee_id' => 'required|numeric',
            'description' => 'nullable|string',
            'reference_id' => 'nullable|numeric',
            'reference_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Convert numeric strings to integers for IDs
        $payerId = (int)$request->payer_id;
        $payeeId = (int)$request->payee_id;

        $metadata = [
            'transaction_type' => $request->transaction_type,
            'payer_type' => $request->payer_type,
            'payer_id' => (string)$payerId,
            'payee_type' => $request->payee_type,
            'payee_id' => (string)$payeeId,
        ];

        if ($request->reference_id) {
            $metadata['reference_id'] = (string)((int)$request->reference_id);
            $metadata['reference_type'] = $request->reference_type;
        }

        $result = $this->stripeService->createPaymentIntent(
            $request->amount,
            $request->currency,
            $metadata
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        // Create pending transaction
        $transaction = Transaction::create([
            'transaction_type' => $request->transaction_type,
            'payer_type' => $request->payer_type,
            'payer_id' => $payerId,
            'payee_type' => $request->payee_type,
            'payee_id' => $payeeId,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_method' => 'credit_card',
            'payment_gateway_transaction_id' => $result['payment_intent_id'],
            'status' => 'pending',
            'description' => $request->description,
            'reference_id' => $request->reference_id ? (int)$request->reference_id : null,
            'reference_type' => $request->reference_type,
        ]);

        return response()->json([
            'success' => true,
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['payment_intent_id'],
            'transaction_id' => $transaction->id,
            'amount' => $result['amount'],
            'currency' => $result['currency'],
        ]);
    }

    #[OA\Post(
        path: "/stripe/confirm-payment",
        summary: "Confirm payment",
        description: "Confirm a Stripe payment intent. Can be called manually or via webhook.",
        tags: ["Stripe"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_intent_id"],
                properties: [
                    new OA\Property(property: "payment_intent_id", type: "string", example: "pi_xxx"),
                    new OA\Property(property: "transaction_id", type: "integer", nullable: true, example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment confirmed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "status", type: "string", example: "succeeded"),
                        new OA\Property(property: "transaction", type: "object", nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to confirm payment")
        ]
    )]
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'transaction_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->stripeService->confirmPaymentIntent($request->payment_intent_id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        // Update transaction if transaction_id is provided
        if ($request->transaction_id) {
            $transaction = Transaction::find($request->transaction_id);
            if ($transaction) {
                $transaction->update([
                    'status' => $result['status'] === 'succeeded' ? 'completed' : 'failed',
                    'completed_at' => $result['status'] === 'succeeded' ? now() : null,
                ]);
            }
        } else {
            // Try to find transaction by payment_intent_id
            $transaction = Transaction::where('payment_gateway_transaction_id', $request->payment_intent_id)->first();
            if ($transaction) {
                $transaction->update([
                    'status' => $result['status'] === 'succeeded' ? 'completed' : 'failed',
                    'completed_at' => $result['status'] === 'succeeded' ? now() : null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'status' => $result['status'],
            'transaction' => $transaction ?? null,
        ]);
    }

    #[OA\Post(
        path: "/stripe/webhook",
        summary: "Handle Stripe webhook",
        description: "Handle Stripe webhook events for payment status updates. This endpoint is called by Stripe.",
        tags: ["Stripe"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                description: "Stripe webhook event payload"
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Webhook processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "received", type: "boolean", example: true)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Invalid signature or event data"),
            new OA\Response(response: 500, description: "Webhook processing failed")
        ]
    )]
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$this->stripeService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Stripe webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            $event = json_decode($payload, true);

            if (!isset($event['type'])) {
                Log::warning('Stripe webhook received without event type');
                return response()->json(['error' => 'Invalid event data'], 400);
            }

            Log::info('Stripe webhook received', [
                'event_type' => $event['type'],
                'event_id' => $event['id'] ?? null,
            ]);

            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->webhookService->handlePaymentSucceeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->webhookService->handlePaymentFailed($event['data']['object']);
                    break;

                case 'payment_intent.canceled':
                    $this->webhookService->handlePaymentCanceled($event['data']['object']);
                    break;

                case 'charge.refunded':
                    $this->webhookService->handleRefund($event['data']['object']);
                    break;

                case 'charge.dispute.created':
                    $this->webhookService->handleDisputeCreated($event['data']['object']);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['event_type' => $event['type']]);
            }

            return response()->json(['received' => true]);
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }


    #[OA\Post(
        path: "/stripe/refund",
        summary: "Refund payment",
        description: "Process a refund for a Stripe payment. Can refund full or partial amount.",
        tags: ["Stripe"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_intent_id"],
                properties: [
                    new OA\Property(property: "payment_intent_id", type: "string", example: "pi_xxx"),
                    new OA\Property(property: "amount", type: "number", format: "float", nullable: true, example: 50.00, minimum: 0.01, description: "Partial refund amount. If not provided, full refund will be processed.")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Refund processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "refund_id", type: "string", example: "re_xxx"),
                        new OA\Property(property: "amount", type: "number", example: 50.00),
                        new OA\Property(property: "status", type: "string", example: "succeeded"),
                        new OA\Property(property: "transaction", type: "object", nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to process refund")
        ]
    )]
    public function refund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->stripeService->refundPayment(
            $request->payment_intent_id,
            $request->amount
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        }

        // Update transaction status
        $transaction = Transaction::where('payment_gateway_transaction_id', $request->payment_intent_id)->first();
        if ($transaction) {
            $transaction->update([
                'status' => 'refunded',
            ]);
        }

        return response()->json([
            'success' => true,
            'refund_id' => $result['refund_id'],
            'amount' => $result['amount'],
            'status' => $result['status'],
        ]);
    }
}

