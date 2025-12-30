<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SubscriptionController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
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
                        new OA\Property(property: "subscription", type: "object", nullable: true)
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

        return response()->json(['subscription' => $subscription]);
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

        // Verify Stripe payment intent if credit card payment
        if ($request->payment_method === 'credit_card') {
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $request->amount,
                    [
                        'acc_id' => (string)$acc->id,
                        'type' => 'subscription',
                    ]
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1, // Group ID
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Check if ACC is suspended due to expired subscription
            if ($acc->status === 'suspended') {
                // Reactivate ACC account
                $acc->update(['status' => 'active']);
            }

            // Create or update subscription
            $subscription = ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => now(),
                'subscription_end_date' => now()->addYear(), // Default 1 year subscription
                'renewal_date' => now()->addYear(),
                'amount' => $request->amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
            ]);

            // Also activate the user account associated with this ACC
            $user = \App\Models\User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin' && $user->status !== 'active') {
                $user->update(['status' => 'active']);
            }

            DB::commit();

            // Send notifications
            $notificationService = new NotificationService();
            $notificationService->notifySubscriptionPaid($user->id, $subscription->id, $request->amount);
            
            // Notify Admin
            $notificationService->notifyAdminSubscriptionPaid($acc->id, $acc->name, $request->amount, false);

            return response()->json([
                'message' => 'Payment successful',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Payment failed: ' . $e->getMessage()], 500);
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

        // Check if subscription is expired
        if ($currentSubscription->subscription_end_date < now() && $acc->status === 'suspended') {
            return response()->json([
                'message' => 'Subscription expired. Account is suspended. Please renew to reactivate.',
                'requires_payment' => true
            ], 400);
        }

        // Verify Stripe payment intent if credit card payment
        if ($request->payment_method === 'credit_card') {
            if (!$request->payment_intent_id) {
                return response()->json([
                    'message' => 'payment_intent_id is required for credit card payments'
                ], 400);
            }

            try {
                $this->stripeService->verifyPaymentIntent(
                    $request->payment_intent_id,
                    $request->amount,
                    [
                        'acc_id' => (string)$acc->id,
                        'type' => 'subscription_renewal',
                        'subscription_id' => (string)$currentSubscription->id,
                    ]
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage()
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Create transaction for renewal
            $transaction = Transaction::create([
                'transaction_type' => 'subscription',
                'payer_type' => 'acc',
                'payer_id' => $acc->id,
                'payee_type' => 'group',
                'payee_id' => 1,
                'amount' => $request->amount,
                'currency' => 'USD',
                'payment_method' => $request->payment_method,
                'payment_gateway_transaction_id' => $request->payment_intent_id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Calculate new dates
            $startDate = $currentSubscription->subscription_end_date > now() 
                ? $currentSubscription->subscription_end_date 
                : now();
            $endDate = $startDate->copy()->addYear();

            // Create new subscription record
            $newSubscription = ACCSubscription::create([
                'acc_id' => $acc->id,
                'subscription_start_date' => $startDate,
                'subscription_end_date' => $endDate,
                'renewal_date' => $endDate,
                'amount' => $request->amount,
                'payment_status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'transaction_id' => $transaction->id,
                'auto_renew' => $request->auto_renew ?? false,
            ]);

            // Reactivate ACC if suspended
            if ($acc->status === 'suspended') {
                $acc->update(['status' => 'active']);
                
                // Also activate the user account
                $userModel = \App\Models\User::where('email', $acc->email)->first();
                if ($userModel && $userModel->role === 'acc_admin') {
                    $userModel->update(['status' => 'active']);
                }
            }

            DB::commit();

            // Send notifications
            $userModel = \App\Models\User::where('email', $acc->email)->first();
            if ($userModel) {
                $notificationService = new NotificationService();
                $notificationService->notifySubscriptionPaid($userModel->id, $newSubscription->id, $request->amount);
                
                // Notify Admin
                $notificationService->notifyAdminSubscriptionPaid($acc->id, $acc->name, $request->amount, true);
            }

            return response()->json([
                'message' => 'Subscription renewed successfully',
                'subscription' => $newSubscription,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Renewal failed: ' . $e->getMessage()], 500);
        }
    }
}

