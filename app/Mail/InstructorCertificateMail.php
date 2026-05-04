<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InstructorCertificateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $instructorName;

    public $accName;

    public $certificatePdfPath;

    public function __construct(string $instructorName, string $accName, string $certificatePdfPath)
    {
        $this->instructorName     = $instructorName;
        $this->accName            = $accName;
        $this->certificatePdfPath = $certificatePdfPath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Congratulations! Your Instructor Authorization Certificate - '.config('app.name'))
            ->view('emails.instructor-certificate')
            ->attach($this->certificatePdfPath, [
                'as'   => 'instructor_authorization_certificate.pdf',
                'mime' => 'application/pdf',
            ])
            ->with([
                'instructorName' => $this->instructorName,
                'accName'        => $this->accName,
                'appName'        => config('app.name'),
            ]);
    }
}
