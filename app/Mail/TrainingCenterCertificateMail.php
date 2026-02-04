<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrainingCenterCertificateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $trainingCenterName;
    public $accName;
    public $certificatePdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct($trainingCenterName, $accName, $certificatePdfPath)
    {
        $this->trainingCenterName = $trainingCenterName;
        $this->accName = $accName;
        $this->certificatePdfPath = $certificatePdfPath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Congratulations! Your Training Center Authorization Certificate - ' . config('app.name'))
                    ->view('emails.training-center-certificate')
                    ->attach($this->certificatePdfPath, [
                        'as' => 'authorization_certificate.pdf',
                        'mime' => 'application/pdf',
                    ])
                    ->with([
                        'trainingCenterName' => $this->trainingCenterName,
                        'accName' => $this->accName,
                        'appName' => config('app.name'),
                    ]);
    }
}

