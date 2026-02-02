<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StripeOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $accountName;
    public $accountType;
    public $onboardingUrl;
    public $appName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $accountName, string $accountType, string $onboardingUrl)
    {
        $this->accountName = $accountName;
        $this->accountType = $accountType;
        $this->onboardingUrl = $onboardingUrl;
        $this->appName = config('app.name', 'BOMEQP');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $accountTypeLabel = match($this->accountType) {
            'acc' => 'Accreditation Body',
            'training_center' => 'Training Center',
            'instructor' => 'Instructor',
            default => 'Account',
        };

        return $this->subject('Complete Your Stripe Connect Setup - ' . $this->appName)
                    ->view('emails.stripe-onboarding')
                    ->with([
                        'accountName' => $this->accountName,
                        'accountType' => $accountTypeLabel,
                        'onboardingUrl' => $this->onboardingUrl,
                        'appName' => $this->appName,
                    ]);
    }
}

