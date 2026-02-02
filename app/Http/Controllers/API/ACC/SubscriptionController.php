<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class SubscriptionController extends Controller
{
    protected StripeService $stripeService;
    protected SubscriptionService $subscriptionService;

    public function __construct(StripeService $stripeService, SubscriptionService $subscriptionService)
    {
        $this->stripeService = $stripeService;
        $this->subscriptionService = $subscriptionService;
    }

    #[OA\Post(
        path: "/acc/subscription/create-payment-intent",
        summary: "Create subscription payment intent",
        description: "Create a Stripe payment intent for ACC subscription payment.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 1000.00, minimum: 0)
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
                        new OA\Property(property: "currency", type: "string", example: "USD")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Stripe not configured"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to create payment intent")
        ]
    )]
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
        }

        try {
            $result = $this->stripeService->createPaymentIntent(
                $request->amount,
                'USD',
                [
                    'acc_id' => (string)$acc->id,
                    'user_id' => (string)$user->id,
                    'type' => 'subscription',
                    'amount' => (string)$request->amount,
                ]
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Post(
        path: "/acc/subscription/create-renewal-payment-intent",
        summary: "Create subscription renewal payment intent",
        description: "Create a Stripe payment intent for ACC subscription renewal.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 1000.00, minimum: 0)
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
                        new OA\Property(property: "currency", type: "string", example: "USD")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Stripe not configured"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or subscription not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to create payment intent")
        ]
    )]
    public function createRenewalPaymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $currentSubscription = $acc->subscriptions()->latest()->first();
        if (!$currentSubscription) {
            return response()->json(['message' => 'No subscription found. Please create a new subscription.'], 404);
        }

        // Check if current subscription has ended
        if ($currentSubscription->subscription_end_date > now()) {
            return response()->json([
                'message' => 'Cannot create renewal payment intent. Current subscription is still active and ends on ' . $currentSubscription->subscription_end_date->format('Y-m-d') . '. Please wait until the subscription ends before renewing.',
                'subscription_end_date' => $currentSubscription->subscription_end_date->format('Y-m-d'),
            ], 400);
        }

        if (!$this->stripeService->isConfigured()) {
            return response()->json([
                'message' => 'Stripe payment is not configured'
            ], 400);
        }

        try {
            $result = $this->stripeService->createPaymentIntent(
                $request->amount,
                'USD',
                [
                    'acc_id' => (string)$acc->id,
                    'user_id' => (string)$user->id,
                    'type' => 'subscription_renewal',
                    'amount' => (string)$request->amount,
                    'subscription_id' => (string)$currentSubscription->id,
                ]
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'client_secret' => $result['client_secret'],
                'payment_intent_id' => $result['payment_intent_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: "/acc/subscription",
        summary: "Get ACC subscription",
        description: "Get the current subscription for the authenticated ACC.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Subscription retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "subscription", type: "object", nullable: true),
                        new OA\Property(property: "subscription_price", type: "number", format: "float", nullable: true, example: 1000.00, description: "Subscription price set for this ACC")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function show(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subscription = $acc->subscriptions()->latest()->first();

        return response()->json([
            'subscription' => $subscription,
            'subscription_price' => $acc->subscription_price,
        ]);
    }

    #[OA\Post(
        path: "/acc/subscription/payment",
        summary: "Process subscription payment",
        description: "Process subscription payment after payment intent is confirmed.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount", "payment_method"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 1000.00, minimum: 0),
                    new OA\Property(property: "payment_method", type: "string", enum: ["credit_card"], example: "credit_card"),
                    new OA\Property(property: "payment_intent_id", type: "string", nullable: true, example: "pi_xxx", description: "Required for credit_card payment method")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Subscription payment processed successfully"),
                        new OA\Property(property: "subscription", type: "object"),
                        new OA\Property(property: "transaction", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Payment verification failed or invalid request"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function payment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:credit_card',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        try {
            $result = $this->subscriptionService->processPayment(
                $acc,
                $request->amount,
                $request->payment_method,
                $request->payment_intent_id
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null
                ], $result['code'] ?? 400);
            }

            return response()->json([
                'message' => $result['message'],
                'subscription' => $result['subscription'],
                'transaction' => $result['transaction'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to process subscription payment', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Payment failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Post(
        path: "/acc/subscription/renew",
        summary: "Renew subscription",
        description: "Renew ACC subscription. Payment intent must be verified before calling this endpoint.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["amount", "payment_method"],
                properties: [
                    new OA\Property(property: "amount", type: "number", format: "float", example: 1000.00, minimum: 0),
                    new OA\Property(property: "payment_method", type: "string", enum: ["credit_card"], example: "credit_card"),
                    new OA\Property(property: "payment_intent_id", type: "string", nullable: true, example: "pi_xxx", description: "Required for credit_card payment method"),
                    new OA\Property(property: "auto_renew", type: "boolean", nullable: true, example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Subscription renewed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Subscription renewed successfully"),
                        new OA\Property(property: "subscription", type: "object"),
                        new OA\Property(property: "transaction", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Subscription expired or payment verification failed"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC or subscription not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function renew(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:credit_card',
            'payment_intent_id' => 'required_if:payment_method,credit_card|nullable|string',
            'auto_renew' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $currentSubscription = $acc->subscriptions()->latest()->first();

        if (!$currentSubscription) {
            return response()->json(['message' => 'No subscription found. Please create a new subscription.'], 404);
        }

        // Check if current subscription has ended
        if ($currentSubscription->subscription_end_date > now()) {
            return response()->json([
                'message' => 'Cannot renew subscription. Current subscription is still active and ends on ' . $currentSubscription->subscription_end_date->format('Y-m-d') . '. Please wait until the subscription ends before renewing.',
                'subscription_end_date' => $currentSubscription->subscription_end_date->format('Y-m-d'),
            ], 400);
        }

        try {
            $result = $this->subscriptionService->renewSubscription(
                $acc,
                $currentSubscription,
                $request->amount,
                $request->payment_method,
                $request->payment_intent_id,
                $request->auto_renew
            );

            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message'],
                    'requires_payment' => $result['requires_payment'] ?? false,
                    'error' => $result['error'] ?? null
                ], $result['code'] ?? 400);
            }

            return response()->json([
                'message' => $result['message'],
                'subscription' => $result['subscription'],
                'transaction' => $result['transaction'],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to renew subscription', [
                'acc_id' => $acc->id,
                'subscription_id' => $currentSubscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Renewal failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

