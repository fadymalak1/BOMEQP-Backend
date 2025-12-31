<?php

namespace App\Services;

use App\Models\StripeSetting;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Stripe\Account;
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
     * Verify Stripe Connect account exists and is valid
     * 
     * @param string $accountId Stripe Connect account ID
     * @return array
     */
    public function verifyStripeAccount(string $accountId): array
    {
        if (!$this->isConfigured()) {
            return [
                'valid' => false,
                'error' => 'Stripe is not configured',
            ];
        }

        if (empty($accountId)) {
            return [
                'valid' => false,
                'error' => 'Account ID is required',
            ];
        }

        try {
            // Retrieve the account to verify it exists
            $account = Account::retrieve($accountId);
            
            // Check if account is active/valid
            $isValid = $account && !empty($account->id);
            
            return [
                'valid' => $isValid,
                'account' => $isValid ? [
                    'id' => $account->id,
                    'type' => $account->type ?? 'unknown',
                    'charges_enabled' => $account->charges_enabled ?? false,
                    'payouts_enabled' => $account->payouts_enabled ?? false,
                    'details_submitted' => $account->details_submitted ?? false,
                ] : null,
            ];
        } catch (ApiErrorException $e) {
            Log::warning('Stripe account verification failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a payment intent with destination charges (split payment)
     * Money goes to provider account, admin commission is automatically deducted
     * 
     * @param float $amount Total amount in currency units (e.g., 1000.00 EGP)
     * @param string $providerStripeAccountId Stripe Connect account ID of the provider
     * @param float $commissionAmount Commission amount for admin/platform (e.g., 100.00 EGP)
     * @param string $currency Currency code (default: 'egp')
     * @param array $metadata Additional metadata
     * @return array
     */
    public function createDestinationChargePaymentIntent(
        float $amount,
        string $providerStripeAccountId,
        float $commissionAmount,
        string $currency = 'egp',
        array $metadata = []
    ): array {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not configured or not active');
        }

        if (empty($providerStripeAccountId)) {
            throw new \Exception('Provider Stripe account ID is required');
        }

        // Verify the Stripe account exists before creating payment intent
        $verification = $this->verifyStripeAccount($providerStripeAccountId);
        if (!$verification['valid']) {
            $errorMessage = $verification['error'] ?? 'Invalid Stripe account';
            
            // Provide more helpful error message
            if (strpos($errorMessage, 'No such account') !== false || 
                strpos($errorMessage, 'No such destination') !== false) {
                $errorMessage = "Stripe account '{$providerStripeAccountId}' not found or not connected. Please verify the account ID is correct and the account is properly connected to the platform.";
            }
            
            Log::error('Stripe account verification failed before payment intent creation', [
                'account_id' => $providerStripeAccountId,
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => 'invalid_stripe_account',
            ];
        }

        try {
            $amountInCents = (int)($amount * 100);
            $commissionInCents = (int)($commissionAmount * 100);

            // Validate commission doesn't exceed amount
            if ($commissionInCents >= $amountInCents) {
                throw new \Exception('Commission amount cannot be greater than or equal to total amount');
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                
                // Admin/platform commission (application fee)
                'application_fee_amount' => $commissionInCents,
                
                // Money goes to provider's Stripe Connect account
                'transfer_data' => [
                    'destination' => $providerStripeAccountId,
                ],
                
                'metadata' => array_merge($metadata, [
                    'payment_type' => 'destination_charge',
                    'provider_stripe_account_id' => $providerStripeAccountId,
                    'commission_amount' => $commissionAmount,
                ]),
                
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            Log::info('Stripe Destination Charge PaymentIntent created', [
                'payment_intent_id' => $paymentIntent->id,
                'total_amount' => $amount,
                'commission_amount' => $commissionAmount,
                'provider_amount' => $amount - $commissionAmount,
                'provider_stripe_account_id' => $providerStripeAccountId,
                'currency' => $currency,
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
                'commission_amount' => $commissionAmount,
                'provider_amount' => $amount - $commissionAmount,
                'currency' => $currency,
            ];
        } catch (ApiErrorException $e) {
            $errorMessage = $e->getMessage();
            
            // Provide more helpful error messages
            if (strpos($errorMessage, 'No such destination') !== false || 
                strpos($errorMessage, 'No such account') !== false) {
                $errorMessage = "Stripe account '{$providerStripeAccountId}' not found or not connected. Please verify the account ID is correct and the account is properly connected to the platform.";
            }
            
            Log::error('Stripe Destination Charge PaymentIntent creation failed', [
                'error' => $errorMessage,
                'stripe_error' => $e->getMessage(),
                'amount' => $amount,
                'commission_amount' => $commissionAmount,
                'provider_stripe_account_id' => $providerStripeAccountId,
                'currency' => $currency,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => 'stripe_api_error',
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

