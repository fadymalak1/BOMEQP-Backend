<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;

class BackfillCertificateTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:backfill-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill certificate types for existing certificates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting certificate type backfill...');

        // Get all certificates - we'll recalculate all types to ensure accuracy
        $certificates = Certificate::all();

        $this->info("Found {$certificates->count()} certificates to process.");

        if ($certificates->count() === 0) {
            $this->info('No certificates found.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($certificates->count());
        $bar->start();

        $instructorCount = 0;
        $traineeCount = 0;
        $updatedCount = 0;

        foreach ($certificates as $certificate) {
            $calculatedType = Certificate::determineType(
                $certificate->instructor_id,
                $certificate->trainee_name ?? ''
            );

            // Only update if type is different or not set
            if ($certificate->type !== $calculatedType) {
                $certificate->type = $calculatedType;
                $certificate->save();
                $updatedCount++;
            }

            if ($calculatedType === 'instructor') {
                $instructorCount++;
            } else {
                $traineeCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Backfill completed!");
        $this->info("Total certificates processed: {$certificates->count()}");
        $this->info("Certificates updated: {$updatedCount}");
        $this->info("Instructor certificates: {$instructorCount}");
        $this->info("Trainee certificates: {$traineeCount}");

        return Command::SUCCESS;
    }
}

