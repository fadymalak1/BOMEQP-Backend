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
        
        // Construct login URL - if FRONTEND_URL is set, use it, otherwise construct from APP_URL
        $frontendUrl = env('FRONTEND_URL');
        if ($frontendUrl) {
            $this->loginUrl = rtrim($frontendUrl, '/') . '/login';
        } else {
            // Fallback: construct from APP_URL (remove /api if present)
            $appUrl = rtrim(config('app.url'), '/');
            $appUrl = str_replace('/api', '', $appUrl);
            $this->loginUrl = $appUrl . '/login';
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

