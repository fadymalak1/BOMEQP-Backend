<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;
    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
        
        // Construct reset URL - if FRONTEND_URL is set, use it, otherwise construct from APP_URL
        $frontendUrl = env('FRONTEND_URL');
        if ($frontendUrl) {
            $this->resetUrl = rtrim($frontendUrl, '/') . '/reset-password?token=' . $token . '&email=' . urlencode($email);
        } else {
            // Fallback: construct from APP_URL (remove /api if present)
            $appUrl = rtrim(config('app.url'), '/');
            $appUrl = str_replace('/api', '', $appUrl);
            $appUrl = str_replace('/v1', '', $appUrl);
            $this->resetUrl = $appUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email);
        }
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset Your Password - ' . config('app.name'))
                    ->view('emails.reset-password')
                    ->with([
                        'token' => $this->token,
                        'email' => $this->email,
                        'resetUrl' => $this->resetUrl,
                        'appName' => config('app.name'),
                    ]);
    }
}

