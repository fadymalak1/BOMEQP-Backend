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
        $this->settings = StripeSetting::getActive();
        
        if ($this->settings && $this->settings->secret_key) {
            Stripe::setApiKey($this->settings->secret_key);
        }
    }

    /**
     * Check if Stripe is configured and active
     */
    public function isConfigured(): bool
    {
        return $this->settings !== null 
            && $this->settings->is_active 
            && !empty($this->settings->secret_key);
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
        return $this->settings?->publishable_key;
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (!$this->settings || !$this->settings->webhook_secret) {
            return false;
        }

        try {
            \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->settings->webhook_secret
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

