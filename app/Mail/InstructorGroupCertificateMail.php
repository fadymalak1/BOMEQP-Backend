<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InstructorGroupCertificateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $instructorName;
    public array $accNames;
    public string $certificatePdfPath;
    public string $appName;

    /**
     * @param string   $instructorName     Full name of the instructor
     * @param string[] $accNames           Names of all ACCs that authorized the instructor
     * @param string   $certificatePdfPath Absolute filesystem path to the generated PDF
     */
    public function __construct(string $instructorName, array $accNames, string $certificatePdfPath)
    {
        $this->instructorName     = $instructorName;
        $this->accNames           = $accNames;
        $this->certificatePdfPath = $certificatePdfPath;
        $this->appName            = config('app.name');
    }

    public function build(): self
    {
        return $this
            ->subject('Congratulations! Your Multi-ACC Instructor Achievement Certificate - ' . config('app.name'))
            ->view('emails.instructor-group-certificate')
            ->attach($this->certificatePdfPath, [
                'as'   => 'instructor_achievement_certificate.pdf',
                'mime' => 'application/pdf',
            ])
            ->with([
                'instructorName' => $this->instructorName,
                'accNames'       => $this->accNames,
                'appName'        => $this->appName,
            ]);
    }
}
