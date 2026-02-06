<?php

namespace App\Console\Commands;

use App\Models\TrainingClass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTrainingClassStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'training-classes:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update training class statuses based on start_date and end_date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating training class statuses based on dates...');

        $today = now()->toDateString();
        $updatedCount = 0;
        $scheduledCount = 0;
        $inProgressCount = 0;
        $completedCount = 0;

        // Get all classes that have start_date and end_date and are not cancelled
        $classes = TrainingClass::whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($classes as $class) {
            $startDate = $class->start_date;
            $endDate = $class->end_date;
            $currentStatus = $class->status;

            // Determine the appropriate status based on dates
            if ($today < $startDate) {
                // Class hasn't started yet
                $newStatus = 'scheduled';
            } elseif ($today >= $startDate && $today <= $endDate) {
                // Class is currently in progress
                $newStatus = 'in_progress';
            } else {
                // Class has ended
                $newStatus = 'completed';
            }

            // Update status if it has changed
            if ($currentStatus !== $newStatus) {
                try {
                    DB::transaction(function () use ($class, $newStatus) {
                        $class->update(['status' => $newStatus]);
                    });

                    $updatedCount++;
                    
                    // Track counts by status
                    switch ($newStatus) {
                        case 'scheduled':
                            $scheduledCount++;
                            break;
                        case 'in_progress':
                            $inProgressCount++;
                            break;
                        case 'completed':
                            $completedCount++;
                            break;
                    }

                    $this->line("Updated class ID {$class->id} ({$class->name}): {$currentStatus} -> {$newStatus}");
                } catch (\Exception $e) {
                    Log::error('Failed to update training class status', [
                        'class_id' => $class->id,
                        'error' => $e->getMessage()
                    ]);
                    $this->error("Failed to update class ID {$class->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Completed. Updated {$updatedCount} class(es).");
        $this->info("Status breakdown: {$scheduledCount} scheduled, {$inProgressCount} in_progress, {$completedCount} completed.");
        
        return Command::SUCCESS;
    }
}

