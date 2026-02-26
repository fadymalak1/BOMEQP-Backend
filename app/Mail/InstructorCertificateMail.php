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
    public $courseNames;

    /**
     * @param string       $instructorName
     * @param array|string $courseNames       One or more course names
     * @param string       $accName
     * @param string       $certificatePdfPath  Path to the single certificate PDF
     */
    public function __construct($instructorName, $courseNames, $accName, $certificatePdfPath)
    {
        $this->instructorName    = $instructorName;
        $this->courseNames       = is_array($courseNames) ? $courseNames : [$courseNames];
        $this->accName           = $accName;
        $this->certificatePdfPath = $certificatePdfPath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $mail = $this->subject('Congratulations! Your Instructor Authorization Certificate - ' . config('app.name'))
                     ->view('emails.instructor-certificate')
                     ->attach($this->certificatePdfPath, [
                         'as'   => 'instructor_authorization_certificate.pdf',
                         'mime' => 'application/pdf',
                     ])
                     ->with([
                         'instructorName' => $this->instructorName,
                         'courseNames'    => $this->courseNames,
                         'accName'        => $this->accName,
                         'appName'        => config('app.name'),
                     ]);

        return $mail;
    }
}

