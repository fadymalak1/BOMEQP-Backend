<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;

class SetCertificateType extends Command
{
    protected $signature = 'certificates:set-type {certificate_id} {type : instructor or trainee}';
    protected $description = 'Manually set the type of a specific certificate';

    public function handle()
    {
        $certificateId = $this->argument('certificate_id');
        $type = $this->argument('type');

        if (!in_array($type, ['instructor', 'trainee'])) {
            $this->error('Type must be either "instructor" or "trainee"');
            return Command::FAILURE;
        }

        $certificate = Certificate::find($certificateId);
        if (!$certificate) {
            $this->error("Certificate with ID {$certificateId} not found");
            return Command::FAILURE;
        }

        $oldType = $certificate->type ?? 'NULL';
        $certificate->type = $type;
        $certificate->save();

        $this->info("Certificate ID {$certificateId} type updated:");
        $this->line("  Old Type: {$oldType}");
        $this->line("  New Type: {$type}");
        $this->line("  Trainee Name: {$certificate->trainee_name}");
        if ($certificate->instructor_id) {
            $certificate->load('instructor');
            if ($certificate->instructor) {
                $instructorName = trim($certificate->instructor->first_name . ' ' . $certificate->instructor->last_name);
                $this->line("  Instructor: {$instructorName}");
            }
        }

        return Command::SUCCESS;
    }
}

