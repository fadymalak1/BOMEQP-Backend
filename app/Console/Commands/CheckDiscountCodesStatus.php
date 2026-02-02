<?php

namespace App\Console\Commands;

use App\Models\DiscountCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDiscountCodesStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discount-codes:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update discount codes status based on expiry date and quantity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking discount codes status...');

        $updatedCount = 0;
        $expiredCount = 0;
        $depletedCount = 0;

        // Get all active discount codes
        $discountCodes = DiscountCode::where('status', 'active')->get();

        foreach ($discountCodes as $discountCode) {
            $statusChanged = false;
            $newStatus = $discountCode->status;

            // Check if expired (end_date has passed)
            if ($discountCode->end_date && $discountCode->end_date < now()->toDateString()) {
                $newStatus = 'expired';
                $statusChanged = true;
                $expiredCount++;
            }
            // Check if depleted (used_quantity >= total_quantity)
            elseif ($discountCode->total_quantity && $discountCode->used_quantity >= $discountCode->total_quantity) {
                $newStatus = 'depleted';
                $statusChanged = true;
                $depletedCount++;
            }

            // Update status if changed
            if ($statusChanged) {
                $discountCode->update(['status' => $newStatus]);
                $updatedCount++;

                Log::info('Discount code status updated', [
                    'discount_code_id' => $discountCode->id,
                    'code' => $discountCode->code,
                    'old_status' => 'active',
                    'new_status' => $newStatus,
                ]);
            }
        }

        $this->info("Discount codes check completed.");
        $this->info("Total checked: " . $discountCodes->count());
        $this->info("Updated: {$updatedCount} (Expired: {$expiredCount}, Depleted: {$depletedCount})");

        return Command::SUCCESS;
    }
}

