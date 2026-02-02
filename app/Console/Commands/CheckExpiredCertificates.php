<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update certificates that have passed their expiry date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired certificates...');

        try {
            // Find certificates that have expired (expiry_date is in the past)
            // Only update certificates that are currently 'valid' status
            $expiredCertificates = Certificate::where('status', 'valid')
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<', now()->toDateString())
                ->get();

            $updatedCount = 0;

            foreach ($expiredCertificates as $certificate) {
                $certificate->update(['status' => 'expired']);
                $updatedCount++;
                
                $this->line("Updated certificate ID {$certificate->id} (Number: {$certificate->certificate_number}) - Expired on {$certificate->expiry_date}");
            }

            if ($updatedCount > 0) {
                $this->info("Completed. Updated {$updatedCount} certificate(s) to expired status.");
                Log::info("Certificate expiration check completed", [
                    'updated_count' => $updatedCount,
                    'date' => now()->toDateString(),
                ]);
            } else {
                $this->info("No expired certificates found.");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error checking expired certificates: " . $e->getMessage());
            Log::error("Certificate expiration check failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}

