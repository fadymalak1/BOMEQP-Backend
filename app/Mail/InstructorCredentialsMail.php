<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InstructorCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $password;
    public $instructorName;
    public $trainingCenterName;
    public $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($email, $password, $instructorName, $trainingCenterName)
    {
        $this->email = $email;
        $this->password = $password;
        $this->instructorName = $instructorName;
        $this->trainingCenterName = $trainingCenterName;
        
        // Construct login URL - use the frontend login URL
        $frontendUrl = env('FRONTEND_URL');
        if ($frontendUrl) {
            $this->loginUrl = rtrim($frontendUrl, '/') . '/login';
        } else {
            // Fallback: construct from APP_URL, extract base domain and remove /v1 and /api prefixes
            $appUrl = rtrim(config('app.url'), '/');
            // Parse URL to get base domain
            $parsedUrl = parse_url($appUrl);
            $scheme = $parsedUrl['scheme'] ?? 'https';
            $host = $parsedUrl['host'] ?? 'app.bomeqp.com';
            // Use base domain with /login (without /v1 or /api)
            $this->loginUrl = $scheme . '://' . $host . '/login';
        }
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Instructor Account Credentials - ' . config('app.name'))
                    ->view('emails.instructor-credentials')
                    ->with([
                        'email' => $this->email,
                        'password' => $this->password,
                        'instructorName' => $this->instructorName,
                        'trainingCenterName' => $this->trainingCenterName,
                        'loginUrl' => $this->loginUrl,
                        'appName' => config('app.name'),
                    ]);
    }
}

