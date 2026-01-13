<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\CertificateGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Certificate $certificate,
        public CertificateTemplate $template,
        public array $data
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CertificateGenerationService $certificateGenerationService): void
    {
        try {
            Log::info('Starting certificate PDF generation', [
                'certificate_id' => $this->certificate->id,
                'template_id' => $this->template->id,
            ]);

            // Generate certificate as PDF
            $generationResult = $certificateGenerationService->generate($this->template, $this->data, 'pdf');

            if (!$generationResult['success']) {
                Log::error('Certificate PDF generation failed', [
                    'certificate_id' => $this->certificate->id,
                    'error' => $generationResult['message'] ?? 'Unknown error',
                ]);

                // Update certificate status or handle failure
                $this->certificate->update([
                    'status' => 'revoked', // Or handle as needed
                ]);

                return;
            }

            // Update certificate with PDF URL
            $this->certificate->update([
                'certificate_pdf_url' => $generationResult['file_url'],
                'status' => 'valid',
            ]);

            Log::info('Certificate PDF generated successfully', [
                'certificate_id' => $this->certificate->id,
                'pdf_url' => $generationResult['file_url'],
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate PDF generation job failed', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update certificate status on failure
            $this->certificate->update([
                'status' => 'revoked', // Or handle as needed
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }
}

