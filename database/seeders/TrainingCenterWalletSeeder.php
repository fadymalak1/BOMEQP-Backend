<?php

namespace Database\Seeders;

use App\Models\TrainingCenter;
use App\Models\TrainingCenterWallet;
use Illuminate\Database\Seeder;

class TrainingCenterWalletSeeder extends Seeder
{
    public function run(): void
    {
        $trainingCenters = TrainingCenter::all();

        foreach ($trainingCenters as $center) {
            TrainingCenterWallet::create([
                'training_center_id' => $center->id,
                'balance' => rand(1000, 10000),
                'currency' => 'USD',
                'last_updated' => now()->subDays(rand(1, 30)),
            ]);
        }
    }
}

