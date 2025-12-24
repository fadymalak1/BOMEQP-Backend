<?php

namespace App\Services;

use App\Models\StripeSetting;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected ?StripeSetting $settings;

    public function __construct()
    {
        // Try to get settings from database first
        $this->settings = StripeSetting::getActive();
        
        // If no database settings, try to use .env as fallback
        if (!$this->settings || empty($this->settings->secret_key)) {
            $envSecretKey = env('STRIPE_KEY');
            $envPublishableKey = env('STRIPE_PUBLISHABLE_KEY');
            $envWebhookSecret = env('STRIPE_WEBHOOK_SECRET');
            
            if ($envSecretKey) {
                Stripe::setApiKey($envSecretKey);
                // Create a virtual settings object from .env
                $this->settings = new \stdClass();
                $this->settings->secret_key = $envSecretKey;
                $this->settings->publishable_key = $envPublishableKey;
                $this->settings->webhook_secret = $envWebhookSecret;
                $this->settings->is_active = true;
            }
        } else {
            // Use database settings
            if ($this->settings->secret_key) {
                Stripe::setApiKey($this->settings->secret_key);
            }
        }
    }

    /**
     * Check if Stripe is configured and active
     */
    public function isConfigured(): bool
    {
        if ($this->settings === null) {
            // Fallback to .env
            return !empty(env('STRIPE_KEY'));
        }
        
        // Check if secret key exists
        $secretKey = $this->settings->secret_key ?? null;
        if (empty($secretKey)) {
            return false;
        }
        
        // If it's a database model, check is_active
        if ($this->settings instanceof \App\Models\StripeSetting) {
            return $this->settings->is_active === true;
        }
        
        // For .env object, assume active
        return true;
    }

    /**
     * Create a payment intent
     */
    public function createPaymentIntent(float $amount, string $currency = 'USD', array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not configured or not active');
        }

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent creation failed', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm a payment intent
     */
    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not configured or not active');
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'payment_intent_id' => $paymentIntent->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent confirmation failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve a payment intent
     */
    public function retrievePaymentIntent(string $paymentIntentId): ?PaymentIntent
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            return PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent retrieval failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);
            return null;
        }
    }

    /**
     * Verify payment intent - checks status, amount, and metadata
     */
    public function verifyPaymentIntent(string $paymentIntentId, float $expectedAmount, array $expectedMetadata = []): PaymentIntent
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not configured or not active');
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            // Verify status
            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment not completed. Status: ' . $paymentIntent->status);
            }

            // Verify amount (Stripe uses cents)
            $expectedAmountInCents = (int)($expectedAmount * 100);
            if ($paymentIntent->amount !== $expectedAmountInCents) {
                throw new \Exception('Payment amount mismatch. Expected: ' . $expectedAmount . ', Received: ' . ($paymentIntent->amount / 100));
            }

            // Verify metadata
            if (!empty($expectedMetadata)) {
                $paymentMetadata = (array)($paymentIntent->metadata->toArray() ?? []);
                foreach ($expectedMetadata as $key => $value) {
                    if (!isset($paymentMetadata[$key]) || (string)$paymentMetadata[$key] !== (string)$value) {
                        throw new \Exception("Metadata mismatch for key: {$key}. Expected: {$value}, Received: " . ($paymentMetadata[$key] ?? 'null'));
                    }
                }
            }

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent verification failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);
            throw new \Exception('Failed to verify payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Create a charge (legacy method)
     */
    public function createCharge(float $amount, string $currency = 'USD', string $source, array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not configured or not active');
        }

        try {
            $charge = Charge::create([
                'amount' => (int)($amount * 100),
                'currency' => strtolower($currency),
                'source' => $source,
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'charge_id' => $charge->id,
                'status' => $charge->status,
                'amount' => $amount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Charge creation failed', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentIntentId, ?float $amount = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not configured or not active');
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            $refundParams = [
                'payment_intent' => $paymentIntentId,
            ];

            if ($amount !== null) {
                $refundParams['amount'] = (int)($amount * 100);
            }

            $refund = \Stripe\Refund::create($refundParams);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get publishable key
     */
    public function getPublishableKey(): ?string
    {
        if (!$this->settings) {
            // Try .env as fallback
            return env('STRIPE_PUBLISHABLE_KEY');
        }
        
        return $this->settings->publishable_key ?? env('STRIPE_PUBLISHABLE_KEY');
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $webhookSecret = null;
        
        // Try to get webhook secret from settings (database or .env object)
        if ($this->settings && isset($this->settings->webhook_secret)) {
            $webhookSecret = $this->settings->webhook_secret;
        }
        
        // Fallback to .env
        if (!$webhookSecret) {
            $webhookSecret = env('STRIPE_WEBHOOK_SECRET');
        }

        if (!$webhookSecret) {
            Log::warning('Stripe webhook secret not configured');
            return false;
        }

        try {
            \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

