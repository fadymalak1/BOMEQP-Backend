<?php

namespace App\Jobs;

use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedTransferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Transfer $transfer;
    protected int $delayInSeconds;

    /**
     * Create a new job instance.
     */
    public function __construct(Transfer $transfer, int $delayInSeconds = 60)
    {
        $this->transfer = $transfer;
        $this->delayInSeconds = $delayInSeconds;
    }

    /**
     * Execute the job.
     */
    public function handle(TransferService $transferService): void
    {
        try {
            // Refresh transfer from database
            $this->transfer->refresh();

            // التحقق من إمكانية إعادة المحاولة
            if (!$this->transfer->canRetry()) {
                Log::info('Transfer cannot be retried', [
                    'transfer_id' => $this->transfer->id,
                    'status' => $this->transfer->status,
                    'retry_count' => $this->transfer->retry_count,
                ]);
                return;
            }

            Log::info('Retrying failed transfer', [
                'transfer_id' => $this->transfer->id,
                'transaction_id' => $this->transfer->transaction_id,
                'retry_count' => $this->transfer->retry_count + 1,
            ]);

            // محاولة إعادة التحويل
            $result = $transferService->retryFailedTransfer($this->transfer);

            if ($result['success']) {
                // نجحت إعادة المحاولة
                $this->transfer->markAsCompleted($result['stripe_transfer_id']);

                // إرسال إشعار
                $transferService->sendTransferNotification($this->transfer, 'completed');

                Log::info('Transfer retry succeeded', [
                    'transfer_id' => $this->transfer->id,
                    'stripe_transfer_id' => $result['stripe_transfer_id'],
                ]);
            } else {
                // فشلت إعادة المحاولة
                $this->transfer->markAsFailed($result['error'] ?? 'Retry failed');

                // إذا كان عدد المحاولات أقل من 3، جدولة محاولة أخرى
                if ($this->transfer->retry_count < 3) {
                    // زيادة التأخير في كل محاولة (60s, 120s, 240s)
                    $nextDelay = $this->delayInSeconds * pow(2, $this->transfer->retry_count);
                    
                    RetryFailedTransferJob::dispatch($this->transfer, $nextDelay)
                        ->delay(now()->addSeconds($nextDelay));

                    Log::info('Scheduled next retry attempt', [
                        'transfer_id' => $this->transfer->id,
                        'retry_count' => $this->transfer->retry_count,
                        'next_delay_seconds' => $nextDelay,
                    ]);
                } else {
                    // تجاوز عدد المحاولات - إشعار الإدارة
                    Log::error('Transfer failed after maximum retries', [
                        'transfer_id' => $this->transfer->id,
                        'retry_count' => $this->transfer->retry_count,
                        'error' => $result['error'],
                    ]);

                    // يمكن إضافة إشعار للإدارة هنا
                }
            }

        } catch (\Exception $e) {
            Log::error('Exception in RetryFailedTransferJob', [
                'transfer_id' => $this->transfer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // في حالة استثناء، نحاول مرة أخرى بعد تأخير أطول
            if ($this->transfer->retry_count < 3) {
                RetryFailedTransferJob::dispatch($this->transfer, 300)
                    ->delay(now()->addMinutes(5));
            }
        }
    }
}

