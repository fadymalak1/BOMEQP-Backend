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
    public $courseName;
    public $accName;
    public $certificatePdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct($instructorName, $courseName, $accName, $certificatePdfPath)
    {
        $this->instructorName = $instructorName;
        $this->courseName = $courseName;
        $this->accName = $accName;
        $this->certificatePdfPath = $certificatePdfPath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Congratulations! Your Instructor Authorization Certificate - ' . $this->courseName . ' - ' . config('app.name'))
                    ->view('emails.instructor-certificate')
                    ->attach($this->certificatePdfPath, [
                        'as' => 'instructor_authorization_certificate_' . str_replace(' ', '_', $this->courseName) . '.pdf',
                        'mime' => 'application/pdf',
                    ])
                    ->with([
                        'instructorName' => $this->instructorName,
                        'courseName' => $this->courseName,
                        'accName' => $this->accName,
                        'appName' => config('app.name'),
                    ]);
    }
}

