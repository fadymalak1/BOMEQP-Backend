<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrainingClass;
use App\Models\Trainee;

class RecalculateDashboardNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:recalculate
                            {--enrolled-count : Recalculate enrolled_count for all training classes}
                            {--all : Recalculate all numbers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate dashboard numbers (enrolled_count, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recalculateAll = $this->option('all');
        $recalculateEnrolledCount = $this->option('enrolled-count') || $recalculateAll;

        if ($recalculateEnrolledCount) {
            $this->info('Recalculating enrolled_count for all training classes...');
            $this->recalculateEnrolledCount();
        }

        $this->info('Dashboard numbers recalculation completed!');
    }

    /**
     * Recalculate enrolled_count for all training classes based on actual trainee count
     */
    protected function recalculateEnrolledCount()
    {
        $trainingClasses = TrainingClass::all();
        $bar = $this->output->createProgressBar($trainingClasses->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($trainingClasses as $class) {
            $actualCount = $class->trainees()->count();
            
            if ($class->enrolled_count != $actualCount) {
                $class->enrolled_count = $actualCount;
                $class->save();
                $updated++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated: {$updated} training classes");
        $this->info("Skipped (already correct): {$skipped} training classes");
    }
}

