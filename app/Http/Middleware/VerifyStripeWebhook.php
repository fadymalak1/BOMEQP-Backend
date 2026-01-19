<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyStripeWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // الحصول على التوقيع من header
        $signature = $request->header('Stripe-Signature');
        
        if (!$signature) {
            Log::warning('Stripe webhook signature missing');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        // الحصول على webhook secret
        $webhookSecret = $this->getWebhookSecret();
        
        if (!$webhookSecret) {
            Log::error('Stripe webhook secret not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        try {
            // الحصول على payload
            $payload = $request->getContent();
            
            // التحقق من التوقيع باستخدام Stripe SDK
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );

            // إضافة event إلى request للاستخدام في controller
            $request->merge(['stripe_event' => $event]);

            return $next($request);

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Exception in VerifyStripeWebhook', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['error' => 'Webhook verification failed'], 500);
        }
    }

    /**
     * Get webhook secret from settings or env
     */
    protected function getWebhookSecret(): ?string
    {
        // محاولة الحصول من قاعدة البيانات أولاً
        $settings = \App\Models\StripeSetting::getActive();
        if ($settings && !empty($settings->webhook_secret)) {
            return $settings->webhook_secret;
        }

        // Fallback إلى .env
        return env('STRIPE_WEBHOOK_SECRET');
    }
}

