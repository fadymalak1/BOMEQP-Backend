<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;

class DebugCertificates extends Command
{
    protected $signature = 'certificates:debug';
    protected $description = 'Debug certificate data to see why types are not being detected correctly';

    public function handle()
    {
        $certificates = Certificate::with('instructor')->get();

        $this->info("Found {$certificates->count()} certificates:");
        $this->newLine();

        foreach ($certificates as $cert) {
            $this->line("Certificate ID: {$cert->id}");
            $this->line("  Current Type: " . ($cert->type ?? 'NULL'));
            $this->line("  Instructor ID: " . ($cert->instructor_id ?? 'NULL'));
            $this->line("  Trainee Name: " . ($cert->trainee_name ?? 'NULL'));
            
            if ($cert->instructor_id && $cert->instructor) {
                $instructor = $cert->instructor;
                $instructorName = trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? ''));
                $this->line("  Instructor Name: {$instructorName}");
                $this->line("  Instructor First Name: " . ($instructor->first_name ?? 'NULL'));
                $this->line("  Instructor Last Name: " . ($instructor->last_name ?? 'NULL'));
                
                // Show normalized comparison
                $normalizedInstructor = preg_replace('/\s+/', ' ', strtolower(trim($instructorName)));
                $normalizedTrainee = preg_replace('/\s+/', ' ', strtolower(trim($cert->trainee_name ?? '')));
                $this->line("  Normalized Instructor: '{$normalizedInstructor}'");
                $this->line("  Normalized Trainee: '{$normalizedTrainee}'");
                $this->line("  Match: " . ($normalizedInstructor === $normalizedTrainee ? 'YES' : 'NO'));
            } else {
                $this->line("  Instructor: NOT FOUND or NOT SET");
            }
            
            $calculatedType = Certificate::determineType($cert->instructor_id, $cert->trainee_name ?? '');
            $this->line("  Calculated Type: {$calculatedType}");
            $this->newLine();
        }

        return Command::SUCCESS;
    }
}

